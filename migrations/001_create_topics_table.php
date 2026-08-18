<?php
/**
 * @package WP Helpdesk
 */

return new class {
	public function up(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table            = $wpdb->base_prefix . 'hd_topics';
		$charset_collate  = $wpdb->get_charset_collate();
		$sql              = "CREATE TABLE {$table} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			network_id bigint(20) NOT NULL DEFAULT 1,
			slug varchar(190) NOT NULL,
			title varchar(255) NOT NULL,
			description text NULL,
			is_final tinyint(1) NOT NULL DEFAULT 0,
			is_active tinyint(1) NOT NULL DEFAULT 1,
			sort_order int NOT NULL DEFAULT 0,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY slug_network (slug, network_id)
		) {$charset_collate};";

		dbDelta( $sql );
	}
};
