<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use WPHelpdesk\Interfaces\Admin\Pages\DashboardPage;

require_once __DIR__ . '/bootstrap.php';

final class DashboardPageTest extends TestCase {
	protected function setUp(): void {
		wp_helpdesk_test_reset_state();
	}

	public function testRenderShowsFrontendInterfacesSectionAndLinks(): void {
		global $wpdb;

		$wpdb = new class {
			public string $base_prefix = 'wp_';

			public function prepare( string $query, ...$args ): string {
				return $query;
			}

			public function get_results( string $query, string $output = ARRAY_A ): array {
				return array();
			}

			public function get_var( string $query ) {
				return 0;
			}
		};

		$page = new DashboardPage();

		ob_start();
		$page->render();
		$output = (string) ob_get_clean();

		self::assertStringContainsString( 'Front-end Interfaces', $output );
		self::assertStringContainsString( '/helpdesk/', $output );
		self::assertStringContainsString( '/helpdesk/member/new/', $output );
		self::assertStringContainsString( '/my-account/helpdesk/requests/', $output );
		self::assertStringContainsString( 'request/{ticket-no}', $output );
	}
}
