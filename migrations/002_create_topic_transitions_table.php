<?php
/**
 * @package WP Helpdesk
 */

return new class {
	public function up(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table           = $wpdb->base_prefix . 'hd_topic_transitions';
		$charset_collate = $wpdb->get_charset_collate();
		$sql             = "CREATE TABLE {$table} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			network_id bigint(20) NOT NULL DEFAULT 1,
			from_topic_id bigint(20) UNSIGNED NOT NULL,
			to_topic_id bigint(20) UNSIGNED NOT NULL,
			label varchar(255) NOT NULL,
			condition_type varchar(50) NOT NULL DEFAULT 'always',
			condition_value text NULL,
			sort_order int NOT NULL DEFAULT 0,
			is_active tinyint(1) NOT NULL DEFAULT 1,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY from_topic (from_topic_id)
		) {$charset_collate};";

		dbDelta( $sql );
	}
};
