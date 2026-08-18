<?php
/**
 * @package WP Helpdesk
 */

namespace WPHelpdesk\Infrastructure;

class Logger {
	/**
	 * Log an informational message.
	 *
	 * @param string $message Message to log.
	 * @return void
	 */
	public function info( string $message ): void {
		error_log( '[WP Helpdesk] ' . $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	}

	/**
	 * Log an error message.
	 *
	 * @param string $message Message to log.
	 * @return void
	 */
	public function error( string $message ): void {
		error_log( '[WP Helpdesk][ERROR] ' . $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	}
}
