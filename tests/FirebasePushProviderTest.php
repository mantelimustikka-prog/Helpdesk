<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use WPHelpdesk\Domain\Push\FirebasePushProvider;
use WPHelpdesk\Support\Constants;

require_once __DIR__ . '/bootstrap.php';

/**
 * Test FCM HTTP v1 send path on FirebasePushProvider.
 */
final class FirebasePushProviderTest extends TestCase {

	protected function setUp(): void {
		wp_helpdesk_test_reset_state();
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Return a provider stub in v1 mode whose getAccessToken() returns a fake token,
	 * bypassing real JWT / OAuth network calls in unit tests.
	 */
	private function makeV1Provider( string $project_id = 'my-project' ): FirebasePushProvider {
		$GLOBALS['wp_site_options'][ Constants::OPTION_FCM_MODE ]                 = 'v1';
		$GLOBALS['wp_site_options'][ Constants::OPTION_FCM_PROJECT_ID ]           = $project_id;
		$GLOBALS['wp_site_options'][ Constants::OPTION_FCM_SERVICE_ACCOUNT_JSON ] = json_encode(
			array(
				'client_email' => 'bot@example.iam.gserviceaccount.com',
				'private_key'  => 'stub-key',
			)
		);

		return new class extends FirebasePushProvider {
			/** @override */
			protected function getAccessToken(): ?string {
				return 'fake-bearer-token';
			}
		};
	}

	// -------------------------------------------------------------------------
	// v1 mode: basic delivery
	// -------------------------------------------------------------------------

	public function testV1SendPostsToCorrectEndpoint(): void {
		$provider = $this->makeV1Provider( 'test-project-123' );

		$result = $provider->send( array( 'device-token-abc' ), 'Hello', 'World' );

		self::assertTrue( $result );
		self::assertCount( 1, $GLOBALS['wp_remote_post_log'] );
		self::assertSame(
			'https://fcm.googleapis.com/v1/projects/test-project-123/messages:send',
			$GLOBALS['wp_remote_post_log'][0]['url']
		);
	}

	public function testV1SendIncludesBearerAuthorization(): void {
		$provider = $this->makeV1Provider();

		$provider->send( array( 'token-1' ), 'Title', 'Body' );

		$headers = $GLOBALS['wp_remote_post_log'][0]['args']['headers'] ?? array();
		self::assertStringStartsWith( 'Bearer ', $headers['Authorization'] );
	}

	public function testV1SendPayloadContainsNotificationAndData(): void {
		$provider = $this->makeV1Provider();

		$data = array(
			'event_type'      => 'ticket_replied',
			'ticket_id'       => 42,
			'deep_link'       => 'wphelpd://ticket/42',
			'notification_id' => 'ticket_replied:42:9001',
		);

		$provider->send( array( 'device-tok' ), 'Reply', 'Customer replied', $data );

		$body    = json_decode( $GLOBALS['wp_remote_post_log'][0]['args']['body'] ?? '{}', true );
		$message = $body['message'] ?? array();

		self::assertSame( 'device-tok', $message['token'] );
		self::assertSame( 'Reply', $message['notification']['title'] );
		self::assertSame( 'Customer replied', $message['notification']['body'] );
		self::assertSame( 'ticket_replied', $message['data']['event_type'] );
		self::assertSame( '42', $message['data']['ticket_id'] );
		self::assertSame( 'wphelpd://ticket/42', $message['data']['deep_link'] );
		self::assertSame( 'ticket_replied:42:9001', $message['data']['notification_id'] );
	}

	public function testV1SendOneRequestPerToken(): void {
		$provider = $this->makeV1Provider();

		$provider->send( array( 'tok-a', 'tok-b', 'tok-c' ), 'T', 'B' );

		self::assertCount( 3, $GLOBALS['wp_remote_post_log'] );
	}

	public function testV1SendReturnsFalseOnHttpError(): void {
		$provider = $this->makeV1Provider();

		$GLOBALS['wp_remote_post_response'] = array( 'response' => array( 'code' => 500 ) );

		$result = $provider->send( array( 'bad-token' ), 'T', 'B' );

		self::assertFalse( $result );
	}

	public function testV1SendReturnsFalseWhenAccessTokenUnavailable(): void {
		$GLOBALS['wp_site_options'][ Constants::OPTION_FCM_MODE ]       = 'v1';
		$GLOBALS['wp_site_options'][ Constants::OPTION_FCM_PROJECT_ID ] = 'proj';
		$GLOBALS['wp_site_options'][ Constants::OPTION_FCM_SERVICE_ACCOUNT_JSON ] = '{}';

		// No getAccessToken override – will fail because private_key / client_email are absent.
		$provider = new FirebasePushProvider();
		$result   = $provider->send( array( 'tok' ), 'T', 'B' );

		self::assertFalse( $result );
		// No FCM request should have been made.
		self::assertCount( 0, $GLOBALS['wp_remote_post_log'] );
	}

	public function testV1SendReturnsFalseWhenNoTokens(): void {
		$provider = $this->makeV1Provider();

		$result = $provider->send( array(), 'T', 'B' );

		self::assertFalse( $result );
		self::assertCount( 0, $GLOBALS['wp_remote_post_log'] );
	}

	public function testSendDefaultsToV1WhenModeIsNotConfigured(): void {
		unset( $GLOBALS['wp_site_options'][ Constants::OPTION_FCM_MODE ] );
		$GLOBALS['wp_site_options'][ Constants::OPTION_FCM_PROJECT_ID ]           = 'default-v1-project';
		$GLOBALS['wp_site_options'][ Constants::OPTION_FCM_SERVICE_ACCOUNT_JSON ] = json_encode(
			array(
				'client_email' => 'bot@example.iam.gserviceaccount.com',
				'private_key'  => 'stub-key',
			)
		);

		$provider = new class extends FirebasePushProvider {
			protected function getAccessToken(): ?string {
				return 'default-v1-token';
			}
		};

		$result = $provider->send( array( 'device-token-abc' ), 'Hello', 'World' );

		self::assertTrue( $result );
		self::assertSame(
			'https://fcm.googleapis.com/v1/projects/default-v1-project/messages:send',
			$GLOBALS['wp_remote_post_log'][0]['url']
		);
	}

	// -------------------------------------------------------------------------
	// Legacy mode: existing behaviour should be unchanged.
	// -------------------------------------------------------------------------

	public function testLegacySendPostsToLegacyEndpoint(): void {
		$GLOBALS['wp_site_options'][ Constants::OPTION_FCM_MODE ]       = 'legacy';
		$GLOBALS['wp_site_options'][ Constants::OPTION_FCM_SERVER_KEY ] = 'my-server-key';

		$provider = new FirebasePushProvider();
		$result   = $provider->send( array( 'tok-legacy' ), 'T', 'B' );

		self::assertTrue( $result );
		self::assertSame(
			'https://fcm.googleapis.com/fcm/send',
			$GLOBALS['wp_remote_post_log'][0]['url']
		);
		self::assertSame( 'key=my-server-key', $GLOBALS['wp_remote_post_log'][0]['args']['headers']['Authorization'] );
	}

	public function testLegacySendReturnsFalseWithoutServerKey(): void {
		$GLOBALS['wp_site_options'][ Constants::OPTION_FCM_MODE ]       = 'legacy';
		$GLOBALS['wp_site_options'][ Constants::OPTION_FCM_SERVER_KEY ] = '';

		$provider = new FirebasePushProvider();
		$result   = $provider->send( array( 'tok' ), 'T', 'B' );

		self::assertFalse( $result );
	}

	// -------------------------------------------------------------------------
	// Logging side-effect tests.
	// -------------------------------------------------------------------------

	public function testV1SendLogsFcmModeEntry(): void {
		$provider = $this->makeV1Provider();

		$provider->send( array( 'tok' ), 'T', 'B' );

		$actions = array_column( $GLOBALS['hd_log_calls'], 'action' );
		self::assertContains( 'push.fcm_mode', $actions );

		$modeEntry = array_values(
			array_filter( $GLOBALS['hd_log_calls'], static fn( $e ) => 'push.fcm_mode' === $e['action'] )
		)[0];
		self::assertSame( 'v1', $modeEntry['context']['mode'] );
	}

	public function testLegacySendLogsFcmModeEntry(): void {
		$GLOBALS['wp_site_options'][ Constants::OPTION_FCM_MODE ]       = 'legacy';
		$GLOBALS['wp_site_options'][ Constants::OPTION_FCM_SERVER_KEY ] = 'key';

		$provider = new FirebasePushProvider();
		$provider->send( array( 'tok' ), 'T', 'B' );

		$modeEntries = array_values(
			array_filter( $GLOBALS['hd_log_calls'], static fn( $e ) => 'push.fcm_mode' === $e['action'] )
		);
		self::assertNotEmpty( $modeEntries );
		self::assertSame( 'legacy', $modeEntries[0]['context']['mode'] );
	}

	public function testV1SendLogsSuccessPerToken(): void {
		$provider = $this->makeV1Provider();

		$provider->send( array( 'mytoken-xyz' ), 'T', 'B' );

		$sentEntries = array_values(
			array_filter( $GLOBALS['hd_log_calls'], static fn( $e ) => 'push.fcm_sent' === $e['action'] )
		);
		self::assertCount( 1, $sentEntries );
		self::assertSame( 'v1', $sentEntries[0]['context']['mode'] );
		self::assertSame( 'mytoken-', $sentEntries[0]['context']['token_prefix'] );
	}

	public function testV1SendLogsHttpErrorPerToken(): void {
		$provider = $this->makeV1Provider();

		$GLOBALS['wp_remote_post_response'] = array( 'response' => array( 'code' => 400 ) );

		$provider->send( array( 'bad-token' ), 'T', 'B' );

		$errEntries = array_values(
			array_filter( $GLOBALS['hd_log_calls'], static fn( $e ) => 'push.fcm_error' === $e['action'] )
		);
		self::assertCount( 1, $errEntries );
		self::assertSame( 'http_error', $errEntries[0]['context']['error'] );
		self::assertSame( 400, $errEntries[0]['context']['http_code'] );
	}

	public function testV1SendLogsWpError(): void {
		$provider = $this->makeV1Provider();

		$GLOBALS['wp_remote_post_response'] = new WP_Error( 'conn_failed', 'Connection timed out' );

		$provider->send( array( 'any-token' ), 'T', 'B' );

		$errEntries = array_values(
			array_filter( $GLOBALS['hd_log_calls'], static fn( $e ) => 'push.fcm_error' === $e['action'] )
		);
		self::assertCount( 1, $errEntries );
		self::assertSame( 'wp_error', $errEntries[0]['context']['error'] );
		self::assertSame( 'Connection timed out', $errEntries[0]['context']['message'] );
	}

	public function testV1SendLogsMissingProjectId(): void {
		$GLOBALS['wp_site_options'][ Constants::OPTION_FCM_MODE ]       = 'v1';
		$GLOBALS['wp_site_options'][ Constants::OPTION_FCM_PROJECT_ID ] = '';

		$provider = new FirebasePushProvider();
		$provider->send( array( 'tok' ), 'T', 'B' );

		$errEntries = array_values(
			array_filter( $GLOBALS['hd_log_calls'], static fn( $e ) => 'push.fcm_error' === $e['action'] )
		);
		self::assertCount( 1, $errEntries );
		self::assertSame( 'missing_project_id', $errEntries[0]['context']['error'] );
	}

	public function testV1SendLogsOauthTokenUnavailable(): void {
		$GLOBALS['wp_site_options'][ Constants::OPTION_FCM_MODE ]                 = 'v1';
		$GLOBALS['wp_site_options'][ Constants::OPTION_FCM_PROJECT_ID ]           = 'proj';
		$GLOBALS['wp_site_options'][ Constants::OPTION_FCM_SERVICE_ACCOUNT_JSON ] = '{}';

		$provider = new FirebasePushProvider();
		$provider->send( array( 'tok' ), 'T', 'B' );

		$errEntries = array_values(
			array_filter( $GLOBALS['hd_log_calls'], static fn( $e ) => 'push.fcm_error' === $e['action'] )
		);
		self::assertCount( 1, $errEntries );
		self::assertSame( 'oauth2_token_unavailable', $errEntries[0]['context']['error'] );
	}

	public function testLegacySendLogsSuccessfulDelivery(): void {
		$GLOBALS['wp_site_options'][ Constants::OPTION_FCM_MODE ]       = 'legacy';
		$GLOBALS['wp_site_options'][ Constants::OPTION_FCM_SERVER_KEY ] = 'key';

		$provider = new FirebasePushProvider();
		$provider->send( array( 'tok-1', 'tok-2' ), 'T', 'B' );

		$sentEntries = array_values(
			array_filter( $GLOBALS['hd_log_calls'], static fn( $e ) => 'push.fcm_sent' === $e['action'] )
		);
		self::assertCount( 1, $sentEntries );
		self::assertSame( 'legacy', $sentEntries[0]['context']['mode'] );
		self::assertSame( 2, $sentEntries[0]['context']['token_count'] );
	}

	public function testLegacySendLogsHttpError(): void {
		$GLOBALS['wp_site_options'][ Constants::OPTION_FCM_MODE ]       = 'legacy';
		$GLOBALS['wp_site_options'][ Constants::OPTION_FCM_SERVER_KEY ] = 'key';
		$GLOBALS['wp_remote_post_response']                             = array( 'response' => array( 'code' => 500 ) );

		$provider = new FirebasePushProvider();
		$result   = $provider->send( array( 'tok' ), 'T', 'B' );

		self::assertFalse( $result );

		$errEntries = array_values(
			array_filter( $GLOBALS['hd_log_calls'], static fn( $e ) => 'push.fcm_error' === $e['action'] )
		);
		self::assertCount( 1, $errEntries );
		self::assertSame( 'http_error', $errEntries[0]['context']['error'] );
		self::assertSame( 'legacy', $errEntries[0]['context']['mode'] );
		self::assertSame( 500, $errEntries[0]['context']['http_code'] );
	}

	public function testLegacySendLogsMissingServerKey(): void {
		$GLOBALS['wp_site_options'][ Constants::OPTION_FCM_MODE ]       = 'legacy';
		$GLOBALS['wp_site_options'][ Constants::OPTION_FCM_SERVER_KEY ] = '';

		$provider = new FirebasePushProvider();
		$provider->send( array( 'tok' ), 'T', 'B' );

		$errEntries = array_values(
			array_filter( $GLOBALS['hd_log_calls'], static fn( $e ) => 'push.fcm_error' === $e['action'] )
		);
		self::assertCount( 1, $errEntries );
		self::assertSame( 'missing_server_key', $errEntries[0]['context']['error'] );
		self::assertSame( 'legacy', $errEntries[0]['context']['mode'] );
	}
}
