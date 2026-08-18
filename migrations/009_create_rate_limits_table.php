<?php
/**
 * @package WP Helpdesk
 */

return new class {
	public function up(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table           = $wpdb->base_prefix . 'hd_rate_limits';
		$charset_collate = $wpdb->get_charset_collate();
		$sql             = "CREATE TABLE {$table} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			key_hash varchar(64) NOT NULL,
			window_start datetime NOT NULL,
			hits int NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			UNIQUE KEY key_hash (key_hash)
		) {$charset_collate};";

		dbDelta( $sql );
	}
};
