<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use WPHelpdesk\Interfaces\Admin\Pages\TicketsPage;

require_once __DIR__ . '/bootstrap.php';

/**
 * Test double that exposes renderOrderRelationRow publicly and allows
 * getWooCommerceOrder to be overridden in anonymous sub-classes.
 */
class TicketsPageOrderLinkTestDouble extends TicketsPage {
	public function renderOrderRelationRowPublic( array $ticket ): string {
		ob_start();
		$this->renderOrderRelationRow( $ticket );
		return (string) ob_get_clean();
	}
}

final class TicketsPageOrderLinkTest extends TestCase {
	protected function setUp(): void {
		wp_helpdesk_test_reset_state();
	}

	public function testNotAnyExistingOrderRelatedShowsLabel(): void {
		$page   = new TicketsPageOrderLinkTestDouble();
		$output = $page->renderOrderRelationRowPublic( array( 'order_relation' => 'not_any_existing_order_related' ) );

		self::assertStringContainsString( 'Not any existing order related', $output );
		self::assertStringNotContainsString( '<a ', $output );
	}

	public function testEmptyOrderRelationRendersNothing(): void {
		$page   = new TicketsPageOrderLinkTestDouble();
		$output = $page->renderOrderRelationRowPublic( array( 'order_relation' => '' ) );

		self::assertSame( '', $output );
	}

	public function testMissingOrderRelationKeyRendersNothing(): void {
		$page   = new TicketsPageOrderLinkTestDouble();
		$output = $page->renderOrderRelationRowPublic( array() );

		self::assertSame( '', $output );
	}

	public function testNumericOrderRelationShowsLinkWhenWooCommerceAvailable(): void {
		// Register a minimal wc_get_order stub that returns a fake order object.
		if ( ! function_exists( 'wc_get_order' ) ) {
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
			function wc_get_order( int $order_id ) {
				return new class( $order_id ) {
					private int $id;

					public function __construct( int $id ) {
						$this->id = $id;
					}

					public function get_order_number(): string {
						return (string) $this->id;
					}

					public function get_edit_order_url(): string {
						return 'https://example.test/wp-admin/admin.php?page=wc-orders&action=edit&id=' . $this->id;
					}
				};
			}
		}

		$page = new class extends TicketsPageOrderLinkTestDouble {
			protected function getWooCommerceOrder( int $order_id ) {
				return wc_get_order( $order_id );
			}
		};

		$output = $page->renderOrderRelationRowPublic( array( 'order_relation' => '101' ) );

		self::assertStringContainsString( '<a ', $output );
		self::assertStringContainsString( 'id=101', $output );
		self::assertStringContainsString( '#101', $output );
	}

	public function testNumericOrderRelationFallsBackGracefullyWhenOrderMissing(): void {
		$page = new class extends TicketsPageOrderLinkTestDouble {
			protected function getWooCommerceOrder( int $order_id ) {
				return false;
			}
		};

		$output = $page->renderOrderRelationRowPublic( array( 'order_relation' => '999' ) );

		self::assertStringContainsString( '#999', $output );
		self::assertStringNotContainsString( '<a ', $output );
	}

	public function testMultisiteOrderLinkSwitchesToOriginatingSite(): void {
		$GLOBALS['wp_is_multisite']       = true;
		$GLOBALS['wp_switch_to_blog_log'] = array();

		$page = new class extends TicketsPageOrderLinkTestDouble {
			protected function getWooCommerceOrder( int $order_id ) {
				return new class( $order_id ) {
					private int $id;

					public function __construct( int $id ) {
						$this->id = $id;
					}

					public function get_order_number(): string {
						return (string) $this->id;
					}

					public function get_edit_order_url(): string {
						return 'https://site5.example.test/wp-admin/admin.php?page=wc-orders&id=' . $this->id;
					}
				};
			}
		};

		$output = $page->renderOrderRelationRowPublic( array(
			'order_relation' => '200',
			'site_id'        => 5,
		) );

		self::assertContains( 5, $GLOBALS['wp_switch_to_blog_log'], 'switch_to_blog must be called with the ticket site_id' );
		self::assertStringContainsString( '<a ', $output );
		self::assertStringContainsString( '#200', $output );
		self::assertStringContainsString( 'site5.example.test', $output );
	}

	public function testSingleSiteOrderLinkDoesNotSwitchBlog(): void {
		$GLOBALS['wp_is_multisite']       = false;
		$GLOBALS['wp_switch_to_blog_log'] = array();

		$page = new class extends TicketsPageOrderLinkTestDouble {
			protected function getWooCommerceOrder( int $order_id ) {
				return new class( $order_id ) {
					private int $id;

					public function __construct( int $id ) {
						$this->id = $id;
					}

					public function get_order_number(): string {
						return (string) $this->id;
					}

					public function get_edit_order_url(): string {
						return 'https://example.test/wp-admin/admin.php?page=wc-orders&id=' . $this->id;
					}
				};
			}
		};

		$output = $page->renderOrderRelationRowPublic( array(
			'order_relation' => '55',
			'site_id'        => 3,
		) );

		self::assertEmpty( $GLOBALS['wp_switch_to_blog_log'], 'switch_to_blog must NOT be called on a single-site install' );
		self::assertStringContainsString( '#55', $output );
	}
}

