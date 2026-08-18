<?php
/**
 * @package WP Helpdesk
 */

return new class {
	public function up(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table           = $wpdb->base_prefix . 'hd_device_tokens';
		$charset_collate = $wpdb->get_charset_collate();
		$sql             = "CREATE TABLE {$table} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id bigint(20) UNSIGNED NOT NULL,
			device_token varchar(255) NOT NULL,
			platform varchar(20) NOT NULL DEFAULT 'android',
			app_version varchar(20) NULL,
			is_active tinyint(1) NOT NULL DEFAULT 1,
			last_seen_at datetime NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY device_token (device_token),
			KEY user_id_idx (user_id)
		) {$charset_collate};";

		dbDelta( $sql );
	}
};
