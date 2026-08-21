<?php
/**
 * @package WP Helpdesk
 */

namespace WPHelpdesk\Infrastructure\Database;

use WPHelpdesk\Support\Constants;

class Migrator {
	/**
	 * Run all available migrations from the default migrations directory.
	 *
	 * @return void
	 */
	public static function runAll(): void {
		static::runAllFromDirectory( trailingslashit( HD_PATH ) . 'migrations' );
	}

	/**
	 * Check whether there are unapplied migrations in the default directory.
	 *
	 * @return bool
	 */
	public static function hasPendingMigrations(): bool {
		return static::hasPendingMigrationsInDirectory( trailingslashit( HD_PATH ) . 'migrations' );
	}

	/**
	 * Run all available migrations from a given directory.
	 *
	 * Migrations are PHP files that return an object with an `up()` method.
	 * Each migration is recorded by file name in the `hd_applied_migrations`
	 * site option so it is never executed more than once.
	 *
	 * @param string $directory Absolute path to the migrations directory.
	 * @return void
	 */
	public static function runAllFromDirectory( string $directory ): void {
		$migration_files = glob( trailingslashit( $directory ) . '*.php' );

		if ( empty( $migration_files ) ) {
			update_site_option( Constants::OPTION_DB_VERSION, HD_VERSION );
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

	/**
	 * Check whether a migration directory contains unapplied files.
	 *
	 * @param string $directory Absolute path to the migrations directory.
	 * @return bool
	 */
	public static function hasPendingMigrationsInDirectory( string $directory ): bool {
		$migration_files = glob( trailingslashit( $directory ) . '*.php' );
		if ( empty( $migration_files ) ) {
			return false;
		}

		sort( $migration_files );

		/** @var string[] $applied */
		$applied = (array) get_site_option( Constants::OPTION_APPLIED_MIGRATIONS, array() );

		foreach ( $migration_files as $migration_file ) {
			if ( ! in_array( basename( $migration_file ), $applied, true ) ) {
				return true;
			}
		}

		return false;
	}
}
