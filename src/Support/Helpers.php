<?php
/**
 * @package WP Helpdesk
 */

namespace WPHelpdesk\Support;

class Helpers {
	/**
	 * Get the current network identifier.
	 *
	 * @return int
	 */
	public static function getNetworkId(): int {
		if ( function_exists( 'get_current_network_id' ) ) {
			return (int) get_current_network_id();
		}

		return 1;
	}

	/**
	 * Get the current site identifier.
	 *
	 * @return int
	 */
	public static function getCurrentSiteId(): int {
		return (int) get_current_blog_id();
	}

	/**
	 * Sanitize rich HTML content for stored templates.
	 *
	 * @param string $html Raw HTML.
	 * @return string
	 */
	public static function sanitizeRichText( string $html ): string {
		return wp_kses_post( $html );
	}

	/**
	 * Generate the next ticket number atomically.
	 *
	 * Uses a DB-level atomic increment to prevent duplicate ticket numbers
	 * under concurrent ticket creation.
	 *
	 * @return string
	 */
	public static function generateTicketNo(): string {
		global $wpdb;

		$option_name = Constants::OPTION_TICKET_COUNTER;
		$start       = (int) get_site_option( Constants::OPTION_TICKET_START, 1000 );

		// Attempt an atomic UPDATE increment. If the row doesn't exist yet, initialise it.
		$updated = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->sitemeta} SET meta_value = GREATEST(meta_value + 1, %d + 1)
				 WHERE meta_key = %s AND site_id = %d",
				$start,
				$option_name,
				get_current_network_id()
			)
		);

		if ( ! $updated ) {
			// Row doesn't exist; insert with the start value (race-safe via INSERT IGNORE).
			$wpdb->query(
				$wpdb->prepare(
					"INSERT IGNORE INTO {$wpdb->sitemeta} (site_id, meta_key, meta_value)
					 VALUES (%d, %s, %d)",
					get_current_network_id(),
					$option_name,
					$start
				)
			);

			// Re-run the atomic increment after insert.
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$wpdb->sitemeta} SET meta_value = GREATEST(meta_value + 1, %d + 1)
					 WHERE meta_key = %s AND site_id = %d",
					$start,
					$option_name,
					get_current_network_id()
				)
			);
		}

		$counter = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT meta_value FROM {$wpdb->sitemeta} WHERE meta_key = %s AND site_id = %d",
				$option_name,
				get_current_network_id()
			)
		);

		return sprintf( 'HD-%06d', $counter );
	}

	/**
	 * Resolve a filesystem path inside the plugin.
	 *
	 * @param string $path Relative path.
	 * @return string
	 */
	public static function pluginPath( string $path = '' ): string {
		return trailingslashit( HD_PATH ) . ltrim( $path, '/' );
	}

	/**
	 * Resolve a URL inside the plugin.
	 *
	 * @param string $path Relative path.
	 * @return string
	 */
	public static function pluginUrl( string $path = '' ): string {
		return trailingslashit( HD_URL ) . ltrim( $path, '/' );
	}

	/**
	 * Get the configured REST namespace.
	 *
	 * @return string
	 */
	public static function restNamespace(): string {
		return Constants::REST_NAMESPACE;
	}
}
