<?php
/**
 * @package WP Helpdesk
 *
 * Plugin Name: WP Helpdesk
 * Description: Network multisite helpdesk plugin for WordPress with REST APIs, notifications, attachments, and Android admin app support.
 * Version: 1.0.0
 * Network: true
 * Requires PHP: 8.1
 * Author: GitHub Copilot
 * Text Domain: wp-helpdesk
 */

defined( 'ABSPATH' ) || exit;

define( 'HD_VERSION', '1.0.0' );
define( 'HD_PATH', plugin_dir_path( __FILE__ ) );
define( 'HD_URL', plugin_dir_url( __FILE__ ) );
define( 'HD_BASENAME', plugin_basename( __FILE__ ) );

if ( file_exists( HD_PATH . 'vendor/autoload.php' ) ) {
	require_once HD_PATH . 'vendor/autoload.php';
} else {
	spl_autoload_register(
		static function ( string $class ): void {
			$prefix = 'WPHelpdesk\\';

			if ( 0 !== strpos( $class, $prefix ) ) {
				return;
			}

			$relative_class = substr( $class, strlen( $prefix ) );
			$file           = HD_PATH . 'src/' . str_replace( '\\', '/', $relative_class ) . '.php';

			if ( file_exists( $file ) ) {
				require_once $file;
			}
		}
	);
}

add_action(
	'plugins_loaded',
	static function (): void {
		$plugin = new \WPHelpdesk\Bootstrap\Plugin();
		$plugin->boot();
	}
);

register_activation_hook( __FILE__, array( \WPHelpdesk\Bootstrap\Activator::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( \WPHelpdesk\Bootstrap\Deactivator::class, 'deactivate' ) );
register_uninstall_hook( __FILE__, 'wp_helpdesk_uninstall' );

/**
 * Proxy uninstall execution to uninstall.php.
 *
 * @return void
 */
function wp_helpdesk_uninstall(): void {
	if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
		define( 'WP_UNINSTALL_PLUGIN', HD_BASENAME );
	}

	require HD_PATH . 'uninstall.php';
}
