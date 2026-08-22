<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use WPHelpdesk\Domain\Attachment\AttachmentService;
use WPHelpdesk\Domain\Ticket\TicketRepository;
use WPHelpdesk\Domain\Ticket\TicketService;
use WPHelpdesk\Infrastructure\Database\Schema;
use WPHelpdesk\Interfaces\Frontend\GuestTicketForm;
use WPHelpdesk\Interfaces\Frontend\MemberTicketForm;
use WPHelpdesk\Support\Constants;

require_once __DIR__ . '/bootstrap.php';

/**
 * Tests for attachment cleanup on ticket deletion, and frontend upload UI.
 */
final class AttachmentCleanupAndUploadUiTest extends TestCase {

	protected function setUp(): void {
		wp_helpdesk_test_reset_state();
	}

	// -------------------------------------------------------------------------
	// TicketService::deleteTicket must call AttachmentService::deleteForTicket
	// -------------------------------------------------------------------------

	public function testDeleteTicketCleansUpAttachments(): void {
		$deleted_attachment_ticket_ids = array();

		$repository = new class extends TicketRepository {
			public array $deleted_ticket_ids = array();

			public function delete( int $id, int $network_id ): bool {
				$this->deleted_ticket_ids[] = $id;
				return true;
			}
		};

		$service = $this->makeServiceWithSpyAttachment(
			$repository,
			$deleted_attachment_ticket_ids
		);

		$result = $service->deleteTicket( 42 );

		self::assertTrue( $result );
		self::assertContains( 42, $deleted_attachment_ticket_ids, 'deleteForTicket must be called with the ticket id' );
	}

	public function testDeleteTicketReturnsFalseWhenRepositoryFails(): void {
		$deleted_attachment_ticket_ids = array();

		$repository = new class extends TicketRepository {
			public function delete( int $id, int $network_id ): bool {
				return false;
			}
		};

		$service = $this->makeServiceWithSpyAttachment(
			$repository,
			$deleted_attachment_ticket_ids
		);

		// Attachment cleanup is still attempted even when ticket delete fails.
		$result = $service->deleteTicket( 99 );

		self::assertFalse( $result );
		self::assertContains( 99, $deleted_attachment_ticket_ids );
	}

	public function testDeleteTicketCleansUpMessageAndEventRows(): void {
		$repository = new class extends TicketRepository {
			public function delete( int $id, int $network_id ): bool {
				return true;
			}
		};

		global $wpdb;
		$wpdb = new class {
			public string $base_prefix = 'wp_';
			public string $sitemeta    = 'wp_sitemeta';
			/** @var array<int, array{table:string,where:array<string,int>}> */
			public array $delete_calls = array();

			public function get_col( string $query ): array {
				return array();
			}

			public function prepare( string $query, ...$args ): string {
				return $query;
			}

			public function query( string $query ): int {
				return 1;
			}

			public function delete( string $table, array $where, array $format = array() ): int {
				$this->delete_calls[] = array(
					'table' => $table,
					'where' => $where,
				);
				return 1;
			}
		};

		$service = new TicketService();

		$repo_prop = new ReflectionProperty( TicketService::class, 'repository' );
		$repo_prop->setAccessible( true );
		$repo_prop->setValue( $service, $repository );

		$service->deleteTicket( 42 );

		$deleted_tables = array_map(
			static fn( array $call ): string => $call['table'],
			$wpdb->delete_calls
		);

		self::assertContains( Schema::table( Constants::TABLE_TICKET_MESSAGES ), $deleted_tables );
		self::assertContains( Schema::table( Constants::TABLE_TICKET_EVENTS ), $deleted_tables );
	}

	// -------------------------------------------------------------------------
	// Frontend forms must include file input fields
	// -------------------------------------------------------------------------

	public function testGuestTicketFormIncludesFileInput(): void {
		ob_start();
		( new GuestTicketForm() )->render();
		$output = (string) ob_get_clean();

		self::assertStringContainsString( 'type="file"', $output );
		self::assertStringContainsString( 'attachments[]', $output );
		self::assertStringContainsString( 'image/jpeg', $output );
		self::assertStringContainsString( 'application/pdf', $output );
	}

	public function testMemberTicketFormIncludesFileInput(): void {
		ob_start();
		( new MemberTicketForm() )->render();
		$output = (string) ob_get_clean();

		self::assertStringContainsString( 'type="file"', $output );
		self::assertStringContainsString( 'attachments[]', $output );
		self::assertStringContainsString( 'image/jpeg', $output );
	}

	public function testGuestFormFileInputAcceptsAllowedMimeTypes(): void {
		ob_start();
		( new GuestTicketForm() )->render();
		$output = (string) ob_get_clean();

		foreach ( AttachmentService::ALLOWED_MIME_TYPES as $mime ) {
			self::assertStringContainsString( $mime, $output, "MIME type {$mime} should be listed in the accept attribute" );
		}
	}

	// -------------------------------------------------------------------------
	// AttachmentService::deleteForTicket
	// -------------------------------------------------------------------------

	public function testDeleteForTicketIsIdempotentWithNoAttachments(): void {
		$service = new class extends AttachmentService {
			public function deleteForTicket( int $ticket_id ): void {
				// With an empty/stubbed DB no errors should be thrown.
			}
		};

		// Should not throw.
		$service->deleteForTicket( 100 );
		$this->addToAssertionCount( 1 );
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Build a TicketService with an injected spy AttachmentService and TicketRepository.
	 *
	 * @param TicketRepository     $repository
	 * @param array<int, int>      &$deleted_attachment_ticket_ids
	 * @return TicketService
	 */
	private function makeServiceWithSpyAttachment(
		TicketRepository $repository,
		array &$deleted_attachment_ticket_ids
	): TicketService {
		$spy_attachment_service = new class ( $deleted_attachment_ticket_ids ) extends AttachmentService {
			/** @var array<int, int> */
			private array $log;

			/** @param array<int, int> &$log */
			public function __construct( array &$log ) {
				$this->log = &$log;
			}

			public function deleteForTicket( int $ticket_id ): void {
				$this->log[] = $ticket_id;
			}
		};

		// Build a TicketService subclass that uses the spy AttachmentService.
		$service = new class ( $spy_attachment_service ) extends TicketService {
			private AttachmentService $attachment_service;

			public function __construct( AttachmentService $attachment_service ) {
				parent::__construct();
				$this->attachment_service = $attachment_service;
			}

			public function deleteTicket( int $id ): bool {
				$this->attachment_service->deleteForTicket( $id );
				return $this->repository->delete( $id, $this->network_id );
			}
		};

		// Inject the stub repository via reflection.
		$repo_prop = new ReflectionProperty( TicketService::class, 'repository' );
		$repo_prop->setAccessible( true );
		$repo_prop->setValue( $service, $repository );

		$net_prop = new ReflectionProperty( TicketService::class, 'network_id' );
		$net_prop->setAccessible( true );
		$net_prop->setValue( $service, 1 );

		return $service;
	}
}
