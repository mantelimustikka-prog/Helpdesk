<?php
/**
 * @package WP Helpdesk
 */

namespace WPHelpdesk\Bootstrap;

use WPHelpdesk\Support\Constants;

class Uninstaller {
	/**
	 * Remove plugin data.
	 *
	 * @return void
	 */
	public static function uninstall(): void {
		global $wpdb;

		$table_like = $wpdb->esc_like( $wpdb->base_prefix . 'hd_' ) . '%';
		$tables     = $wpdb->get_col(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$table_like
			)
		);

		if ( ! empty( $tables ) ) {
			foreach ( $tables as $table ) {
				$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			}
		}

		$option_keys = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT meta_key FROM {$wpdb->sitemeta} WHERE meta_key LIKE %s",
				$wpdb->esc_like( 'hd_' ) . '%'
			)
		);

		foreach ( $option_keys as $option_key ) {
			delete_site_option( $option_key );
		}

		foreach ( Constants::optionKeys() as $option_key ) {
			delete_site_option( $option_key );
		}
	}
}
