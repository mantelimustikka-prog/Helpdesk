<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use WPHelpdesk\Interfaces\Admin\Pages\SettingsPage;
use WPHelpdesk\Support\Constants;

require_once __DIR__ . '/bootstrap.php';

/**
 * Tests for the retention-days setting wired into the General settings tab.
 */
final class RetentionSettingsTest extends TestCase {

	private SettingsPage $page;

	protected function setUp(): void {
		wp_helpdesk_test_reset_state();
		$this->page = new SettingsPage();
	}

	public function testGeneralSavePersistsRetentionDays(): void {
		$this->submit(
			array(
				'hd_general_ticket_number_start'     => '1000',
				'hd_general_ticket_number_increment' => '1',
				'hd_general_default_status'          => 'new',
				'hd_general_default_priority'        => 'normal',
				'hd_general_auto_assign_mode'        => 'none',
				'hd_general_timezone_mode'           => 'network',
				'hd_general_date_format'             => 'wp_default',
				'hd_data_retention_days'             => '90',
			)
		);

		self::assertSame( 90, $GLOBALS['wp_site_options'][ Constants::OPTION_GENERAL_RETENTION_DAYS ] );
	}

	public function testGeneralSaveRejectsZeroRetentionDays(): void {
		$this->submit(
			array(
				'hd_general_ticket_number_start'     => '1000',
				'hd_general_ticket_number_increment' => '1',
				'hd_general_default_status'          => 'new',
				'hd_general_default_priority'        => 'normal',
				'hd_general_auto_assign_mode'        => 'none',
				'hd_general_timezone_mode'           => 'network',
				'hd_general_date_format'             => 'wp_default',
				'hd_data_retention_days'             => '0',
			)
		);

		self::assertArrayNotHasKey( Constants::OPTION_GENERAL_RETENTION_DAYS, $GLOBALS['wp_site_options'] );
		self::assertSame( 'error', $GLOBALS['wp_settings_errors'][0]['type'] );
	}

	public function testGeneralSaveDefaultsRetentionDaysTo365WhenMissing(): void {
		$this->submit(
			array(
				'hd_general_ticket_number_start'     => '1000',
				'hd_general_ticket_number_increment' => '1',
				'hd_general_default_status'          => 'new',
				'hd_general_default_priority'        => 'normal',
				'hd_general_auto_assign_mode'        => 'none',
				'hd_general_timezone_mode'           => 'network',
				'hd_general_date_format'             => 'wp_default',
				// hd_data_retention_days intentionally omitted.
			)
		);

		self::assertSame( 365, $GLOBALS['wp_site_options'][ Constants::OPTION_GENERAL_RETENTION_DAYS ] );
	}

	public function testRetentionDaysFieldRenderedInGeneralTab(): void {
		$GLOBALS['wp_site_options'][ Constants::OPTION_GENERAL_RETENTION_DAYS ] = 180;

		$_GET['tab'] = 'general';
		ob_start();
		$this->page->render();
		$output = (string) ob_get_clean();

		self::assertStringContainsString( 'hd_data_retention_days', $output );
		self::assertStringContainsString( 'value="180"', $output );
		self::assertStringContainsString( 'Auto-Delete After', $output );
	}

	/**
	 * @param array<string, string> $post
	 */
	private function submit( array $post ): void {
		$_GET['page']              = 'wp-helpdesk-settings';
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST                     = array_merge(
			array(
				'hd_current_tab'    => 'general',
				'hd_settings_nonce' => 'valid-settings-nonce',
			),
			$post
		);

		$this->page->handlePost();
	}
}
