<?php
/**
 * @package WP Helpdesk
 */

namespace WPHelpdesk\Bootstrap;

class Deactivator {
	/**
	 * Deactivate the plugin.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		wp_clear_scheduled_hook( 'hd_process_notifications' );
		wp_clear_scheduled_hook( 'hd_cleanup_form_sessions' );
		wp_clear_scheduled_hook( 'hd_cleanup_rate_limits' );
	}
}
