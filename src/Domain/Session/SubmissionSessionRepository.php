<?php
/**
 * @package WP Helpdesk
 */

namespace WPHelpdesk\Domain\Session;

use WPHelpdesk\Infrastructure\Database\Schema;
use WPHelpdesk\Support\Constants;

class SubmissionSessionRepository {

	/**
	 * Find a session by token.
	 *
	 * @param string $token Session token.
	 * @return array<string, mixed>|null
	 */
	public function findByToken( string $token ): ?array {
		global $wpdb;

		$table = Schema::table( Constants::TABLE_FORM_SESSIONS );
		$row   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE session_token = %s LIMIT 1",
				$token
			),
			ARRAY_A
		);

		return $row ?: null;
	}

	/**
	 * Find a session by id.
	 *
	 * @param int $id Session id.
	 * @return array<string, mixed>|null
	 */
	public function find( int $id ): ?array {
		global $wpdb;

		$table = Schema::table( Constants::TABLE_FORM_SESSIONS );
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
	 * Insert a new session row.
	 *
	 * @param array<string, mixed> $data Column data.
	 * @return int Inserted id or 0 on failure.
	 */
	public function create( array $data ): int {
		global $wpdb;

		$table  = Schema::table( Constants::TABLE_FORM_SESSIONS );
		$result = $wpdb->insert( $table, $data );

		return $result ? (int) $wpdb->insert_id : 0;
	}

	/**
	 * Update a session row by token.
	 *
	 * @param string               $token Session token.
	 * @param array<string, mixed> $data  Column data.
	 * @return bool
	 */
	public function updateByToken( string $token, array $data ): bool {
		global $wpdb;

		$table  = Schema::table( Constants::TABLE_FORM_SESSIONS );
		$result = $wpdb->update(
			$table,
			$data,
			[ 'session_token' => $token ]
		);

		return false !== $result;
	}

	/**
	 * Delete a session by token.
	 *
	 * @param string $token Session token.
	 * @return bool
	 */
	public function deleteByToken( string $token ): bool {
		global $wpdb;

		$table  = Schema::table( Constants::TABLE_FORM_SESSIONS );
		$result = $wpdb->delete( $table, [ 'session_token' => $token ] );

		return false !== $result && $result > 0;
	}

	/**
	 * Delete all expired sessions.
	 *
	 * @return int Number of deleted rows.
	 */
	public function deleteExpired(): int {
		global $wpdb;

		$table  = Schema::table( Constants::TABLE_FORM_SESSIONS );
		$result = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE expires_at < %s",
				current_time( 'mysql' )
			)
		);

		return false !== $result ? (int) $result : 0;
	}
}
