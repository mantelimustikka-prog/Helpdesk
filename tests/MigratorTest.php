<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use WPHelpdesk\Infrastructure\Database\Migrator;
use WPHelpdesk\Support\Constants;

require_once __DIR__ . '/bootstrap.php';

final class MigratorTest extends TestCase {
	/** @var string Temporary migrations directory used for isolated tests. */
	private string $tmp_migrations_dir = '';

	protected function setUp(): void {
		wp_helpdesk_test_reset_state();

		$this->tmp_migrations_dir = sys_get_temp_dir() . '/hd_migrator_test_' . uniqid();
		mkdir( $this->tmp_migrations_dir, 0777, true );
	}

	protected function tearDown(): void {
		// Clean up tmp migration files.
		foreach ( glob( $this->tmp_migrations_dir . '/*.php' ) ?: [] as $file ) {
			unlink( $file );
		}
		if ( is_dir( $this->tmp_migrations_dir ) ) {
			rmdir( $this->tmp_migrations_dir );
		}
	}

	/**
	 * Build a migration file that simply appends its name to a shared array.
	 */
	private function createMigrationFile( string $name ): string {
		$path = $this->tmp_migrations_dir . '/' . $name;
		$code = <<<PHP
<?php
return new class {
    public function up(): void {
        \$GLOBALS['hd_test_ran_migrations'][] = '$name';
    }
};
PHP;
		file_put_contents( $path, $code );
		return $path;
	}

	public function testRunAllAppliesNewMigrationsAndRecordsThem(): void {
		$GLOBALS['hd_test_ran_migrations'] = [];
		$name = '001_test_migration.php';
		$this->createMigrationFile( $name );

		Migrator::runAllFromDirectory( $this->tmp_migrations_dir );

		self::assertContains( $name, $GLOBALS['hd_test_ran_migrations'], 'Migration should have been applied.' );

		$applied = get_site_option( Constants::OPTION_APPLIED_MIGRATIONS, [] );
		self::assertContains( $name, $applied, 'Applied migration should be recorded in site option.' );
	}

	public function testRunAllSkipsAlreadyAppliedMigrations(): void {
		$GLOBALS['hd_test_ran_migrations'] = [];
		$name = '002_already_applied.php';
		$this->createMigrationFile( $name );

		// Pre-mark the migration as already applied.
		update_site_option( Constants::OPTION_APPLIED_MIGRATIONS, [ $name ] );

		Migrator::runAllFromDirectory( $this->tmp_migrations_dir );

		self::assertNotContains( $name, $GLOBALS['hd_test_ran_migrations'], 'Already-applied migration must not run again.' );
	}

	public function testRunAllIsIdempotentWhenCalledTwice(): void {
		$GLOBALS['hd_test_ran_migrations'] = [];
		$name = '003_idempotent.php';
		$this->createMigrationFile( $name );

		Migrator::runAllFromDirectory( $this->tmp_migrations_dir );
		Migrator::runAllFromDirectory( $this->tmp_migrations_dir );

		self::assertSame( 1, count( array_filter( $GLOBALS['hd_test_ran_migrations'], fn( $n ) => $n === $name ) ), 'Migration must run exactly once even when runAll is called twice.' );
	}

	public function testRunAllUpdatesDbVersionOption(): void {
		Migrator::runAllFromDirectory( $this->tmp_migrations_dir );

		$stored = get_site_option( Constants::OPTION_DB_VERSION, '' );
		self::assertSame( HD_VERSION, $stored, 'DB version option must be updated to current plugin version after migration run.' );
	}

	public function testHasPendingMigrationsInDirectoryReturnsTrueWhenUnappliedExists(): void {
		$name = '004_pending.php';
		$this->createMigrationFile( $name );

		self::assertTrue( Migrator::hasPendingMigrationsInDirectory( $this->tmp_migrations_dir ) );
	}

	public function testHasPendingMigrationsInDirectoryReturnsFalseWhenAllApplied(): void {
		$name = '005_applied.php';
		$this->createMigrationFile( $name );
		update_site_option( Constants::OPTION_APPLIED_MIGRATIONS, array( $name ) );

		self::assertFalse( Migrator::hasPendingMigrationsInDirectory( $this->tmp_migrations_dir ) );
	}
}
