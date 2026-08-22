<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use WPHelpdesk\Interfaces\Frontend\HelpdeskPage;
use WPHelpdesk\Support\Constants;

require_once __DIR__ . '/bootstrap.php';

final class HelpdeskPageTest extends TestCase {
	protected function setUp(): void {
		wp_helpdesk_test_reset_state();
	}

	protected function tearDown(): void {
		wp_helpdesk_test_reset_state();
	}

	public function testRenderShowsMemberHubActionsWhenLoggedIn(): void {
		$GLOBALS['wp_logged_in'] = true;

		$page = new HelpdeskPage();
		ob_start();
		$page->render();
		$output = (string) ob_get_clean();

		self::assertStringContainsString( 'class="hd-nav-menu"', $output );
		self::assertStringContainsString( '/helpdesk/member/new/', $output );
		self::assertStringContainsString( '/helpdesk/requests/', $output );
		self::assertStringContainsString( 'New Request', $output );
		self::assertStringContainsString( 'My Requests', $output );
		self::assertStringContainsString( 'My Account', $output );
		self::assertStringContainsString( 'Home', $output );
		self::assertStringContainsString( 'href="https://example.test/my-account/"', $output );
		self::assertStringContainsString( 'href="https://example.test/"', $output );
		self::assertStringNotContainsString( 'wp-login.php?redirect_to=', $output );
		self::assertStringNotContainsString( 'Browse articles', $output );
	}

	public function testRenderShowsGuestMenuWhenGuestTicketsEnabled(): void {
		$GLOBALS['wp_logged_in'] = false;
		$GLOBALS['wp_site_options'][ Constants::OPTION_GENERAL_ALLOW_GUEST ] = 1;

		$page = new HelpdeskPage();
		ob_start();
		$page->render();
		$output = (string) ob_get_clean();

		self::assertStringContainsString( '/helpdesk/new/', $output );
		self::assertStringContainsString( 'wp-login.php?redirect_to=', $output );
		self::assertStringContainsString( rawurlencode( 'https://example.test/helpdesk/requests/' ), $output );
		self::assertStringContainsString( 'My Account', $output );
		self::assertStringContainsString( 'href="https://example.test/my-account/"', $output );
		self::assertStringContainsString( 'Home', $output );
		self::assertStringContainsString( 'href="https://example.test/"', $output );
		self::assertStringNotContainsString( 'Sign in', $output );
		self::assertStringNotContainsString( 'Browse articles', $output );
	}

	public function testRenderHidesGuestSubmitWhenGuestTicketsDisabled(): void {
		$GLOBALS['wp_logged_in'] = false;
		$GLOBALS['wp_site_options'][ Constants::OPTION_GENERAL_ALLOW_GUEST ] = 0;

		$page = new HelpdeskPage();
		ob_start();
		$page->render();
		$output = (string) ob_get_clean();

		self::assertStringNotContainsString( '/helpdesk/new/', $output );
		self::assertStringContainsString( 'My Requests', $output );
		self::assertStringContainsString( 'My Account', $output );
		self::assertStringContainsString( 'Home', $output );
	}

	public function testRenderSkipsMyAccountWhenWooAccountUrlUnavailable(): void {
		$GLOBALS['wc_page_permalinks']['myaccount'] = '';

		$page = new HelpdeskPage();
		ob_start();
		$page->render();
		$output = (string) ob_get_clean();

		self::assertStringNotContainsString( 'My Account', $output );
	}
}
