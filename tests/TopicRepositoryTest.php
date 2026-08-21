<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use WPHelpdesk\Domain\Topic\TopicRepository;

require_once __DIR__ . '/bootstrap.php';

final class TopicRepositoryTest extends TestCase {
	protected function setUp(): void {
		wp_helpdesk_test_reset_state();
	}

	public function testCreateRetriesWithoutTypeOnLegacyUnknownColumnError(): void {
		global $wpdb;

		$wpdb = new class {
			public string $base_prefix = 'wp_';
			public string $last_error = '';
			public int $insert_id = 0;
			/** @var array<int, array<string, mixed>> */
			public array $insert_calls = array();

			public function insert( string $table, array $data, $format = null ) {
				$this->insert_calls[] = $data;

				if ( 1 === count( $this->insert_calls ) ) {
					$this->last_error = "Unknown column 'type' in 'field list'";
					return false;
				}

				$this->last_error = '';
				$this->insert_id  = 55;
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
		self::assertCount( 2, $wpdb->insert_calls );
		self::assertArrayHasKey( 'type', $wpdb->insert_calls[0] );
		self::assertArrayHasKey( 'parent_id', $wpdb->insert_calls[0] );
		self::assertArrayNotHasKey( 'type', $wpdb->insert_calls[1] );
		self::assertArrayHasKey( 'parent_id', $wpdb->insert_calls[1] );
	}

	public function testCreateRetriesWithoutParentIdOnLegacyUnknownColumnError(): void {
		global $wpdb;

		$wpdb = new class {
			public string $base_prefix = 'wp_';
			public string $last_error = '';
			public int $insert_id = 0;
			/** @var array<int, array<string, mixed>> */
			public array $insert_calls = array();

			public function insert( string $table, array $data, $format = null ) {
				$this->insert_calls[] = $data;

				if ( 1 === count( $this->insert_calls ) ) {
					$this->last_error = "Unknown column 'parent_id' in 'field list'";
					return false;
				}

				$this->last_error = '';
				$this->insert_id  = 77;
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
		self::assertCount( 2, $wpdb->insert_calls );
		self::assertArrayHasKey( 'type', $wpdb->insert_calls[0] );
		self::assertArrayHasKey( 'parent_id', $wpdb->insert_calls[0] );
		self::assertArrayHasKey( 'type', $wpdb->insert_calls[1] );
		self::assertSame( 'followup', $wpdb->insert_calls[1]['type'] );
		self::assertArrayNotHasKey( 'parent_id', $wpdb->insert_calls[1] );
	}
}
