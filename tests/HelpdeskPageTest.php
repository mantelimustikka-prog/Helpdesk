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
		self::assertStringContainsString( '>Home<', $output );
		self::assertStringContainsString( 'href="https://example.test/"', $output );
		self::assertStringNotContainsString( 'wp-login.php?redirect_to=', $output );
	}

	public function testRenderShowsGuestSubmitAndSignInWhenGuestTicketsEnabled(): void {
		$GLOBALS['wp_logged_in'] = false;
		$GLOBALS['wp_site_options'][ Constants::OPTION_GENERAL_ALLOW_GUEST ] = 1;

		$page = new HelpdeskPage();
		ob_start();
		$page->render();
		$output = (string) ob_get_clean();

		self::assertStringContainsString( '/helpdesk/new/', $output );
		self::assertStringContainsString( 'Sign in', $output );
		self::assertStringContainsString( 'wp-login.php?redirect_to=', $output );
		self::assertStringContainsString( rawurlencode( 'https://example.test/helpdesk/member/new/' ), $output );
		self::assertStringContainsString( '>Home<', $output );
		self::assertStringContainsString( 'href="https://example.test/"', $output );
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
	}

	public function testRenderShowsKnowledgeBaseCardWhenKbUrlProvided(): void {
		$GLOBALS['wp_logged_in'] = false;
		add_filter(
			'wp_helpdesk_frontend_kb_url',
			static function (): string {
				return 'https://example.test/help/';
			}
		);

		$page = new HelpdeskPage();
		ob_start();
		$page->render();
		$output = (string) ob_get_clean();

		self::assertStringContainsString( 'https://example.test/help/', $output );
	}

	public function testRenderSkipsKnowledgeBaseCardForUnsafeKbUrl(): void {
		$GLOBALS['wp_logged_in'] = true;
		add_filter(
			'wp_helpdesk_frontend_kb_url',
			static function (): string {
				return 'javascript:alert(1)';
			}
		);

		$page = new HelpdeskPage();
		ob_start();
		$page->render();
		$output = (string) ob_get_clean();

		self::assertStringNotContainsString( 'Browse help articles', $output );
	}
}
