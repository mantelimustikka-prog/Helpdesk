<?php
/**
 * @package WP Helpdesk
 */

return new class {
	public function up(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table           = $wpdb->base_prefix . 'hd_tickets';
		$charset_collate = $wpdb->get_charset_collate();
		$sql             = "CREATE TABLE {$table} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			network_id bigint(20) NOT NULL DEFAULT 1,
			site_id bigint(20) NOT NULL DEFAULT 1,
			ticket_no varchar(30) NOT NULL,
			user_id bigint(20) UNSIGNED NULL,
			requester_name varchar(190) NOT NULL,
			requester_email varchar(190) NOT NULL,
			requester_phone varchar(50) NOT NULL,
			subject varchar(255) NOT NULL,
			topic_path_json longtext NULL,
			status enum('new','triaged','waiting_customer','in_progress','resolved','closed') NOT NULL DEFAULT 'new',
			priority enum('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
			assigned_to bigint(20) UNSIGNED NULL,
			first_response_due_at datetime NULL,
			resolve_due_at datetime NULL,
			closed_at datetime NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY ticket_no (ticket_no),
			KEY status_idx (status),
			KEY assigned_idx (assigned_to),
			KEY network_site (network_id, site_id)
		) {$charset_collate};";

		dbDelta( $sql );
	}
};
