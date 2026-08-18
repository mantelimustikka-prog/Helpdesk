<?php
/**
 * @package WP Helpdesk
 */

return new class {
	public function up(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table           = $wpdb->base_prefix . 'hd_form_sessions';
		$charset_collate = $wpdb->get_charset_collate();
		$sql             = "CREATE TABLE {$table} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			network_id bigint(20) NOT NULL DEFAULT 1,
			site_id bigint(20) NOT NULL DEFAULT 1,
			session_token varchar(64) NOT NULL,
			user_id bigint(20) UNSIGNED NULL,
			form_type enum('guest','member') NOT NULL,
			current_topic_id bigint(20) UNSIGNED NULL,
			step_index int NOT NULL DEFAULT 0,
			payload_json longtext NULL,
			expires_at datetime NOT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY session_token (session_token)
		) {$charset_collate};";

		dbDelta( $sql );
	}
};
