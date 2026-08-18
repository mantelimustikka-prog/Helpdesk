<?php
/**
 * @package WP Helpdesk
 */

namespace WPHelpdesk\Bootstrap;

use WPHelpdesk\Infrastructure\Database\Migrator;
use WPHelpdesk\Infrastructure\Security\Capabilities;
use WPHelpdesk\Domain\SLA\SlaService;
use WPHelpdesk\Domain\Privacy\RetentionService;
use WPHelpdesk\Support\Constants;
use WPHelpdesk\Bootstrap\PageBootstrapper;

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

		SlaService::scheduleCron();
		RetentionService::scheduleCron();

		// Bootstrap frontend pages (per-site; iterate on network activation).
		if ( $network_wide && is_multisite() ) {
			$sites = get_sites( array( 'number' => 0 ) );
			foreach ( $sites as $site ) {
				switch_to_blog( (int) $site->blog_id );
				PageBootstrapper::ensurePages();
				// Stamp rewrite version to prevent a redundant flush on first boot
				// (activation already flushed above via flush_rewrite_rules()).
				update_option( Constants::OPTION_REWRITE_VERSION, Constants::REWRITE_VERSION );
				restore_current_blog();
			}
		} else {
			PageBootstrapper::ensurePages();
			update_option( Constants::OPTION_REWRITE_VERSION, Constants::REWRITE_VERSION );
		}

		// Flush rewrite rules once at activation time.
		flush_rewrite_rules( false );
	}
}
