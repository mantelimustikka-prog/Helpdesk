<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use WPHelpdesk\Domain\Notification\NotificationService;
use WPHelpdesk\Support\Constants;

require_once __DIR__ . '/bootstrap.php';

final class NotificationServiceTest extends TestCase {
	protected function setUp(): void {
		wp_helpdesk_test_reset_state();
	}

	public function testHeaderFooterTogglesWrapOutboundMail(): void {
		$GLOBALS['wp_site_options'][ Constants::OPTION_EMAIL_HEADER_HTML ] = '<p>HEADER</p>';
		$GLOBALS['wp_site_options'][ Constants::OPTION_EMAIL_FOOTER_HTML ] = '<p>FOOTER</p>';
		$GLOBALS['wp_site_options'][ Constants::OPTION_EMAIL_HEADER_ENABLED ] = 1;
		$GLOBALS['wp_site_options'][ Constants::OPTION_EMAIL_FOOTER_ENABLED ] = 1;

		$service = new class extends NotificationService {
			protected function renderTemplate( string $template_path, array $vars ): string {
				return '<div>BODY</div>';
			}
		};

		$service->sendTicketCreated( array( 'ticket_no' => 'HD-001000' ), 'customer@example.test' );
		self::assertStringContainsString( '<p>HEADER</p><div>BODY</div><p>FOOTER</p>', $GLOBALS['wp_mail_calls'][0]['message'] );

		$GLOBALS['wp_mail_calls'] = array();
		$GLOBALS['wp_site_options'][ Constants::OPTION_EMAIL_HEADER_ENABLED ] = 0;
		$GLOBALS['wp_site_options'][ Constants::OPTION_EMAIL_FOOTER_ENABLED ] = 1;

		$service->sendTicketCreated( array( 'ticket_no' => 'HD-001001' ), 'customer@example.test' );
		self::assertSame( '<div>BODY</div><p>FOOTER</p>', $GLOBALS['wp_mail_calls'][0]['message'] );
	}

	public function testSendTicketReplyIncludesGuestLinkFromRawGuestToken(): void {
		$service = new NotificationService();

		$service->sendTicketReply(
			array(
				'ticket_no'   => 'HD-900001',
				'subject'     => 'Guest question',
				'guest_token' => 'guest-token-abc',
			),
			array( 'body' => 'Agent update' ),
			'guest@example.test'
		);

		$mail = $GLOBALS['wp_mail_calls'][0]['message'] ?? '';
		self::assertStringContainsString( 'View and continue this ticket', $mail );
		self::assertStringContainsString( '/helpdesk/ticket/HD-900001/guest-token-abc/', $mail );
	}

	public function testSendTicketReplyGeneratesGuestLinkFromGuestTicketHash(): void {
		global $wpdb;
		$wpdb = new class {
			public string $base_prefix = 'wp_';
			public string $sitemeta = 'wp_sitemeta';
			/** @var array<string, mixed> */
			public array $updated_data = array();
			/** @var array<string, mixed> */
			public array $updated_where = array();

			public function update( string $table, array $data, array $where, array $format = array(), array $where_format = array() ): int {
				$this->updated_data  = $data;
				$this->updated_where = $where;
				return 1;
			}
		};

		$service = new NotificationService();
		$service->sendTicketReply(
			array(
				'id'               => 55,
				'user_id'          => 0,
				'ticket_no'        => 'HD-900002',
				'subject'          => 'Guest follow-up',
				'guest_token_hash' => hash( 'sha256', 'old-token' ),
			),
			array( 'body' => 'Agent reply' ),
			'guest@example.test'
		);

		self::assertSame( 55, $wpdb->updated_where['id'] ?? null );
		self::assertNotSame( hash( 'sha256', 'old-token' ), $wpdb->updated_data['guest_token_hash'] ?? '' );
		self::assertSame( 64, strlen( (string) ( $wpdb->updated_data['guest_token_hash'] ?? '' ) ) );

		$mail = $GLOBALS['wp_mail_calls'][0]['message'] ?? '';
		self::assertStringContainsString( 'View and continue this ticket', $mail );
		self::assertSame( 1, preg_match( '#/helpdesk/ticket/HD-900002/[0-9a-f]{64}/#', $mail ) );
	}

	public function testSendTicketReplyKeepsLoggedInFlowUnchangedWithoutTicketLink(): void {
		$service = new NotificationService();

		$service->sendTicketReply(
			array(
				'id'        => 99,
				'user_id'   => 10,
				'ticket_no' => 'HD-900003',
				'subject'   => 'Member ticket',
			),
			array( 'body' => 'Agent reply' ),
			'member@example.test'
		);

		$mail = $GLOBALS['wp_mail_calls'][0]['message'] ?? '';
		self::assertStringContainsString( 'Please log in to continue the conversation.', $mail );
		self::assertStringNotContainsString( '/helpdesk/ticket/HD-900003/', $mail );
	}
}
