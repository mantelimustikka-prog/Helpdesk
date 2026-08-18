<?php
/**
 * @package WP Helpdesk
 */

namespace WPHelpdesk\Domain\Topic;

use WPHelpdesk\Infrastructure\Database\Schema;
use WPHelpdesk\Support\Constants;

class TopicTransitionRepository {

	/**
	 * List transitions from a given topic.
	 *
	 * @param int $from_topic_id Source topic id.
	 * @param int $network_id    Network id.
	 * @param bool $active_only  Whether to return only active transitions.
	 * @return array<int, array<string, mixed>>
	 */
	public function listFrom( int $from_topic_id, int $network_id, bool $active_only = true ): array {
		global $wpdb;

		$table  = Schema::table( Constants::TABLE_TOPIC_TRANSITIONS );
		$where  = 'WHERE from_topic_id = %d AND network_id = %d';
		$params = [ $from_topic_id, $network_id ];

		if ( $active_only ) {
			$where .= ' AND is_active = 1';
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} {$where} ORDER BY sort_order ASC, id ASC",
				...$params
			),
			ARRAY_A
		);

		return $rows ?: [];
	}

	/**
	 * List all transitions for a network.
	 *
	 * @param int   $network_id  Network id.
	 * @param array $args        Optional: page, per_page, from_topic_id, to_topic_id.
	 * @return array<int, array<string, mixed>>
	 */
	public function list( int $network_id, array $args = [] ): array {
		global $wpdb;

		$table    = Schema::table( Constants::TABLE_TOPIC_TRANSITIONS );
		$per_page = isset( $args['per_page'] ) ? max( 1, (int) $args['per_page'] ) : 50;
		$page     = isset( $args['page'] ) ? max( 1, (int) $args['page'] ) : 1;
		$offset   = ( $page - 1 ) * $per_page;
		$where    = 'WHERE network_id = %d';
		$params   = [ $network_id ];

		if ( isset( $args['from_topic_id'] ) ) {
			$where   .= ' AND from_topic_id = %d';
			$params[] = (int) $args['from_topic_id'];
		}

		if ( isset( $args['to_topic_id'] ) ) {
			$where   .= ' AND to_topic_id = %d';
			$params[] = (int) $args['to_topic_id'];
		}

		if ( isset( $args['is_active'] ) && '' !== (string) $args['is_active'] ) {
			$where   .= ' AND is_active = %d';
			$params[] = (int) $args['is_active'];
		}

		$params[] = $per_page;
		$params[] = $offset;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} {$where} ORDER BY sort_order ASC, id ASC LIMIT %d OFFSET %d",
				...$params
			),
			ARRAY_A
		);

		return $rows ?: [];
	}

	/**
	 * Find a single transition by id.
	 *
	 * @param int $id         Transition id.
	 * @param int $network_id Network id.
	 * @return array<string, mixed>|null
	 */
	public function find( int $id, int $network_id ): ?array {
		global $wpdb;

		$table = Schema::table( Constants::TABLE_TOPIC_TRANSITIONS );
		$row   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d AND network_id = %d LIMIT 1",
				$id,
				$network_id
			),
			ARRAY_A
		);

		return $row ?: null;
	}

	/**
	 * Create a transition.
	 *
	 * @param array<string, mixed> $data Column data.
	 * @return int Inserted id or 0 on failure.
	 */
	public function create( array $data ): int {
		global $wpdb;

		$table  = Schema::table( Constants::TABLE_TOPIC_TRANSITIONS );
		$result = $wpdb->insert( $table, $data );

		return $result ? (int) $wpdb->insert_id : 0;
	}

	/**
	 * Update a transition.
	 *
	 * @param int                  $id         Transition id.
	 * @param array<string, mixed> $data       Column data.
	 * @param int                  $network_id Network id.
	 * @return bool
	 */
	public function update( int $id, array $data, int $network_id ): bool {
		global $wpdb;

		$table  = Schema::table( Constants::TABLE_TOPIC_TRANSITIONS );
		$result = $wpdb->update(
			$table,
			$data,
			[
				'id'         => $id,
				'network_id' => $network_id,
			]
		);

		if ( false === $result ) {
			return false;
		}

		if ( $result > 0 ) {
			return true;
		}

		return null !== $this->find( $id, $network_id );
	}

	/**
	 * Delete a transition.
	 *
	 * @param int $id         Transition id.
	 * @param int $network_id Network id.
	 * @return bool
	 */
	public function delete( int $id, int $network_id ): bool {
		global $wpdb;

		$table  = Schema::table( Constants::TABLE_TOPIC_TRANSITIONS );
		$result = $wpdb->delete(
			$table,
			[
				'id'         => $id,
				'network_id' => $network_id,
			]
		);

		return false !== $result && $result > 0;
	}

	/**
	 * Count active transitions from a topic.
	 *
	 * @param int $from_topic_id Source topic id.
	 * @param int $network_id    Network id.
	 * @return int
	 */
	public function countActiveFrom( int $from_topic_id, int $network_id ): int {
		global $wpdb;

		$table = Schema::table( Constants::TABLE_TOPIC_TRANSITIONS );

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE from_topic_id = %d AND network_id = %d AND is_active = 1",
				$from_topic_id,
				$network_id
			)
		);
	}
}
