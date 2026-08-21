<?php
/**
 * @package WP Helpdesk
 *
 * Migration 016 – Store guest access token as a hash.
 */

return new class {
	public function up(): void {
		global $wpdb;

		$table = $wpdb->base_prefix . 'hd_tickets';
		$cols  = $wpdb->get_col( "DESCRIBE {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		if ( ! in_array( 'guest_token_hash', $cols, true ) ) {
			$wpdb->query( "ALTER TABLE {$table} ADD COLUMN guest_token_hash VARCHAR(64) NULL DEFAULT NULL AFTER guest_token" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->query( "ALTER TABLE {$table} ADD UNIQUE KEY guest_token_hash_idx (guest_token_hash)" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		if ( in_array( 'guest_token', $cols, true ) ) {
			$wpdb->query( "UPDATE {$table} SET guest_token_hash = SHA2(guest_token, 256) WHERE guest_token IS NOT NULL AND guest_token <> '' AND (guest_token_hash IS NULL OR guest_token_hash = '')" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->query( "UPDATE {$table} SET guest_token = NULL WHERE guest_token IS NOT NULL AND guest_token <> ''" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}
	}
};
