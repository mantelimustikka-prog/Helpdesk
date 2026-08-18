<?php
/**
 * @package WP Helpdesk
 */

namespace WPHelpdesk\Infrastructure\Database;

use WPHelpdesk\Support\Constants;

class Schema {
	/**
	 * Resolve a fully-qualified helpdesk table name.
	 *
	 * @param string $suffix Table suffix without hd_ prefix.
	 * @return string
	 */
	public static function table( string $suffix ): string {
		global $wpdb;

		return $wpdb->base_prefix . 'hd_' . $suffix;
	}

	/**
	 * Get all fully-qualified helpdesk tables.
	 *
	 * @return array<int, string>
	 */
	public static function allTables(): array {
		return array_map( array( self::class, 'table' ), Constants::tableSuffixes() );
	}
}
