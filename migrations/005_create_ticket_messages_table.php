<?php
/**
 * @package WP Helpdesk
 */

return new class {
	public function up(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table           = $wpdb->base_prefix . 'hd_ticket_messages';
		$charset_collate = $wpdb->get_charset_collate();
		$sql             = "CREATE TABLE {$table} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			ticket_id bigint(20) UNSIGNED NOT NULL,
			author_user_id bigint(20) UNSIGNED NULL,
			author_type enum('guest','member','agent','system') NOT NULL,
			body longtext NOT NULL,
			is_internal tinyint(1) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY ticket_id_idx (ticket_id)
		) {$charset_collate};";

		dbDelta( $sql );
	}
};
