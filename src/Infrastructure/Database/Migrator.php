<?php
/**
 * @package WP Helpdesk
 */

namespace WPHelpdesk\Infrastructure\Database;

use WPHelpdesk\Support\Constants;

class Migrator {
	/**
	 * Run all available migrations.
	 *
	 * @return void
	 */
	public static function runAll(): void {
		$migration_files = glob( trailingslashit( HD_PATH ) . 'migrations/*.php' );

		if ( empty( $migration_files ) ) {
			return;
		}

		sort( $migration_files );

		/** @var string[] $applied */
		$applied = (array) get_site_option( Constants::OPTION_APPLIED_MIGRATIONS, array() );

		foreach ( $migration_files as $migration_file ) {
			$migration_name = basename( $migration_file );

			if ( in_array( $migration_name, $applied, true ) ) {
				continue;
			}

			$migration = require $migration_file;

			if ( is_object( $migration ) && method_exists( $migration, 'up' ) ) {
				$migration->up();
				$applied[] = $migration_name;
			}
		}

		update_site_option( Constants::OPTION_APPLIED_MIGRATIONS, $applied );
		update_site_option( Constants::OPTION_DB_VERSION, HD_VERSION );
	}
}
