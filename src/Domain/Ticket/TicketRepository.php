<?php
/**
 * @package WP Helpdesk
 */

namespace WPHelpdesk\Domain\Ticket;

use WPHelpdesk\Infrastructure\Database\Schema;
use WPHelpdesk\Support\Constants;

class TicketRepository {

	/**
	 * List tickets for a network with optional filters/pagination.
	 *
	 * @param int   $network_id Network id.
	 * @param array $args       Optional: status, priority, assigned_to, search, page, per_page, orderby, order.
	 * @return array<int, array<string, mixed>>
	 */
	public function list( int $network_id, array $args = [] ): array {
		global $wpdb;

		$table    = Schema::table( Constants::TABLE_TICKETS );
		$per_page = isset( $args['per_page'] ) ? max( 1, (int) $args['per_page'] ) : 20;
		$page     = isset( $args['page'] ) ? max( 1, (int) $args['page'] ) : 1;
		$offset   = ( $page - 1 ) * $per_page;
		$filters  = $this->buildWhere( $network_id, $args );
		$where    = $filters['where'];
		$params   = $filters['params'];
		$orderby  = $this->safeOrderBy( $args['orderby'] ?? 'created_at' );
		$order    = 'ASC' === strtoupper( (string) ( $args['order'] ?? '' ) ) ? 'ASC' : 'DESC';

		$params[] = $per_page;
		$params[] = $offset;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} {$where} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d",
				...$params
			),
			ARRAY_A
		);

		return $rows ?: [];
	}

	/**
	 * Count tickets for a network.
	 *
	 * @param int   $network_id Network id.
	 * @param array $args       Optional filters.
	 * @return int
	 */
	public function count( int $network_id, array $args = [] ): int {
		global $wpdb;

		$table   = Schema::table( Constants::TABLE_TICKETS );
		$filters = $this->buildWhere( $network_id, $args );
		$where   = $filters['where'];
		$params  = $filters['params'];

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} {$where}",
				...$params
			)
		);
	}

	/**
	 * Find a single ticket by id.
	 *
	 * @param int $id         Ticket id.
	 * @param int $network_id Network id.
	 * @return array<string, mixed>|null
	 */
	public function find( int $id, int $network_id ): ?array {
		global $wpdb;

		$table = Schema::table( Constants::TABLE_TICKETS );
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
	 * Find a ticket by ticket number.
	 *
	 * @param string $ticket_no  Ticket number.
	 * @param int    $network_id Network id.
	 * @return array<string, mixed>|null
	 */
	public function findByTicketNo( string $ticket_no, int $network_id ): ?array {
		global $wpdb;

		$table = Schema::table( Constants::TABLE_TICKETS );
		$row   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE ticket_no = %s AND network_id = %d LIMIT 1",
				$ticket_no,
				$network_id
			),
			ARRAY_A
		);

		return $row ?: null;
	}

	/**
	 * List tickets owned by a specific logged-in member.
	 *
	 * Falls back to requester_email for older member tickets that pre-date the
	 * user_id linkage.
	 *
	 * @param int    $network_id Network id.
	 * @param int    $user_id    Current user id.
	 * @param string $email      Current user email.
	 * @param array  $args       Optional: page, per_page.
	 * @return array<int, array<string, mixed>>
	 */
	public function listForUser( int $network_id, int $user_id, string $email, array $args = array() ): array {
		global $wpdb;

		$table    = Schema::table( Constants::TABLE_TICKETS );
		$per_page = isset( $args['per_page'] ) ? max( 1, (int) $args['per_page'] ) : 20;
		$page     = isset( $args['page'] ) ? max( 1, (int) $args['page'] ) : 1;
		$offset   = ( $page - 1 ) * $per_page;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table}
				 WHERE network_id = %d
				   AND (user_id = %d OR ((user_id IS NULL OR user_id = 0) AND requester_email = %s))
				 ORDER BY updated_at DESC
				 LIMIT %d OFFSET %d",
				$network_id,
				$user_id,
				sanitize_email( $email ),
				$per_page,
				$offset
			),
			ARRAY_A
		);

		return $rows ?: array();
	}

	/**
	 * Insert a new ticket row.
	 *
	 * @param array<string, mixed> $data Column data.
	 * @return int Inserted id or 0 on failure.
	 */
	public function create( array $data ): int {
		global $wpdb;

		$table  = Schema::table( Constants::TABLE_TICKETS );
		$result = $wpdb->insert( $table, $data );

		return $result ? (int) $wpdb->insert_id : 0;
	}

	/**
	 * Update a ticket row.
	 *
	 * @param int                  $id         Ticket id.
	 * @param array<string, mixed> $data       Column data.
	 * @param int                  $network_id Network id.
	 * @return bool
	 */
	public function update( int $id, array $data, int $network_id ): bool {
		global $wpdb;

		$table  = Schema::table( Constants::TABLE_TICKETS );
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
	 * Delete a ticket row.
	 *
	 * @param int $id         Ticket id.
	 * @param int $network_id Network id.
	 * @return bool
	 */
	public function delete( int $id, int $network_id ): bool {
		global $wpdb;

		$table  = Schema::table( Constants::TABLE_TICKETS );
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
	 * Count tickets grouped by status (reporting).
	 *
	 * @param int   $network_id  Network id.
	 * @param array $date_range  Optional: ['from' => 'Y-m-d', 'to' => 'Y-m-d'].
	 * @return array<string, int> Status => count.
	 */
	public function countByStatus( int $network_id, array $date_range = [] ): array {
		global $wpdb;

		$table  = Schema::table( Constants::TABLE_TICKETS );
		$where  = 'WHERE network_id = %d';
		$params = [ $network_id ];

		if ( ! empty( $date_range['from'] ) ) {
			$where   .= ' AND created_at >= %s';
			$params[] = sanitize_text_field( (string) $date_range['from'] ) . ' 00:00:00';
		}

		if ( ! empty( $date_range['to'] ) ) {
			$where   .= ' AND created_at <= %s';
			$params[] = sanitize_text_field( (string) $date_range['to'] ) . ' 23:59:59';
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT status, COUNT(*) AS total FROM {$table} {$where} GROUP BY status",
				...$params
			),
			ARRAY_A
		);

		$counts = [];
		foreach ( $rows ?: [] as $row ) {
			$counts[ (string) $row['status'] ] = (int) $row['total'];
		}

		return $counts;
	}

	/**
	 * Count tickets grouped by priority (reporting).
	 *
	 * @param int   $network_id  Network id.
	 * @param array $date_range  Optional: ['from' => 'Y-m-d', 'to' => 'Y-m-d'].
	 * @return array<string, int> Priority => count.
	 */
	public function countByPriority( int $network_id, array $date_range = [] ): array {
		global $wpdb;

		$table  = Schema::table( Constants::TABLE_TICKETS );
		$where  = 'WHERE network_id = %d';
		$params = [ $network_id ];

		if ( ! empty( $date_range['from'] ) ) {
			$where   .= ' AND created_at >= %s';
			$params[] = sanitize_text_field( (string) $date_range['from'] ) . ' 00:00:00';
		}

		if ( ! empty( $date_range['to'] ) ) {
			$where   .= ' AND created_at <= %s';
			$params[] = sanitize_text_field( (string) $date_range['to'] ) . ' 23:59:59';
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT priority, COUNT(*) AS total FROM {$table} {$where} GROUP BY priority",
				...$params
			),
			ARRAY_A
		);

		$counts = [];
		foreach ( $rows ?: [] as $row ) {
			$counts[ (string) $row['priority'] ] = (int) $row['total'];
		}

		return $counts;
	}

	/**
	 * Count tickets created per day over a date range (reporting).
	 *
	 * @param int    $network_id Network id.
	 * @param string $from       Start date Y-m-d.
	 * @param string $to         End date Y-m-d.
	 * @return array<string, int> Date => count.
	 */
	public function countByDay( int $network_id, string $from, string $to ): array {
		global $wpdb;

		$table = Schema::table( Constants::TABLE_TICKETS );
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE(created_at) AS day, COUNT(*) AS total
				 FROM {$table}
				 WHERE network_id = %d
				   AND created_at >= %s
				   AND created_at <= %s
				 GROUP BY day
				 ORDER BY day ASC",
				$network_id,
				sanitize_text_field( $from ) . ' 00:00:00',
				sanitize_text_field( $to ) . ' 23:59:59'
			),
			ARRAY_A
		);

		$counts = [];
		foreach ( $rows ?: [] as $row ) {
			$counts[ (string) $row['day'] ] = (int) $row['total'];
		}

		return $counts;
	}

	/**
	 * Build the common WHERE clause for list/count queries.
	 *
	 * @param int                  $network_id Network id.
	 * @param array<string, mixed> $args       Query args.
	 * @return array{where:string, params:array<int, mixed>}
	 */
	private function buildWhere( int $network_id, array $args ): array {
		global $wpdb;

		$where  = 'WHERE network_id = %d';
		$params = [ $network_id ];

		if ( ! empty( $args['status'] ) ) {
			$where   .= ' AND status = %s';
			$params[] = sanitize_key( (string) $args['status'] );
		}

		if ( ! empty( $args['priority'] ) ) {
			$where   .= ' AND priority = %s';
			$params[] = sanitize_key( (string) $args['priority'] );
		}

		if ( isset( $args['assigned_to'] ) && '' !== (string) $args['assigned_to'] ) {
			$where   .= ' AND assigned_to = %d';
			$params[] = (int) $args['assigned_to'];
		}

		if ( ! empty( $args['search'] ) ) {
			$like     = '%' . $wpdb->esc_like( sanitize_text_field( (string) $args['search'] ) ) . '%';
			$where   .= ' AND (subject LIKE %s OR requester_email LIKE %s OR ticket_no LIKE %s)';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}

		if ( ! empty( $args['from_date'] ) ) {
			$where   .= ' AND created_at >= %s';
			$params[] = sanitize_text_field( (string) $args['from_date'] ) . ' 00:00:00';
		}

		if ( ! empty( $args['to_date'] ) ) {
			$where   .= ' AND created_at <= %s';
			$params[] = sanitize_text_field( (string) $args['to_date'] ) . ' 23:59:59';
		}

		return [
			'where'  => $where,
			'params' => $params,
		];
	}

	/**
	 * Validate and return a safe ORDER BY column name.
	 *
	 * @param string $column Requested column.
	 * @return string
	 */
	private function safeOrderBy( string $column ): string {
		$allowed = [
			'id', 'ticket_no', 'status', 'priority', 'created_at', 'updated_at',
			'assigned_to', 'requester_email', 'subject',
		];

		return in_array( $column, $allowed, true ) ? $column : 'created_at';
	}
}
