<?php
/**
 * @package WP Helpdesk
 */

namespace WPHelpdesk\Infrastructure\Security;

class Capabilities {
	/**
	 * Register helpdesk capabilities.
	 *
	 * @return void
	 */
	public static function register(): void {
		$caps = require HD_PATH . 'config/capabilities.php';

		if ( is_multisite() ) {
			switch_to_blog( get_main_site_id() );
		}

		$role = get_role( 'administrator' );

		if ( $role ) {
			foreach ( $caps as $cap ) {
				$role->add_cap( $cap );
			}
		}

		if ( is_multisite() ) {
			restore_current_blog();
		}
	}

	/**
	 * Remove helpdesk capabilities.
	 *
	 * @return void
	 */
	public static function remove(): void {
		$caps = require HD_PATH . 'config/capabilities.php';

		if ( is_multisite() ) {
			switch_to_blog( get_main_site_id() );
		}

		$role = get_role( 'administrator' );

		if ( $role ) {
			foreach ( $caps as $cap ) {
				$role->remove_cap( $cap );
			}
		}

		if ( is_multisite() ) {
			restore_current_blog();
		}
	}
}
