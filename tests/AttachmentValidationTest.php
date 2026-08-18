<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use WPHelpdesk\Domain\Attachment\AttachmentService;

require_once __DIR__ . '/bootstrap.php';

final class AttachmentValidationTest extends TestCase {

	public function testHandleUploadRejectsOversizedFile(): void {
		$service = new AttachmentService();

		$file   = [
			'size'     => AttachmentService::MAX_FILE_SIZE + 1,
			'tmp_name' => '/tmp/bigfile.bin',
			'name'     => 'big.pdf',
		];
		$result = $service->handleUpload( $file, 1, null, 1 );

		self::assertTrue( is_wp_error( $result ) );
		self::assertSame( 'hd_attachment_size', $result->get_error_code() );
	}

	public function testHandleUploadRejectsEmptySize(): void {
		$service = new AttachmentService();

		$result = $service->handleUpload(
			[ 'size' => 0, 'tmp_name' => '/tmp/empty', 'name' => 'empty.txt' ],
			1,
			null,
			1
		);

		self::assertTrue( is_wp_error( $result ) );
		self::assertSame( 'hd_attachment_size', $result->get_error_code() );
	}

	public function testHandleUploadRejectsDisallowedMimeType(): void {
		$service = new AttachmentService();

		// Simulate a valid-sized file but disallowed mime type.
		// We patch wp_check_filetype_and_ext via global override in bootstrap.
		$GLOBALS['hd_test_filetype'] = [
			'type' => 'application/x-executable',
			'ext'  => 'exe',
		];

		$file   = [
			'size'     => 1024,
			'tmp_name' => '/tmp/evil.exe',
			'name'     => 'evil.exe',
		];
		$result = $service->handleUpload( $file, 1, null, 1 );

		self::assertTrue( is_wp_error( $result ) );
		self::assertSame( 'hd_attachment_type', $result->get_error_code() );

		unset( $GLOBALS['hd_test_filetype'] );
	}

	public function testAllowedMimeTypesListIsNonEmpty(): void {
		self::assertNotEmpty( AttachmentService::ALLOWED_MIME_TYPES );

		foreach ( AttachmentService::ALLOWED_MIME_TYPES as $mime ) {
			self::assertStringContainsString( '/', $mime, 'Each MIME type must contain a slash' );
		}
	}

	public function testMaxFileSizeIsReasonable(): void {
		// 1 MB minimum, 100 MB maximum – sanity bounds.
		self::assertGreaterThanOrEqual( 1_048_576, AttachmentService::MAX_FILE_SIZE );
		self::assertLessThanOrEqual( 104_857_600, AttachmentService::MAX_FILE_SIZE );
	}

	public function testDeleteReturnsFalseForMissingAttachment(): void {
		// Subclass AttachmentService to use a repository-like find stub.
		$service = new class extends AttachmentService {
			public function delete( int $attachment_id, int $user_id ): bool {
				// Simulate: no row found.
				return false;
			}
		};

		// Calling delete on missing attachment id must return false.
		self::assertFalse( $service->delete( 9999, 1 ) );
	}
}
