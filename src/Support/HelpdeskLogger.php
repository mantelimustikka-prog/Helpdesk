<?php
/**
 * @package WP Helpdesk
 */

namespace WPHelpdesk\Support;

/**
 * Lightweight structured logger for the WP Helpdesk plugin.
 *
 * Writes JSON-lines to wp-content/uploads/wp-helpdesk-logs/helpdesk-debug.log
 * when the hd_api_log_requests site option is enabled.  Every log entry is a
 * single JSON object on its own line so the admin log viewer can parse and
 * display entries without loading the entire file at once.
 */
class HelpdeskLogger {
	public const LOG_DIR_RELATIVE  = 'wp-helpdesk-logs';
	public const LOG_FILE_NAME     = 'helpdesk-debug.log';
	public const MAX_FILE_BYTES    = 5 * 1024 * 1024; // 5 MB rotate threshold.

	/**
	 * Resolve the absolute path to the log directory.
	 *
	 * Uses wp_upload_dir() when available so the path honours custom UPLOADS
	 * constants; falls back to ABSPATH for unit-test environments.
	 *
	 * @return string
	 */
	public static function logDir(): string {
		if ( function_exists( 'wp_upload_dir' ) ) {
			$upload = wp_upload_dir();
			$base   = trailingslashit( (string) ( $upload['basedir'] ?? '' ) );
		} else {
			$base = defined( 'ABSPATH' ) ? trailingslashit( ABSPATH ) . 'wp-content/uploads/' : sys_get_temp_dir() . '/';
		}

		return rtrim( $base, '/' ) . '/' . self::LOG_DIR_RELATIVE;
	}

	/**
	 * Resolve the absolute path to the log file.
	 *
	 * @return string
	 */
	public static function logFile(): string {
		return self::logDir() . '/' . self::LOG_FILE_NAME;
	}

	/**
	 * Return true when debug logging is enabled via site option.
	 *
	 * @return bool
	 */
	public static function isEnabled(): bool {
		return (bool) get_site_option( Constants::OPTION_API_LOG_REQUESTS, false );
	}

	/**
	 * Write a structured log entry.
	 *
	 * @param string               $action  Short action/route identifier.
	 * @param array<string, mixed> $context Contextual data for the entry.
	 * @return void
	 */
	public static function log( string $action, array $context = array() ): void {
		if ( ! self::isEnabled() ) {
			return;
		}

		$entry = array_merge(
			array(
				'timestamp' => gmdate( 'Y-m-d\TH:i:s\Z' ),
				'action'    => $action,
			),
			$context
		);

		$dir = self::logDir();
		if ( ! self::ensureLogDir( $dir ) ) {
			return;
		}

		$file = self::logFile();

		// Rotate the file if it has grown beyond the threshold.
		if ( file_exists( $file ) && filesize( $file ) >= self::MAX_FILE_BYTES ) {
			rename( $file, $file . '.1' );
		}

		$line = wp_json_encode( $entry );
		if ( false === $line ) {
			return;
		}

		file_put_contents( $file, $line . "\n", FILE_APPEND | LOCK_EX ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
	}

	/**
	 * Ensure the log directory exists and is protected by an .htaccess and
	 * index file so direct web browsing is blocked.
	 *
	 * @param string $dir Absolute directory path.
	 * @return bool True on success.
	 */
	public static function ensureLogDir( string $dir ): bool {
		if ( ! file_exists( $dir ) ) {
			if ( ! mkdir( $dir, 0755, true ) && ! is_dir( $dir ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir
				return false;
			}

			file_put_contents( $dir . '/.htaccess', "Order Deny,Allow\nDeny from all\n", LOCK_EX ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $dir . '/index.php', "<?php // Silence is golden.\n", LOCK_EX ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}

		return is_dir( $dir );
	}

	/**
	 * Read log entries from the log file.
	 *
	 * Returns entries newest-first so the admin viewer can paginate without
	 * loading the entire file.  Each element is an associative array decoded
	 * from a JSON line; lines that cannot be decoded are silently skipped.
	 *
	 * @param int $limit  Maximum number of entries to return (0 = all).
	 * @return array<int, array<string, mixed>>
	 */
	public static function readEntries( int $limit = 200 ): array {
		$file = self::logFile();
		if ( ! file_exists( $file ) || ! is_readable( $file ) ) {
			return array();
		}

		$raw     = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$lines   = array_filter( explode( "\n", (string) $raw ) );
		$entries = array();

		foreach ( $lines as $line ) {
			$decoded = json_decode( trim( $line ), true );
			if ( is_array( $decoded ) ) {
				$entries[] = $decoded;
			}
		}

		// Newest first.
		$entries = array_reverse( $entries );

		if ( $limit > 0 ) {
			$entries = array_slice( $entries, 0, $limit );
		}

		return $entries;
	}

	/**
	 * Delete (truncate) the current log file.
	 *
	 * @return bool
	 */
	public static function clearLog(): bool {
		$file = self::logFile();
		if ( ! file_exists( $file ) ) {
			return true;
		}

		return false !== file_put_contents( $file, '', LOCK_EX ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
	}
}
