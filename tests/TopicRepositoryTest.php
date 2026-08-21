<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use WPHelpdesk\Domain\Topic\TopicRepository;

require_once __DIR__ . '/bootstrap.php';

final class TopicRepositoryTest extends TestCase {
	protected function setUp(): void {
		wp_helpdesk_test_reset_state();
	}

	public function testCreateFiltersOutTypeWhenColumnMissingFromSchema(): void {
		global $wpdb;

		$wpdb = new class {
			public string $base_prefix = 'wp_';
			public string $last_error = '';
			public int $insert_id = 0;
			/** @var array<int, array<string, mixed>> */
			public array $insert_calls = array();

			public function get_col( string $sql, int $col_offset = 0 ): array {
				return array( 'id', 'network_id', 'title', 'slug', 'parent_id', 'description', 'is_final', 'is_active', 'sort_order', 'created_at', 'updated_at' );
			}

			public function insert( string $table, array $data, $format = null ) {
				$this->insert_calls[] = $data;
				$this->last_error     = '';
				$this->insert_id      = 55;
				return 1;
			}
		};

		$repository = new TopicRepository();

		$topic_id = $repository->create(
			array(
				'network_id' => 1,
				'title'      => 'General',
				'slug'       => 'general',
				'type'       => 'root',
				'parent_id'  => null,
			)
		);

		self::assertSame( 55, $topic_id );
		self::assertCount( 1, $wpdb->insert_calls );
		self::assertArrayNotHasKey( 'type', $wpdb->insert_calls[0] );
		self::assertArrayHasKey( 'parent_id', $wpdb->insert_calls[0] );
	}

	public function testCreateFiltersOutParentIdWhenColumnMissingFromSchema(): void {
		global $wpdb;

		$wpdb = new class {
			public string $base_prefix = 'wp_';
			public string $last_error = '';
			public int $insert_id = 0;
			/** @var array<int, array<string, mixed>> */
			public array $insert_calls = array();

			public function get_col( string $sql, int $col_offset = 0 ): array {
				return array( 'id', 'network_id', 'title', 'slug', 'type', 'description', 'is_final', 'is_active', 'sort_order', 'created_at', 'updated_at' );
			}

			public function insert( string $table, array $data, $format = null ) {
				$this->insert_calls[] = $data;
				$this->last_error     = '';
				$this->insert_id      = 77;
				return 1;
			}
		};

		$repository = new TopicRepository();

		$topic_id = $repository->create(
			array(
				'network_id' => 1,
				'title'      => 'Billing',
				'slug'       => 'billing',
				'type'       => 'followup',
				'parent_id'  => 12,
			)
		);

		self::assertSame( 77, $topic_id );
		self::assertCount( 1, $wpdb->insert_calls );
		self::assertArrayHasKey( 'type', $wpdb->insert_calls[0] );
		self::assertSame( 'followup', $wpdb->insert_calls[0]['type'] );
		self::assertArrayNotHasKey( 'parent_id', $wpdb->insert_calls[0] );
	}

	public function testCreateFiltersOutBothLegacyColumnsWhenBothMissingFromSchema(): void {
		global $wpdb;

		$wpdb = new class {
			public string $base_prefix = 'wp_';
			public string $last_error = '';
			public int $insert_id = 0;
			/** @var array<int, array<string, mixed>> */
			public array $insert_calls = array();

			public function get_col( string $sql, int $col_offset = 0 ): array {
				return array( 'id', 'network_id', 'title', 'slug', 'description', 'is_final', 'is_active', 'sort_order', 'created_at', 'updated_at' );
			}

			public function insert( string $table, array $data, $format = null ) {
				$this->insert_calls[] = $data;
				$this->last_error     = '';
				$this->insert_id      = 91;
				return 1;
			}
		};

		$repository = new TopicRepository();

		$topic_id = $repository->create(
			array(
				'network_id' => 1,
				'title'      => 'General',
				'slug'       => 'general',
				'type'       => 'root',
				'parent_id'  => null,
			)
		);

		self::assertSame( 91, $topic_id );
		self::assertCount( 1, $wpdb->insert_calls );
		self::assertArrayNotHasKey( 'type', $wpdb->insert_calls[0] );
		self::assertArrayNotHasKey( 'parent_id', $wpdb->insert_calls[0] );
	}

	public function testCreateSucceedsWithFullSchemaAndReturnsInsertId(): void {
		global $wpdb;

		$wpdb = new class {
			public string $base_prefix = 'wp_';
			public string $last_error = '';
			public int $insert_id = 0;
			/** @var array<int, array<string, mixed>> */
			public array $insert_calls = array();

			public function get_col( string $sql, int $col_offset = 0 ): array {
				return array( 'id', 'network_id', 'title', 'slug', 'type', 'parent_id', 'description', 'is_final', 'is_active', 'sort_order', 'created_at', 'updated_at' );
			}

			public function insert( string $table, array $data, $format = null ) {
				$this->insert_calls[] = $data;
				$this->last_error     = '';
				$this->insert_id      = 42;
				return 1;
			}
		};

		$repository = new TopicRepository();

		$topic_id = $repository->create(
			array(
				'network_id' => 1,
				'title'      => 'General',
				'slug'       => 'general',
				'type'       => 'root',
				'parent_id'  => null,
			)
		);

		self::assertSame( 42, $topic_id );
		self::assertCount( 1, $wpdb->insert_calls );
		self::assertArrayHasKey( 'type', $wpdb->insert_calls[0] );
		self::assertArrayHasKey( 'parent_id', $wpdb->insert_calls[0] );
	}

	public function testCreateFallsBackToFullPayloadWhenSchemaQueryReturnsEmpty(): void {
		global $wpdb;

		$wpdb = new class {
			public string $base_prefix = 'wp_';
			public string $last_error = '';
			public int $insert_id = 0;
			/** @var array<int, array<string, mixed>> */
			public array $insert_calls = array();

			public function get_col( string $sql, int $col_offset = 0 ): array {
				return array();
			}

			public function insert( string $table, array $data, $format = null ) {
				$this->insert_calls[] = $data;
				$this->last_error     = '';
				$this->insert_id      = 10;
				return 1;
			}
		};

		$repository = new TopicRepository();

		$topic_id = $repository->create(
			array(
				'network_id' => 1,
				'title'      => 'Billing',
				'slug'       => 'billing',
				'type'       => 'followup',
				'parent_id'  => 5,
			)
		);

		self::assertSame( 10, $topic_id );
		self::assertCount( 1, $wpdb->insert_calls );
		self::assertArrayHasKey( 'type', $wpdb->insert_calls[0] );
		self::assertArrayHasKey( 'parent_id', $wpdb->insert_calls[0] );
	}

	public function testGetLastDbErrorReturnsDbErrorAfterFailedInsert(): void {
		global $wpdb;

		$wpdb = new class {
			public string $base_prefix = 'wp_';
			public string $last_error  = "Duplicate entry 'slug' for key 'slug'";
			public int $insert_id = 0;

			public function get_col( string $sql, int $col_offset = 0 ): array {
				return array( 'id', 'network_id', 'title', 'slug', 'is_active' );
			}

			public function insert( string $table, array $data, $format = null ) {
				return false;
			}
		};

		$repository = new TopicRepository();
		$result     = $repository->create( array( 'network_id' => 1, 'title' => 'X', 'slug' => 'x', 'is_active' => 1 ) );

		self::assertSame( 0, $result );
		self::assertSame( "Duplicate entry 'slug' for key 'slug'", $repository->getLastDbError() );
	}
}
