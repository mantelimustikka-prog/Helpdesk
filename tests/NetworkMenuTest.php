<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use WPHelpdesk\Interfaces\Admin\NetworkMenu;
use WPHelpdesk\Support\Constants;

require_once __DIR__ . '/bootstrap.php';

final class NetworkMenuTest extends TestCase {
	protected function setUp(): void {
		wp_helpdesk_test_reset_state();
	}

	protected function tearDown(): void {
		wp_helpdesk_test_reset_state();
	}

	public function testEnqueueAssetsAddsAdminStatusBadgeInlineColors(): void {
		$GLOBALS['wp_site_options'][ Constants::OPTION_APPEARANCE_STATUS_NEW_COLOR ] = '#112233';
		$GLOBALS['wp_site_options'][ Constants::OPTION_APPEARANCE_STATUS_PENDING_AGENT_COLOR ] = '#445566';
		$GLOBALS['wp_site_options'][ Constants::OPTION_APPEARANCE_STATUS_PENDING_CLIENT_COLOR ] = '#778899';
		$GLOBALS['wp_site_options'][ Constants::OPTION_APPEARANCE_STATUS_RESOLVED_COLOR ] = '#aabbcc';
		$GLOBALS['wp_site_options'][ Constants::OPTION_APPEARANCE_STATUS_CLOSED_COLOR ] = '#ddee00';

		$menu = new NetworkMenu();
		$menu->enqueueAssets( 'toplevel_page_wp-helpdesk' );

		$inline_css = (string) ( $GLOBALS['wp_inline_styles']['wp-helpdesk-admin'] ?? '' );

		self::assertStringContainsString( '.hd-admin-wrap .hd-status-badge--new{background-color:#112233 !important;}', $inline_css );
		self::assertStringContainsString( '.hd-admin-wrap .hd-status-badge--pending_agent_reply{background-color:#445566 !important;}', $inline_css );
		self::assertStringContainsString( '.hd-admin-wrap .hd-status-badge--pending_client_reply{background-color:#778899 !important;}', $inline_css );
		self::assertStringContainsString( '.hd-admin-wrap .hd-status-badge--resolved{background-color:#aabbcc !important;}', $inline_css );
		self::assertStringContainsString( '.hd-admin-wrap .hd-status-badge--closed{background-color:#ddee00 !important;}', $inline_css );
	}
}
