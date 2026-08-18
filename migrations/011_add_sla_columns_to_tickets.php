<?php
/**
 * @package WP Helpdesk
 */

return new class {
	public function up(): void {
		global $wpdb;

		$table = $wpdb->base_prefix . 'hd_tickets';

		// Add SLA deadline and breach-tracking columns.  dbDelta handles the
		// "already exists" case gracefully so this migration is idempotent.
		$wpdb->query( "ALTER TABLE {$table} ADD COLUMN IF NOT EXISTS sla_first_response_due datetime NULL DEFAULT NULL" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query( "ALTER TABLE {$table} ADD COLUMN IF NOT EXISTS sla_resolution_due datetime NULL DEFAULT NULL" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query( "ALTER TABLE {$table} ADD COLUMN IF NOT EXISTS sla_first_response_breached tinyint(1) NOT NULL DEFAULT 0" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query( "ALTER TABLE {$table} ADD COLUMN IF NOT EXISTS sla_resolution_breached tinyint(1) NOT NULL DEFAULT 0" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}
};
