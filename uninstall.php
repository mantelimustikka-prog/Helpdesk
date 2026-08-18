<?php
/**
 * @package WP Helpdesk
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$autoload_path = __DIR__ . '/vendor/autoload.php';

if ( file_exists( $autoload_path ) ) {
	require_once $autoload_path;
} else {
	spl_autoload_register(
		static function ( string $class ): void {
			$prefix = 'WPHelpdesk\\';

			if ( 0 !== strpos( $class, $prefix ) ) {
				return;
			}

			$relative_class = substr( $class, strlen( $prefix ) );
			$file           = __DIR__ . '/src/' . str_replace( '\\', '/', $relative_class ) . '.php';

			if ( file_exists( $file ) ) {
				require_once $file;
			}
		}
	);
}

\WPHelpdesk\Bootstrap\Uninstaller::uninstall();
