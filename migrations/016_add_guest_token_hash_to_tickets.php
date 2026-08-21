<?php
/**
 * @package WP Helpdesk
 *
 * Migration 016 – Store guest access token as a hash.
 */

return new class {
	public function up(): void {
		global $wpdb;

		$table = \WPHelpdesk\Infrastructure\Database\Schema::table( \WPHelpdesk\Support\Constants::TABLE_TICKETS );
		$cols  = $wpdb->get_col( "DESCRIBE {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		if ( ! in_array( 'guest_token_hash', $cols, true ) ) {
			$wpdb->query( "ALTER TABLE {$table} ADD COLUMN guest_token_hash VARCHAR(64) NULL DEFAULT NULL AFTER guest_token" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		if ( in_array( 'guest_token', $cols, true ) ) {
			$wpdb->query( "UPDATE {$table} SET guest_token_hash = SHA2(guest_token, 256), guest_token = NULL WHERE guest_token IS NOT NULL AND guest_token <> '' AND (guest_token_hash IS NULL OR guest_token_hash = '')" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		$indexes        = $wpdb->get_results( "SHOW INDEX FROM {$table}", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$has_hash_index = false;
		foreach ( (array) $indexes as $index ) {
			if ( isset( $index['Key_name'] ) && 'guest_token_hash_idx' === $index['Key_name'] ) {
				$has_hash_index = true;
				break;
			}
		}

		if ( ! $has_hash_index ) {
			$wpdb->query( "ALTER TABLE {$table} ADD UNIQUE INDEX guest_token_hash_idx (guest_token_hash)" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}
	}
};
