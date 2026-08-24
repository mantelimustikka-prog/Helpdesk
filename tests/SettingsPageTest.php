<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use WPHelpdesk\Interfaces\Admin\Pages\SettingsPage;
use WPHelpdesk\Support\Constants;

require_once __DIR__ . '/bootstrap.php';

final class SettingsPageTest extends TestCase {
	private SettingsPage $page;

	protected function setUp(): void {
		wp_helpdesk_test_reset_state();
		$this->page = new SettingsPage();
	}

	public function testGeneralSavePersistsValidatedNetworkOptionsOnly(): void {
		$GLOBALS['wp_site_options'][ Constants::OPTION_TICKET_COUNTER ] = 50;

		$this->submit(
			'general',
			array(
				'hd_general_ticket_number_start'     => '2000',
				'hd_general_ticket_number_increment' => '5',
				'hd_general_default_status'          => 'new',
				'hd_general_default_priority'        => 'urgent',
				'hd_general_allow_guest_tickets'     => '1',
				'hd_general_require_topic'           => '1',
				'hd_general_auto_assign_mode'        => 'least_open',
				'hd_general_timezone_mode'           => 'utc',
				'hd_general_date_format'             => 'iso8601',
			)
		);

		self::assertSame( 2000, $GLOBALS['wp_site_options'][ Constants::OPTION_GENERAL_TICKET_NUMBER_START ] );
		self::assertSame( 5, $GLOBALS['wp_site_options'][ Constants::OPTION_GENERAL_TICKET_NUMBER_INC ] );
		self::assertSame( 'urgent', $GLOBALS['wp_site_options'][ Constants::OPTION_GENERAL_DEFAULT_PRIORITY ] );
		self::assertSame( 'least_open', $GLOBALS['wp_site_options'][ Constants::OPTION_GENERAL_AUTO_ASSIGN_MODE ] );
		self::assertSame( 'utc', $GLOBALS['wp_site_options'][ Constants::OPTION_GENERAL_TIMEZONE_MODE ] );
		self::assertSame( 'iso8601', $GLOBALS['wp_site_options'][ Constants::OPTION_GENERAL_DATE_FORMAT ] );
		self::assertSame( 2000, $GLOBALS['wp_site_options'][ Constants::OPTION_TICKET_START ] );
		self::assertSame( 2000, $GLOBALS['wp_site_options'][ Constants::OPTION_TICKET_COUNTER ] );
		self::assertSame( array(), $GLOBALS['wp_option_updates'] );
		self::assertSame( 'General settings saved.', $GLOBALS['wp_settings_errors'][0]['message'] );
	}

	public function testGeneralSaveRejectsInvalidValues(): void {
		$this->submit(
			'general',
			array(
				'hd_general_ticket_number_start'     => '0',
				'hd_general_ticket_number_increment' => '0',
				'hd_general_default_status'          => 'bogus',
				'hd_general_default_priority'        => 'bogus',
				'hd_general_auto_assign_mode'        => 'bogus',
				'hd_general_timezone_mode'           => 'bogus',
				'hd_general_date_format'             => 'bogus',
			)
		);

		self::assertArrayNotHasKey( Constants::OPTION_GENERAL_TICKET_NUMBER_START, $GLOBALS['wp_site_options'] );
		self::assertGreaterThanOrEqual( 2, count( $GLOBALS['wp_settings_errors'] ) );
		self::assertSame( 'error', $GLOBALS['wp_settings_errors'][0]['type'] );
	}

	public function testIntegrationEmailValidationAndApiRateLimitValidationFailSafely(): void {
		$this->submit(
			'integrations',
			array(
				'hd_email_from_address'        => 'not-an-email',
				'hd_email_reply_to_address'    => 'also-bad',
				'hd_api_rate_limit_per_minute' => '0',
			)
		);

		self::assertArrayNotHasKey( Constants::OPTION_EMAIL_FROM_ADDRESS, $GLOBALS['wp_site_options'] );
		self::assertSame( 3, count( $GLOBALS['wp_settings_errors'] ) );
	}

	public function testCapabilityFailureDies(): void {
		$GLOBALS['wp_current_user_caps']['hd_manage_settings'] = false;

		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'You do not have permission to manage helpdesk settings.' );

		$this->submit( 'general', array() );
	}

	public function testNonceFailureDies(): void {
		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'Security check failed.' );

		$this->submit( 'general', array(), 'bad-nonce' );
	}

	public function testSecretMaskingAndReplacementFlowNeverExposesStoredValues(): void {
		$GLOBALS['wp_site_options'][ Constants::OPTION_FCM_SERVER_KEY ] = 'super-secret-key';
		$GLOBALS['wp_site_options'][ Constants::OPTION_FCM_SERVICE_ACCOUNT_JSON ] = '{"client_email":"hidden@example.test"}';

		$_GET['tab'] = 'integrations';
		ob_start();
		$this->page->render();
		$output = (string) ob_get_clean();

		self::assertStringContainsString( '••••••••', $output );
		self::assertStringNotContainsString( 'super-secret-key', $output );
		self::assertStringNotContainsString( 'hidden@example.test', $output );

		$this->submit(
			'integrations',
			array(
				'hd_fcm_mode'                    => 'legacy',
				'hd_fcm_server_key'              => '••••••••',
				'hd_fcm_service_account_json'    => '••••••••',
				'hd_api_rate_limit_per_minute'   => '60',
			)
		);

		self::assertSame( 'super-secret-key', $GLOBALS['wp_site_options'][ Constants::OPTION_FCM_SERVER_KEY ] );
		self::assertSame( '{"client_email":"hidden@example.test"}', $GLOBALS['wp_site_options'][ Constants::OPTION_FCM_SERVICE_ACCOUNT_JSON ] );

		$this->submit(
			'integrations',
			array(
				'hd_fcm_mode'                    => 'legacy',
				'hd_fcm_server_key'              => 'replacement-key',
				'hd_fcm_service_account_json'    => '{"client_email":"new@example.test"}',
				'hd_api_rate_limit_per_minute'   => '60',
			)
		);

		self::assertSame( 'replacement-key', $GLOBALS['wp_site_options'][ Constants::OPTION_FCM_SERVER_KEY ] );
		self::assertSame( '{"client_email":"new@example.test"}', $GLOBALS['wp_site_options'][ Constants::OPTION_FCM_SERVICE_ACCOUNT_JSON ] );
	}

	public function testIntegrationsRenderDefaultsToFcmV1WhenModeIsUnset(): void {
		$_GET['tab'] = 'integrations';
		ob_start();
		$this->page->render();
		$output = (string) ob_get_clean();

		self::assertStringContainsString( '<option value="v1" selected="selected">', $output );
		self::assertStringContainsString( 'FCM v1 is the default and recommended delivery mode.', $output );
	}

	public function testApiFlagsPersistAndAllowedOriginsAreSanitized(): void {
		$this->submit(
			'integrations',
			array(
				'hd_email_from_name'                       => 'Helpdesk Bot',
				'hd_email_from_address'                    => 'bot@example.test',
				'hd_email_reply_to_address'                => 'reply@example.test',
				'hd_email_header_enabled'                  => '1',
				'hd_email_footer_enabled'                  => '1',
				'hd_api_enabled'                           => '1',
				'hd_api_require_application_passwords'     => '1',
				'hd_api_rate_limit_per_minute'             => '5000',
				'hd_api_log_requests'                      => '1',
				'hd_api_allowed_origins'                   => "https://app.example.test\nnot-a-url\nhttps://admin.example.test\nhttps://app.example.test",
			)
		);

		self::assertSame( 1, $GLOBALS['wp_site_options'][ Constants::OPTION_API_ENABLED ] );
		self::assertSame( 1, $GLOBALS['wp_site_options'][ Constants::OPTION_API_REQUIRE_APP_PASSWORDS ] );
		self::assertSame( 5000, $GLOBALS['wp_site_options'][ Constants::OPTION_API_RATE_LIMIT ] );
		self::assertSame( 1, $GLOBALS['wp_site_options'][ Constants::OPTION_API_LOG_REQUESTS ] );
		self::assertSame(
			"https://app.example.test\nhttps://admin.example.test",
			$GLOBALS['wp_site_options'][ Constants::OPTION_API_ALLOWED_ORIGINS ]
		);
	}

	public function testSettingsRenderKeepsExpectedNetworkAdminRoutes(): void {
		$_GET['tab'] = 'general';

		ob_start();
		$this->page->render();
		$output = (string) ob_get_clean();

		self::assertStringContainsString( 'page=wp-helpdesk-settings&tab=general', $output );
		self::assertStringContainsString( 'page=wp-helpdesk-settings&tab=integrations', $output );
		self::assertStringContainsString( 'name="hd_general_ticket_number_start"', $output );
	}

	public function testAppearanceTabIsRenderedInNavigation(): void {
		$_GET['tab'] = 'general';

		ob_start();
		$this->page->render();
		$output = (string) ob_get_clean();

		self::assertStringContainsString( 'page=wp-helpdesk-settings&tab=appearance', $output );
		self::assertStringContainsString( 'Appearance', $output );
	}

	public function testAppearanceTabRendersColorFields(): void {
		$_GET['tab'] = 'appearance';

		ob_start();
		$this->page->render();
		$output = (string) ob_get_clean();

		self::assertStringContainsString( 'name="hd_appearance_admin_reply_color"', $output );
		self::assertStringContainsString( 'name="hd_appearance_client_reply_color"', $output );
		self::assertStringContainsString( 'hd_current_tab" value="appearance"', $output );
		// Color fields are text inputs so admins can clear them to restore the theme default.
		self::assertStringContainsString( 'type="text"', $output );
	}

	public function testAppearanceSavePersistsValidHexColors(): void {
		$this->submit(
			'appearance',
			array(
				'hd_appearance_admin_reply_color'  => '#1a2b3c',
				'hd_appearance_client_reply_color' => '#aabbcc',
			)
		);

		self::assertSame( '#1a2b3c', $GLOBALS['wp_site_options'][ Constants::OPTION_APPEARANCE_ADMIN_REPLY_COLOR ] );
		self::assertSame( '#aabbcc', $GLOBALS['wp_site_options'][ Constants::OPTION_APPEARANCE_CLIENT_REPLY_COLOR ] );
		self::assertSame( 'Appearance settings saved.', $GLOBALS['wp_settings_errors'][0]['message'] );
	}

	public function testAppearanceSaveRejectsInvalidHexColors(): void {
		$this->submit(
			'appearance',
			array(
				'hd_appearance_admin_reply_color'  => 'not-a-color',
				'hd_appearance_client_reply_color' => 'red',
			)
		);

		// Invalid colors are stored as empty strings (sanitize_hex_color returns null for bad input).
		self::assertSame( '', $GLOBALS['wp_site_options'][ Constants::OPTION_APPEARANCE_ADMIN_REPLY_COLOR ] );
		self::assertSame( '', $GLOBALS['wp_site_options'][ Constants::OPTION_APPEARANCE_CLIENT_REPLY_COLOR ] );
	}

	public function testAppearanceSavePreservesExistingColorWhenBlankSubmitted(): void {
		$GLOBALS['wp_site_options'][ Constants::OPTION_APPEARANCE_ADMIN_REPLY_COLOR ] = '#ffffff';

		$this->submit(
			'appearance',
			array(
				'hd_appearance_admin_reply_color'  => '',
				'hd_appearance_client_reply_color' => '#000000',
			)
		);

		// Blank submission clears the stored value.
		self::assertSame( '', $GLOBALS['wp_site_options'][ Constants::OPTION_APPEARANCE_ADMIN_REPLY_COLOR ] );
		self::assertSame( '#000000', $GLOBALS['wp_site_options'][ Constants::OPTION_APPEARANCE_CLIENT_REPLY_COLOR ] );
	}

	public function testIntegrationsSavePersistsPushTicketEvents(): void {
		$this->submit(
			'integrations',
			array(
				'hd_push_enabled'       => '1',
				'hd_push_ticket_events' => array( 'ticket_created', 'ticket_replied', 'status_changed', 'ticket_assigned' ),
				'hd_fcm_mode'           => 'legacy',
			)
		);

		self::assertSame( 1, $GLOBALS['wp_site_options'][ Constants::OPTION_PUSH_ENABLED ] );
		$saved_events = $GLOBALS['wp_site_options'][ Constants::OPTION_PUSH_TICKET_EVENTS ];
		self::assertContains( 'ticket_created', $saved_events );
		self::assertContains( 'ticket_replied', $saved_events );
		self::assertContains( 'status_changed', $saved_events );
		self::assertContains( 'ticket_assigned', $saved_events );
	}

	public function testIntegrationsSaveRejectsUnknownPushEvents(): void {
		$this->submit(
			'integrations',
			array(
				'hd_push_enabled'       => '1',
				'hd_push_ticket_events' => array( 'ticket_created', 'bogus_event', 'ticket_deleted' ),
				'hd_fcm_mode'           => 'legacy',
			)
		);

		$saved_events = $GLOBALS['wp_site_options'][ Constants::OPTION_PUSH_TICKET_EVENTS ] ?? array();
		self::assertContains( 'ticket_created', $saved_events );
		self::assertNotContains( 'bogus_event', $saved_events );
		self::assertNotContains( 'ticket_deleted', $saved_events );
	}

	/**
	 * @param array<string, string> $post
	 */
	private function submit( string $tab, array $post, string $nonce = 'valid-settings-nonce' ): void {
		$_GET['page'] = 'wp-helpdesk-settings';
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST = array_merge(
			array(
				'hd_current_tab'    => $tab,
				'hd_settings_nonce' => $nonce,
			),
			$post
		);

		$this->page->handlePost();
	}
}
