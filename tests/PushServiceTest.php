<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use WPHelpdesk\Domain\Push\PushProviderInterface;
use WPHelpdesk\Domain\Push\PushService;
use WPHelpdesk\Support\Constants;

require_once __DIR__ . '/bootstrap.php';

final class PushServiceTest extends TestCase {
	protected function setUp(): void {
		wp_helpdesk_test_reset_state();
	}

	public function testPushSkipsDisabledAndInvalidConfigurations(): void {
		$provider = new class implements PushProviderInterface {
			/** @var array<int, array<string, mixed>> */
			public array $calls = array();

			public function send( array $device_tokens, string $title, string $body, array $data = array() ): bool {
				$this->calls[] = compact( 'device_tokens', 'title', 'body', 'data' );
				return true;
			}
		};

		$service = new class( $provider ) extends PushService {
			protected function getAdminTokens(): array {
				return array( 'token-1' );
			}

			protected function getUserTokens( int $user_id ): array {
				return array( 'token-' . $user_id );
			}
		};

		$service->notifyNewTicket( array( 'id' => 1, 'subject' => 'Subject' ) );
		self::assertCount( 0, $provider->calls );

		$GLOBALS['wp_site_options'][ Constants::OPTION_PUSH_ENABLED ] = 1;
		$GLOBALS['wp_site_options'][ Constants::OPTION_PUSH_TICKET_EVENTS ] = array( 'ticket_created' );
		$GLOBALS['wp_site_options'][ Constants::OPTION_FCM_MODE ] = 'legacy';

		$service->notifyNewTicket( array( 'id' => 2, 'subject' => 'Subject' ) );
		self::assertCount( 0, $provider->calls );

		$GLOBALS['wp_site_options'][ Constants::OPTION_FCM_SERVER_KEY ] = 'legacy-key';
		$service->notifyNewTicket( array( 'id' => 3, 'subject' => 'Subject' ) );
		self::assertCount( 1, $provider->calls );
		self::assertSame( 'ticket_created', $provider->calls[0]['data']['event_type'] );
		self::assertSame( 3, $provider->calls[0]['data']['ticket_id'] );
		self::assertSame( 'wphelpd://ticket/3', $provider->calls[0]['data']['deep_link'] );
		self::assertSame( 'ticket_created:3', $provider->calls[0]['data']['notification_id'] );
	}

	public function testPushRequiresValidV1Configuration(): void {
		$provider = new class implements PushProviderInterface {
			public int $calls = 0;

			public function send( array $device_tokens, string $title, string $body, array $data = array() ): bool {
				++$this->calls;
				return true;
			}
		};

		$service = new class( $provider ) extends PushService {
			protected function getAdminTokens(): array {
				return array( 'token-1' );
			}

			protected function getUserTokens( int $user_id ): array {
				return array( 'token-' . $user_id );
			}
		};

		$GLOBALS['wp_site_options'][ Constants::OPTION_PUSH_ENABLED ] = 1;
		$GLOBALS['wp_site_options'][ Constants::OPTION_PUSH_TICKET_EVENTS ] = array( 'status_changed' );
		$GLOBALS['wp_site_options'][ Constants::OPTION_FCM_MODE ] = 'v1';
		$GLOBALS['wp_site_options'][ Constants::OPTION_FCM_PROJECT_ID ] = 'project-id';
		$GLOBALS['wp_site_options'][ Constants::OPTION_FCM_SERVICE_ACCOUNT_JSON ] = 'not-json';

		$service->notifyStatusChanged( array( 'id' => 4, 'ticket_no' => 'HD-4' ), 'resolved' );
		self::assertSame( 0, $provider->calls );

		$GLOBALS['wp_site_options'][ Constants::OPTION_FCM_SERVICE_ACCOUNT_JSON ] = '{"client_email":"bot@example.test"}';
		$service->notifyStatusChanged( array( 'id' => 5, 'ticket_no' => 'HD-5' ), 'resolved' );
		self::assertSame( 1, $provider->calls );
	}

	public function testNewReplyPushIncludesRoutingPayload(): void {
		$provider = new class implements PushProviderInterface {
			/** @var array<int, array<string, mixed>> */
			public array $calls = array();

			public function send( array $device_tokens, string $title, string $body, array $data = array() ): bool {
				$this->calls[] = compact( 'device_tokens', 'title', 'body', 'data' );
				return true;
			}
		};

		$service = new class( $provider ) extends PushService {
			protected function getAdminTokens(): array {
				return array( 'token-1' );
			}
		};

		$GLOBALS['wp_site_options'][ Constants::OPTION_PUSH_ENABLED ] = 1;
		$GLOBALS['wp_site_options'][ Constants::OPTION_PUSH_TICKET_EVENTS ] = array( 'ticket_replied' );
		$GLOBALS['wp_site_options'][ Constants::OPTION_FCM_MODE ] = 'legacy';
		$GLOBALS['wp_site_options'][ Constants::OPTION_FCM_SERVER_KEY ] = 'legacy-key';

		$service->notifyNewReply(
			array( 'id' => 42, 'subject' => 'Subject' ),
			array(
				'id'   => 9001,
				'body' => 'Customer replied',
			)
		);

		self::assertCount( 1, $provider->calls );
		self::assertSame( 'ticket_replied', $provider->calls[0]['data']['event_type'] );
		self::assertSame( 42, $provider->calls[0]['data']['ticket_id'] );
		self::assertSame( 'wphelpd://ticket/42', $provider->calls[0]['data']['deep_link'] );
		self::assertSame( 'ticket_replied:42:9001', $provider->calls[0]['data']['notification_id'] );
	}
}
