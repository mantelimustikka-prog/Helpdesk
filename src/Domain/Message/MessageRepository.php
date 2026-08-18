<?php
/**
 * @package WP Helpdesk
 */

namespace WPHelpdesk\Domain\Message;

use WPHelpdesk\Infrastructure\Database\Schema;
use WPHelpdesk\Support\Constants;

class MessageRepository {

	/** Allowed message author types. */
	public const AUTHOR_TYPES = [ 'guest', 'member', 'agent', 'system' ];

	/**
	 * List messages for a ticket with pagination.
	 *
	 * @param int   $ticket_id Ticket id.
	 * @param array $args      Optional: page, per_page, author_type, is_internal.
	 * @return array<int, array<string, mixed>>
	 */
	public function list( int $ticket_id, array $args = [] ): array {
		global $wpdb;

		$table    = Schema::table( Constants::TABLE_TICKET_MESSAGES );
		$per_page = isset( $args['per_page'] ) ? max( 1, (int) $args['per_page'] ) : 50;
		$page     = isset( $args['page'] ) ? max( 1, (int) $args['page'] ) : 1;
		$offset   = ( $page - 1 ) * $per_page;
		$where    = 'WHERE ticket_id = %d';
		$params   = [ $ticket_id ];

		if ( ! empty( $args['author_type'] ) && in_array( $args['author_type'], self::AUTHOR_TYPES, true ) ) {
			$where   .= ' AND author_type = %s';
			$params[] = (string) $args['author_type'];
		}

		if ( isset( $args['is_internal'] ) && '' !== (string) $args['is_internal'] ) {
			$where   .= ' AND is_internal = %d';
			$params[] = (int) $args['is_internal'];
		}

		$params[] = $per_page;
		$params[] = $offset;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} {$where} ORDER BY created_at ASC LIMIT %d OFFSET %d",
				...$params
			),
			ARRAY_A
		);

		return $rows ?: [];
	}

	/**
	 * Count messages for a ticket.
	 *
	 * @param int   $ticket_id Ticket id.
	 * @param array $args      Optional filters.
	 * @return int
	 */
	public function count( int $ticket_id, array $args = [] ): int {
		global $wpdb;

		$table  = Schema::table( Constants::TABLE_TICKET_MESSAGES );
		$where  = 'WHERE ticket_id = %d';
		$params = [ $ticket_id ];

		if ( isset( $args['is_internal'] ) && '' !== (string) $args['is_internal'] ) {
			$where   .= ' AND is_internal = %d';
			$params[] = (int) $args['is_internal'];
		}

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} {$where}",
				...$params
			)
		);
	}

	/**
	 * Find a single message by id.
	 *
	 * @param int $id Message id.
	 * @return array<string, mixed>|null
	 */
	public function find( int $id ): ?array {
		global $wpdb;

		$table = Schema::table( Constants::TABLE_TICKET_MESSAGES );
		$row   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d LIMIT 1",
				$id
			),
			ARRAY_A
		);

		return $row ?: null;
	}

	/**
	 * Insert a new message.
	 *
	 * @param array<string, mixed> $data Column data.
	 * @return int Inserted id or 0 on failure.
	 */
	public function create( array $data ): int {
		global $wpdb;

		$table  = Schema::table( Constants::TABLE_TICKET_MESSAGES );
		$result = $wpdb->insert( $table, $data );

		return $result ? (int) $wpdb->insert_id : 0;
	}

	/**
	 * Delete a message by id.
	 *
	 * @param int $id Message id.
	 * @return bool
	 */
	public function delete( int $id ): bool {
		global $wpdb;

		$table  = Schema::table( Constants::TABLE_TICKET_MESSAGES );
		$result = $wpdb->delete( $table, [ 'id' => $id ] );

		return false !== $result && $result > 0;
	}
}
