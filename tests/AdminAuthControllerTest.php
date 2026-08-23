<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use WPHelpdesk\Interfaces\Rest\AdminAuthController;

require_once __DIR__ . '/bootstrap.php';

final class AdminAuthControllerTest extends TestCase {
	protected function setUp(): void {
		wp_helpdesk_test_reset_state();
	}

	public function testCheckReturnsTopLevelUserPayload(): void {
		$GLOBALS['wp_current_user'] = (object) array(
			'ID'           => 42,
			'display_name' => 'Helpdesk Admin',
			'user_email'   => 'helpdesk@example.test',
			'roles'        => array( 'administrator', 'hd_support' ),
		);

		$controller = new AdminAuthController();
		$response   = $controller->check( new WP_REST_Request() );

		self::assertTrue( $response->data['success'] );
		self::assertArrayNotHasKey( 'data', $response->data );
		self::assertSame(
			array(
				'id'    => 42,
				'name'  => 'Helpdesk Admin',
				'email' => 'helpdesk@example.test',
				'roles' => array( 'administrator', 'hd_support' ),
			),
			$response->data['user']
		);
	}

	public function testCheckReturnsAppearanceColorsFromSiteOptions(): void {
		$GLOBALS['wp_current_user'] = (object) array(
			'ID'           => 1,
			'display_name' => 'Admin',
			'user_email'   => 'admin@example.test',
			'roles'        => array( 'administrator' ),
		);
		$GLOBALS['wp_site_options']['hd_appearance_admin_reply_color']            = '#1a73e8';
		$GLOBALS['wp_site_options']['hd_appearance_client_reply_color']           = '#34a853';
		$GLOBALS['wp_site_options']['hd_appearance_status_new_color']             = '#ea4335';
		$GLOBALS['wp_site_options']['hd_appearance_status_pending_agent_reply_color']  = '#ff6d00';
		$GLOBALS['wp_site_options']['hd_appearance_status_pending_client_reply_color'] = '#fdd835';
		$GLOBALS['wp_site_options']['hd_appearance_status_resolved_color']        = '#0f9d58';
		$GLOBALS['wp_site_options']['hd_appearance_status_closed_color']          = '#9e9e9e';

		$controller = new AdminAuthController();
		$response   = $controller->check( new WP_REST_Request() );

		self::assertTrue( $response->data['success'] );
		self::assertArrayHasKey( 'appearance', $response->data );
		$appearance = $response->data['appearance'];
		self::assertSame( '#1a73e8', $appearance['admin_reply_color'] );
		self::assertSame( '#34a853', $appearance['client_reply_color'] );
		self::assertSame( '#ea4335', $appearance['status_new_color'] );
		self::assertSame( '#ff6d00', $appearance['status_pending_agent_color'] );
		self::assertSame( '#fdd835', $appearance['status_pending_client_color'] );
		self::assertSame( '#0f9d58', $appearance['status_resolved_color'] );
		self::assertSame( '#9e9e9e', $appearance['status_closed_color'] );
	}

	public function testCheckReturnsEmptyAppearanceWhenNoColorsConfigured(): void {
		$GLOBALS['wp_current_user'] = (object) array(
			'ID'           => 1,
			'display_name' => 'Admin',
			'user_email'   => 'admin@example.test',
			'roles'        => array( 'administrator' ),
		);

		$controller = new AdminAuthController();
		$response   = $controller->check( new WP_REST_Request() );

		self::assertTrue( $response->data['success'] );
		self::assertArrayHasKey( 'appearance', $response->data );
		$appearance = $response->data['appearance'];
		self::assertSame( '', $appearance['admin_reply_color'] );
		self::assertSame( '', $appearance['client_reply_color'] );
		self::assertSame( '', $appearance['status_new_color'] );
		self::assertSame( '', $appearance['status_pending_agent_color'] );
		self::assertSame( '', $appearance['status_pending_client_color'] );
		self::assertSame( '', $appearance['status_resolved_color'] );
		self::assertSame( '', $appearance['status_closed_color'] );
	}
}
