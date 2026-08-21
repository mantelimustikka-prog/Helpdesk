<?php
/**
 * @package WP Helpdesk
 *
 * Migration 015 – Add guest_token to tickets table.
 *
 * Stores a cryptographically-random token for guest-created tickets so that
 * the guest can later return to the ticket thread via a secure URL.
 */

return new class {
	public function up(): void {
		global $wpdb;

		$table = $wpdb->base_prefix . 'hd_tickets';
		$cols  = $wpdb->get_col( "DESCRIBE {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		if ( ! in_array( 'guest_token', $cols, true ) ) {
			$wpdb->query( "ALTER TABLE {$table} ADD COLUMN guest_token VARCHAR(64) NULL DEFAULT NULL AFTER order_relation" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->query( "ALTER TABLE {$table} ADD UNIQUE KEY guest_token_idx (guest_token)" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}
	}
};
