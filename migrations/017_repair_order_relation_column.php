<?php
/**
 * @package WP Helpdesk
 *
 * Migration 017 – Repair missing order_relation in tickets table.
 */

return new class {
	public function up(): void {
		global $wpdb;

		$table = \WPHelpdesk\Infrastructure\Database\Schema::table( \WPHelpdesk\Support\Constants::TABLE_TICKETS );
		$cols  = $wpdb->get_col( "DESCRIBE {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		if ( ! in_array( 'order_relation', (array) $cols, true ) ) {
			$wpdb->query( "ALTER TABLE {$table} ADD COLUMN order_relation VARCHAR(255) NULL DEFAULT NULL AFTER topic_path_json" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}
	}
};
