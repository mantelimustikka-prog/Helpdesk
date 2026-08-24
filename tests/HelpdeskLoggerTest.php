<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use WPHelpdesk\Support\HelpdeskLogger;
use WPHelpdesk\Support\Constants;

require_once __DIR__ . '/bootstrap.php';

final class HelpdeskLoggerTest extends TestCase {
	/** @var string */
	private string $tmpDir;

	protected function setUp(): void {
		wp_helpdesk_test_reset_state();
		$this->tmpDir = sys_get_temp_dir() . '/hd_logger_test_' . uniqid( '', true );
	}

	protected function tearDown(): void {
		// Clean up any created files recursively.
		$this->removeDir( $this->tmpDir );
	}

	private function removeDir( string $dir ): void {
		if ( ! is_dir( $dir ) ) {
			return;
		}
		$items = scandir( $dir ) ?: array();
		foreach ( $items as $item ) {
			if ( '.' === $item || '..' === $item ) {
				continue;
			}
			$path = $dir . '/' . $item;
			if ( is_dir( $path ) ) {
				$this->removeDir( $path );
			} else {
				unlink( $path );
			}
		}
		rmdir( $dir );
	}

	public function testEnsureLogDirCreatesDirectory(): void {
		$dir = $this->tmpDir . '/logs';
		self::assertFalse( is_dir( $dir ) );

		$result = HelpdeskLogger::ensureLogDir( $dir );

		self::assertTrue( $result );
		self::assertTrue( is_dir( $dir ) );
		self::assertFileExists( $dir . '/.htaccess' );
		self::assertFileExists( $dir . '/index.php' );
	}

	public function testEnsureLogDirReturnsTrueForExistingDirectory(): void {
		mkdir( $this->tmpDir, 0755, true );
		$result = HelpdeskLogger::ensureLogDir( $this->tmpDir );
		self::assertTrue( $result );
	}

	public function testLogDoesNothingWhenDisabled(): void {
		$GLOBALS['wp_site_options'][ Constants::OPTION_API_LOG_REQUESTS ] = false;

		// Logging disabled; no exception should be thrown and no file written.
		HelpdeskLogger::log( 'test.action', array( 'ticket_id' => 1 ) );

		self::assertFalse( file_exists( HelpdeskLogger::logFile() ) );
	}

	public function testReadEntriesReturnsEmptyArrayWhenFileAbsent(): void {
		$entries = HelpdeskLogger::readEntries();
		self::assertSame( array(), $entries );
	}

	public function testClearLogReturnsTrueWhenFileAbsent(): void {
		self::assertTrue( HelpdeskLogger::clearLog() );
	}

	public function testReadEntriesDecodesJsonLines(): void {
		// Write a couple of JSON lines to a temp file and point logFile() to it.
		$dir  = $this->tmpDir . '/' . HelpdeskLogger::LOG_DIR_RELATIVE;
		mkdir( $dir, 0755, true );
		$file = $dir . '/' . HelpdeskLogger::LOG_FILE_NAME;

		$line1 = json_encode( array( 'timestamp' => '2024-01-01T10:00:00Z', 'action' => 'getTicket.start', 'ticket_id' => 1 ) );
		$line2 = json_encode( array( 'timestamp' => '2024-01-01T10:00:01Z', 'action' => 'getTicket.messages_fetched', 'ticket_id' => 1, 'message_count' => 3 ) );
		file_put_contents( $file, $line1 . "\n" . $line2 . "\n" );

		// Override upload dir so HelpdeskLogger::logDir() returns our tmp path.
		$GLOBALS['wp_upload_dir_override'] = array( 'basedir' => $this->tmpDir );

		$entries = HelpdeskLogger::readEntries();

		// readEntries returns newest-first (reversed).
		self::assertCount( 2, $entries );
		self::assertSame( 'getTicket.messages_fetched', $entries[0]['action'] );
		self::assertSame( 'getTicket.start', $entries[1]['action'] );
	}

	public function testReadEntriesSkipsInvalidLines(): void {
		$dir  = $this->tmpDir . '/' . HelpdeskLogger::LOG_DIR_RELATIVE;
		mkdir( $dir, 0755, true );
		$file = $dir . '/' . HelpdeskLogger::LOG_FILE_NAME;

		file_put_contents( $file, "not-json\n" . json_encode( array( 'action' => 'ok' ) ) . "\n" );

		$GLOBALS['wp_upload_dir_override'] = array( 'basedir' => $this->tmpDir );

		$entries = HelpdeskLogger::readEntries();
		self::assertCount( 1, $entries );
		self::assertSame( 'ok', $entries[0]['action'] );
	}

	public function testReadEntriesLimitIsRespected(): void {
		$dir  = $this->tmpDir . '/' . HelpdeskLogger::LOG_DIR_RELATIVE;
		mkdir( $dir, 0755, true );
		$file = $dir . '/' . HelpdeskLogger::LOG_FILE_NAME;

		$lines = '';
		for ( $i = 1; $i <= 10; $i++ ) {
			$lines .= json_encode( array( 'action' => "action_{$i}" ) ) . "\n";
		}
		file_put_contents( $file, $lines );

		$GLOBALS['wp_upload_dir_override'] = array( 'basedir' => $this->tmpDir );

		$entries = HelpdeskLogger::readEntries( 3 );
		self::assertCount( 3, $entries );
	}

	public function testIsEnabledReturnsFalseByDefault(): void {
		self::assertFalse( HelpdeskLogger::isEnabled() );
	}

	public function testIsEnabledReturnsTrueWhenOptionSet(): void {
		$GLOBALS['wp_site_options'][ Constants::OPTION_API_LOG_REQUESTS ] = '1';
		self::assertTrue( HelpdeskLogger::isEnabled() );
	}
}
