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
	 * Insert a new topic row.
	 *
	 * @param array<string, mixed> $data Column data.
	 * @return int Inserted id or 0 on failure.
	 */
	public function create( array $data ): int {
		global $wpdb;

		$table  = Schema::table( Constants::TABLE_TOPICS );
		$result = $wpdb->insert( $table, $data );

		return $result ? (int) $wpdb->insert_id : 0;
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

		return array(
			'where'  => $where,
			'params' => $params,
		);
	}
}
