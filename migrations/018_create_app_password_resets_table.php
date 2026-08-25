<?php
/**
 * @package WP Helpdesk
 */

return new class {
	public function up(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table           = $wpdb->base_prefix . 'hd_app_password_resets';
		$charset_collate = $wpdb->get_charset_collate();
		$sql             = "CREATE TABLE {$table} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			email varchar(255) NOT NULL,
			reset_code varchar(8) NOT NULL,
			reset_token varchar(64) NULL,
			token_expires_at datetime NULL,
			attempts int(11) NOT NULL DEFAULT 0,
			last_attempt_at datetime NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			expires_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY email_idx (email),
			KEY reset_code_idx (reset_code),
			KEY reset_token_idx (reset_token)
		) {$charset_collate};";

		dbDelta( $sql );
	}
};
