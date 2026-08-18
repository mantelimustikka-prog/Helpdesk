<?php
/**
 * @package WP Helpdesk
 */

return new class {
	public function up(): void {
		global $wpdb;

		$table = $wpdb->base_prefix . 'hd_form_sessions';

		// Add reset_counter column if it does not already exist.
		$column_exists = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s',
				DB_NAME,
				$table,
				'reset_counter'
			)
		);

		if ( ! $column_exists ) {
			$wpdb->query(
				"ALTER TABLE {$table} ADD COLUMN reset_counter int NOT NULL DEFAULT 0 AFTER step_index"
			);
		}
	}
};
