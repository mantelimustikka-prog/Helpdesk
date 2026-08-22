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
}
