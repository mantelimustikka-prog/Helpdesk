<?php
/**
 * @package WP Helpdesk
 *
 * Migration 014 – Add order_relation to tickets table.
 *
 * Stores the required order relation for each ticket:
 *  - 'not_order_related' – the request has no associated order
 *  - a WooCommerce order number string – the related order
 */

return new class {
	public function up(): void {
		global $wpdb;

		$table = $wpdb->base_prefix . 'hd_tickets';
		$cols  = $wpdb->get_col( "DESCRIBE {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		if ( ! in_array( 'order_relation', $cols, true ) ) {
			$wpdb->query( "ALTER TABLE {$table} ADD COLUMN order_relation VARCHAR(255) NULL DEFAULT NULL AFTER topic_path_json" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}
	}
};
