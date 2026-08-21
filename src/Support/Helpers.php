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
		$start       = (int) get_site_option(
			Constants::OPTION_GENERAL_TICKET_NUMBER_START,
			(int) get_site_option( Constants::OPTION_TICKET_START, 1000 )
		);
		$increment   = max( 1, (int) get_site_option( Constants::OPTION_GENERAL_TICKET_NUMBER_INC, 1 ) );
		$network_id  = get_current_network_id();

		// Attempt an atomic UPDATE increment. The stored counter tracks the next
		// available number, while the issued ticket number is the value prior to
		// the increment.
		$updated = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->sitemeta}
				 SET meta_value = LAST_INSERT_ID(GREATEST(CAST(meta_value AS UNSIGNED), %d) + %d)
				 WHERE meta_key = %s AND site_id = %d",
				$start,
				$increment,
				$option_name,
				$network_id
			)
		);

		if ( ! $updated ) {
			// Row doesn't exist; insert with the start value (race-safe via INSERT IGNORE).
			$wpdb->query(
				$wpdb->prepare(
					"INSERT IGNORE INTO {$wpdb->sitemeta} (site_id, meta_key, meta_value)
					 VALUES (%d, %s, %d)",
					$network_id,
					$option_name,
					$start
				)
			);

			// Re-run the atomic increment after insert.
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$wpdb->sitemeta}
					 SET meta_value = LAST_INSERT_ID(GREATEST(CAST(meta_value AS UNSIGNED), %d) + %d)
					 WHERE meta_key = %s AND site_id = %d",
					$start,
					$increment,
					$option_name,
					$network_id
				)
			);
		}

		$next_counter  = (int) $wpdb->get_var( 'SELECT LAST_INSERT_ID()' );
		$ticket_number = max( $start, $next_counter - $increment );

		return sprintf( 'HD-%06d', $ticket_number );
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

	/**
	 * Hash a guest access token for persistent storage and lookup.
	 *
	 * @param string $guest_token Raw guest access token.
	 * @return string
	 */
	public static function hashGuestToken( string $guest_token ): string {
		return hash( 'sha256', $guest_token );
	}
}
