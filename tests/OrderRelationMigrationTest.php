<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/bootstrap.php';

final class OrderRelationMigrationTest extends TestCase {
	protected function setUp(): void {
		wp_helpdesk_test_reset_state();
	}

	public function testRepairMigrationAddsOrderRelationWhenMissing(): void {
		global $wpdb;
		$wpdb = new class {
			public string $base_prefix = 'md_';
			/** @var array<int, string> */
			public array $queries = array();

			public function get_col( string $query ): array {
				return array( 'id', 'ticket_no', 'topic_path_json' );
			}

			public function query( string $query ): int {
				$this->queries[] = $query;
				return 1;
			}
		};

		$migration = require dirname( __DIR__ ) . '/migrations/017_repair_order_relation_column.php';
		$migration->up();

		self::assertNotEmpty( $wpdb->queries );
		self::assertStringContainsString( 'md_hd_tickets', $wpdb->queries[0] );
		self::assertStringContainsString( 'ADD COLUMN order_relation', $wpdb->queries[0] );
	}
}
