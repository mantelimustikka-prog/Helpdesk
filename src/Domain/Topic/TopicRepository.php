<?php
/**
 * @package WP Helpdesk
 */

namespace WPHelpdesk\Domain\Topic;

use WPHelpdesk\Infrastructure\Database\Schema;
use WPHelpdesk\Support\Constants;

class TopicRepository {
	/**
	 * List topics for a network with optional filters/pagination.
	 *
	 * @param int   $network_id Network id.
	 * @param array $args       Optional: search, page, per_page, orderby, order.
	 * @return array<int, array<string, mixed>>
	 */
	public function list( int $network_id, array $args = [] ): array {
		global $wpdb;

		$table    = Schema::table( Constants::TABLE_TOPICS );
		$per_page = isset( $args['per_page'] ) ? max( 1, (int) $args['per_page'] ) : 20;
		$page     = isset( $args['page'] ) ? max( 1, (int) $args['page'] ) : 1;
		$offset   = ( $page - 1 ) * $per_page;
		$filters  = $this->buildWhere( $network_id, $args );
		$where    = $filters['where'];
		$params   = $filters['params'];

		$params[] = $per_page;
		$params[] = $offset;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT t.* FROM {$table} t {$where} ORDER BY t.sort_order ASC, t.id ASC LIMIT %d OFFSET %d",
				...$params
			),
			ARRAY_A
		);

		return $rows ?: array();
	}

	/**
	 * Count topics for a network.
	 *
	 * @param int   $network_id Network id.
	 * @param array $args       Optional filters.
	 * @return int
	 */
	public function count( int $network_id, array $args = [] ): int {
		global $wpdb;

		$table   = Schema::table( Constants::TABLE_TOPICS );
		$filters = $this->buildWhere( $network_id, $args );
		$where   = $filters['where'];
		$params  = $filters['params'];

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} t {$where}",
				...$params
			)
		);
	}

	/**
	 * Find a single topic by id.
	 *
	 * @param int $id         Topic id.
	 * @param int $network_id Network id.
	 * @return array<string, mixed>|null
	 */
	public function find( int $id, int $network_id ): ?array {
		global $wpdb;

		$table = Schema::table( Constants::TABLE_TOPICS );
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
	 * Find a topic by slug.
	 *
	 * @param string $slug       Topic slug.
	 * @param int    $network_id Network id.
	 * @return array<string, mixed>|null
	 */
	public function findBySlug( string $slug, int $network_id ): ?array {
		global $wpdb;

		$table = Schema::table( Constants::TABLE_TOPICS );
		$row   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE slug = %s AND network_id = %d LIMIT 1",
				$slug,
				$network_id
			),
			ARRAY_A
		);

		return $row ?: null;
	}

	/**
	 * Find multiple topics by id within a network.
	 *
	 * @param array<int, int> $ids Topic ids.
	 * @param int             $network_id Network id.
	 * @return array<int, array<string, mixed>>
	 */
	public function findMany( array $ids, int $network_id ): array {
		global $wpdb;

		$ids = array_values( array_filter( array_map( 'intval', $ids ) ) );
		if ( empty( $ids ) ) {
			return array();
		}

		$table        = Schema::table( Constants::TABLE_TOPICS );
		$placeholders = implode( ', ', array_fill( 0, count( $ids ), '%d' ) );
		$params       = array_merge( array( $network_id ), $ids );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE network_id = %d AND id IN ({$placeholders})",
				...$params
			),
			ARRAY_A
		);

		return $rows ?: array();
	}

	/**
	 * Insert a new topic row.
	 *
	 * @param array<string, mixed> $data Column data.
	 * @return int Inserted id or 0 on failure.
	 */
	public function create( array $data ): int {
		global $wpdb;

		$table  = Schema::table( Constants::TABLE_TOPICS );
		$result = $wpdb->insert( $table, $data );
		if ( $result ) {
			return (int) $wpdb->insert_id;
		}

		$legacy_payload = $data;
		while ( true ) {
			$last_error = isset( $wpdb->last_error ) ? (string) $wpdb->last_error : '';
			$matches    = array();
			$did_match  = preg_match( "/Unknown column '(type|parent_id)' in 'field list'/i", $last_error, $matches );

			if ( false === $did_match || 1 !== $did_match ) {
				return 0;
			}

			$missing_column = strtolower( (string) ( $matches[1] ?? '' ) );
			if ( '' === $missing_column || ! array_key_exists( $missing_column, $legacy_payload ) ) {
				return 0;
			}

			unset( $legacy_payload[ $missing_column ] );
			$retry_result = $wpdb->insert( $table, $legacy_payload );

			if ( $retry_result ) {
				return (int) $wpdb->insert_id;
			}
		}
	}

	/**
	 * Update a topic row.
	 *
	 * @param int                  $id         Topic id.
	 * @param array<string, mixed> $data       Column data.
	 * @param int                  $network_id Network id.
	 * @return bool
	 */
	public function update( int $id, array $data, int $network_id ): bool {
		global $wpdb;

		$table  = Schema::table( Constants::TABLE_TOPICS );
		$result = $wpdb->update(
			$table,
			$data,
			array(
				'id'         => $id,
				'network_id' => $network_id,
			)
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
	 * Delete a topic row.
	 *
	 * @param int $id         Topic id.
	 * @param int $network_id Network id.
	 * @return bool
	 */
	public function delete( int $id, int $network_id ): bool {
		global $wpdb;

		$table  = Schema::table( Constants::TABLE_TOPICS );
		$result = $wpdb->delete(
			$table,
			array(
				'id'         => $id,
				'network_id' => $network_id,
			)
		);

		return false !== $result && $result > 0;
	}

	/**
	 * Update sort orders in a single query.
	 *
	 * @param array<int, int> $orders     Topic id => sort order map.
	 * @param int             $network_id Network id.
	 * @return bool
	 */
	public function updateSortOrders( array $orders, int $network_id ): bool {
		global $wpdb;

		if ( empty( $orders ) ) {
			return false;
		}

		$table           = Schema::table( Constants::TABLE_TOPICS );
		$case_fragments  = array();
		$ids             = array();
		$params          = array();

		foreach ( $orders as $topic_id => $sort_order ) {
			$topic_id = (int) $topic_id;
			if ( $topic_id <= 0 ) {
				continue;
			}

			$case_fragments[] = 'WHEN %d THEN %d';
			$params[]         = $topic_id;
			$params[]         = (int) $sort_order;
			$ids[]            = $topic_id;
		}

		if ( empty( $ids ) ) {
			return false;
		}

		$in_placeholders = implode( ', ', array_fill( 0, count( $ids ), '%d' ) );
		$sql             = "
			UPDATE {$table}
			SET sort_order = CASE id " . implode( ' ', $case_fragments ) . " END,
				updated_at = %s
			WHERE network_id = %d
			  AND id IN ({$in_placeholders})
		";

		$params[] = current_time( 'mysql' );
		$params[] = $network_id;
		foreach ( $ids as $topic_id ) {
			$params[] = $topic_id;
		}

		$result = $wpdb->query( $wpdb->prepare( $sql, ...$params ) );

		return false !== $result;
	}

	/**
	 * Count active outgoing transitions for a topic.
	 *
	 * @param int $topic_id    Topic id.
	 * @param int $network_id  Network id.
	 * @return int
	 */
	public function countActiveTransitionsFromTopic( int $topic_id, int $network_id ): int {
		global $wpdb;

		$table = Schema::table( Constants::TABLE_TOPIC_TRANSITIONS );

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE from_topic_id = %d AND network_id = %d AND is_active = 1",
				$topic_id,
				$network_id
			)
		);
	}

	/**
	 * Get active outgoing transition counts for multiple topics.
	 *
	 * @param array<int, int> $topic_ids   Topic ids.
	 * @param int             $network_id  Network id.
	 * @return array<int, int>
	 */
	public function getActiveTransitionCounts( array $topic_ids, int $network_id ): array {
		global $wpdb;

		$topic_ids = array_values( array_filter( array_map( 'intval', $topic_ids ) ) );
		if ( empty( $topic_ids ) ) {
			return array();
		}

		$table        = Schema::table( Constants::TABLE_TOPIC_TRANSITIONS );
		$placeholders = implode( ', ', array_fill( 0, count( $topic_ids ), '%d' ) );
		$params       = array_merge( array( $network_id ), $topic_ids );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT from_topic_id, COUNT(*) AS total
				 FROM {$table}
				 WHERE network_id = %d
				   AND is_active = 1
				   AND from_topic_id IN ({$placeholders})
				 GROUP BY from_topic_id",
				...$params
			),
			ARRAY_A
		);

		$counts = array();
		foreach ( $rows ?: array() as $row ) {
			$counts[ (int) $row['from_topic_id'] ] = (int) $row['total'];
		}

		return $counts;
	}

	/**
	 * List active top-level topics (topics without active incoming transitions).
	 *
	 * @param int $network_id Network id.
	 * @return array<int, array<string, mixed>>
	 */
	public function listActiveTopLevel( int $network_id ): array {
		global $wpdb;

		$topics_table      = Schema::table( Constants::TABLE_TOPICS );
		$transitions_table = Schema::table( Constants::TABLE_TOPIC_TRANSITIONS );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT t.*
				 FROM {$topics_table} t
				 WHERE t.network_id = %d
				   AND t.is_active = 1
				   AND NOT EXISTS (
						SELECT 1
						FROM {$transitions_table} tr
						WHERE tr.network_id = t.network_id
						  AND tr.to_topic_id = t.id
						  AND tr.is_active = 1
				   )
				 ORDER BY t.sort_order ASC, t.id ASC",
				$network_id
			),
			ARRAY_A
		);

		return $rows ?: array();
	}

	/**
	 * Count active incoming transitions to a topic.
	 *
	 * @param int $topic_id    Topic id.
	 * @param int $network_id  Network id.
	 * @return int
	 */
	public function countActiveIncomingTransitionsToTopic( int $topic_id, int $network_id ): int {
		global $wpdb;

		$table = Schema::table( Constants::TABLE_TOPIC_TRANSITIONS );

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*)
				 FROM {$table}
				 WHERE to_topic_id = %d
				   AND network_id = %d
				   AND is_active = 1",
				$topic_id,
				$network_id
			)
		);
	}

	/**
	 * Get active incoming transition counts for multiple topics.
	 *
	 * @param array<int, int> $topic_ids   Topic ids.
	 * @param int             $network_id  Network id.
	 * @return array<int, int>
	 */
	public function getActiveIncomingTransitionCounts( array $topic_ids, int $network_id ): array {
		global $wpdb;

		$topic_ids = array_values( array_filter( array_map( 'intval', $topic_ids ) ) );
		if ( empty( $topic_ids ) ) {
			return array();
		}

		$table        = Schema::table( Constants::TABLE_TOPIC_TRANSITIONS );
		$placeholders = implode( ', ', array_fill( 0, count( $topic_ids ), '%d' ) );
		$params       = array_merge( array( $network_id ), $topic_ids );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT to_topic_id, COUNT(*) AS total
				 FROM {$table}
				 WHERE network_id = %d
				   AND is_active = 1
				   AND to_topic_id IN ({$placeholders})
				 GROUP BY to_topic_id",
				...$params
			),
			ARRAY_A
		);

		$counts = array();
		foreach ( $rows ?: array() as $row ) {
			$counts[ (int) $row['to_topic_id'] ] = (int) $row['total'];
		}

		return $counts;
	}

	/**
	 * List active root topics (type = 'root') for a network.
	 *
	 * Falls back to the legacy top-level query for topics that pre-date the
	 * type column (i.e. topics whose type column is still the default 'root').
	 *
	 * @param int $network_id Network id.
	 * @return array<int, array<string, mixed>>
	 */
	public function listActiveRootTopics( int $network_id ): array {
		global $wpdb;

		$table = Schema::table( Constants::TABLE_TOPICS );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT t.*
				 FROM {$table} t
				 WHERE t.network_id = %d
				   AND t.is_active = 1
				   AND t.type = 'root'
				 ORDER BY t.sort_order ASC, t.id ASC",
				$network_id
			),
			ARRAY_A
		);

		return $rows ?: array();
	}

	/**
	 * List active child topics for a given parent topic id.
	 *
	 * @param int $parent_id  Parent topic id.
	 * @param int $network_id Network id.
	 * @return array<int, array<string, mixed>>
	 */
	public function listChildrenOf( int $parent_id, int $network_id ): array {
		global $wpdb;

		$table = Schema::table( Constants::TABLE_TOPICS );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT t.*
				 FROM {$table} t
				 WHERE t.network_id = %d
				   AND t.parent_id = %d
				   AND t.is_active = 1
				 ORDER BY t.sort_order ASC, t.id ASC",
				$network_id,
				$parent_id
			),
			ARRAY_A
		);

		return $rows ?: array();
	}

	/**
	 * Check whether a topic has any active children (by parent_id).
	 *
	 * @param int $topic_id   Topic id.
	 * @param int $network_id Network id.
	 * @return bool
	 */
	public function hasActiveChildren( int $topic_id, int $network_id ): bool {
		global $wpdb;

		$table = Schema::table( Constants::TABLE_TOPICS );

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE network_id = %d AND parent_id = %d AND is_active = 1",
				$network_id,
				$topic_id
			)
		) > 0;
	}

	/**
	 * Build the common WHERE clause for topic list/count queries.
	 *
	 * @param int                  $network_id Network id.
	 * @param array<string, mixed> $args       Query args.
	 * @return array{where:string, params:array<int, mixed>}
	 */
	private function buildWhere( int $network_id, array $args ): array {
		global $wpdb;

		$where  = 'WHERE t.network_id = %d';
		$params = array( $network_id );

		if ( ! empty( $args['search'] ) ) {
			$like      = '%' . $wpdb->esc_like( sanitize_text_field( (string) $args['search'] ) ) . '%';
			$where    .= ' AND (t.title LIKE %s OR t.slug LIKE %s)';
			$params[]  = $like;
			$params[]  = $like;
		}

		if ( isset( $args['is_active'] ) && '' !== (string) $args['is_active'] ) {
			$where    .= ' AND t.is_active = %d';
			$params[]  = (int) $args['is_active'];
		}

		if ( isset( $args['type'] ) && '' !== (string) $args['type'] ) {
			$where    .= ' AND t.type = %s';
			$params[]  = sanitize_key( (string) $args['type'] );
		}

		return array(
			'where'  => $where,
			'params' => $params,
		);
	}
}
