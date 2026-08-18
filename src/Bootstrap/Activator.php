<?php
/**
 * @package WP Helpdesk
 */

namespace WPHelpdesk\Bootstrap;

use WPHelpdesk\Infrastructure\Database\Migrator;
use WPHelpdesk\Infrastructure\Security\Capabilities;
use WPHelpdesk\Support\Constants;

class Activator {
	/**
	 * Activate the plugin.
	 *
	 * @param bool $network_wide Whether activation is network-wide.
	 * @return void
	 */
	public static function activate( bool $network_wide ): void {
		Migrator::runAll();
		Capabilities::register();

		$defaults = require HD_PATH . 'config/defaults.php';

		foreach ( $defaults as $key => $value ) {
			$option_key = 'hd_' . $key;
			if ( false === get_site_option( $option_key, false ) ) {
				update_site_option( $option_key, $value );
			}
		}

		if ( false === get_site_option( Constants::OPTION_DB_VERSION, false ) ) {
			update_site_option( Constants::OPTION_DB_VERSION, HD_VERSION );
		}

		if ( ! $network_wide && is_multisite() ) {
			update_site_option( 'hd_last_non_network_activation', gmdate( 'Y-m-d H:i:s' ) );
		}
	}
}
