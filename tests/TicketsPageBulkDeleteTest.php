<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use WPHelpdesk\Domain\Attachment\AttachmentService;
use WPHelpdesk\Domain\Ticket\TicketService;
use WPHelpdesk\Interfaces\Admin\Pages\TicketsPage;

require_once __DIR__ . '/bootstrap.php';

/**
 * Test double that intercepts DB calls and lets us inject a TicketService stub.
 */
class TicketsPageTestDouble extends TicketsPage {
	public ?string $redirect_target = null;

	/** @var array<int, int> */
	public array $deleted_ticket_ids = array();

	/** @var array<int, array<string, mixed>> */
	public array $tickets_by_id = array();

	/** @var TicketService|null */
	private ?TicketService $injected_service = null;

	public function injectTicketService( TicketService $service ): void {
		$this->injected_service = $service;
	}

	protected function getTicketService(): TicketService {
		return $this->injected_service ?? parent::getTicketService();
	}

	protected function redirectTo( string $url ): void {
		wp_safe_redirect( $url );
		// Do not call exit so tests can continue making assertions.
	}

	protected function listTickets( int $limit, string $status_filter = '' ): array {
		return array(
			array(
				'id'              => 1,
				'ticket_no'       => 'HD-00001',
				'subject'         => 'Test issue',
				'requester_name'  => 'Alice',
				'requester_email' => 'alice@example.test',
				'requester_phone' => '',
				'status'          => 'new',
				'updated_at'      => '2024-01-01 12:00:00',
			),
			array(
				'id'              => 2,
				'ticket_no'       => 'HD-00002',
				'subject'         => 'Another issue',
				'requester_name'  => 'Bob',
				'requester_email' => 'bob@example.test',
				'requester_phone' => '',
				'status'          => 'closed',
				'updated_at'      => '2024-01-02 10:00:00',
			),
		);
	}

	protected function findTicket( int $ticket_id ): ?array {
		return $this->tickets_by_id[ $ticket_id ] ?? null;
	}

	protected function getMessages( int $ticket_id ): array {
		return array();
	}
}

final class TicketsPageBulkDeleteTest extends TestCase {

	protected function setUp(): void {
		wp_helpdesk_test_reset_state();
	}

	public function testBulkDeleteCallsDeleteTicketForEachSelectedId(): void {
		$ticket_service = new class extends TicketService {
			/** @var array<int, int> */
			public array $deleted_ids = array();

			public function deleteTicket( int $id ): bool {
				$this->deleted_ids[] = $id;
				return true;
			}
		};

		$page = new TicketsPageTestDouble();
		$page->injectTicketService( $ticket_service );

		$_GET['page']              = 'wp-helpdesk-tickets';
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST                     = array(
			'hd_ticket_nonce'  => 'valid-ticket-nonce',
			'hd_ticket_action' => 'bulk_delete',
			'hd_ticket_ids'    => array( '1', '2' ),
		);

		$page->handlePost();

		self::assertSame( array( 1, 2 ), $ticket_service->deleted_ids );
		self::assertStringContainsString( 'wp-helpdesk-tickets', (string) $GLOBALS['wp_safe_redirect_to'] );
	}

	public function testBulkDeleteWithEmptySelectionRedirectsWithoutDeletingAnything(): void {
		$ticket_service = new class extends TicketService {
			public int $delete_calls = 0;

			public function deleteTicket( int $id ): bool {
				++$this->delete_calls;
				return true;
			}
		};

		$page = new TicketsPageTestDouble();
		$page->injectTicketService( $ticket_service );

		$_GET['page']              = 'wp-helpdesk-tickets';
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST                     = array(
			'hd_ticket_nonce'  => 'valid-ticket-nonce',
			'hd_ticket_action' => 'bulk_delete',
			'hd_ticket_ids'    => array(),
		);

		$page->handlePost();

		self::assertSame( 0, $ticket_service->delete_calls );
	}

	public function testBulkDeleteRequiresManageTicketsCapability(): void {
		$GLOBALS['wp_current_user_caps']['hd_manage_tickets'] = false;

		$page = new TicketsPageTestDouble();

		$_GET['page']              = 'wp-helpdesk-tickets';
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST                     = array(
			'hd_ticket_nonce'  => 'valid-ticket-nonce',
			'hd_ticket_action' => 'bulk_delete',
			'hd_ticket_ids'    => array( '1' ),
		);

		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'You do not have permission to delete tickets.' );

		$page->handlePost();
	}

	public function testRenderContainsBulkDeleteCheckboxes(): void {
		$page = new TicketsPageTestDouble();

		$_GET['page']  = 'wp-helpdesk-tickets';
		$_GET['tab']   = '';

		ob_start();
		$page->render();
		$output = (string) ob_get_clean();

		self::assertStringContainsString( 'hd_ticket_ids[]', $output );
		self::assertStringContainsString( 'bulk_delete', $output );
		self::assertStringContainsString( 'hd-select-all', $output );
	}

	public function testRenderStatusFiltersUseCanonicalStatusesOnly(): void {
		$page = new TicketsPageTestDouble();

		$_GET['page'] = 'wp-helpdesk-tickets';
		$_GET['tab']  = '';

		ob_start();
		$page->render();
		$output = (string) ob_get_clean();

		self::assertStringContainsString( 'value="new"', $output );
		self::assertStringContainsString( 'value="pending_agent_reply"', $output );
		self::assertStringContainsString( 'value="pending_client_reply"', $output );
		self::assertStringContainsString( 'value="resolved"', $output );
		self::assertStringContainsString( 'value="closed"', $output );
		self::assertStringNotContainsString( 'value="in_progress"', $output );
		self::assertStringNotContainsString( 'value="waiting_customer"', $output );
	}

	public function testRenderSingleTicketViewDoesNotRenderQueueList(): void {
		$attachment_service = new class extends AttachmentService {
			public function getForTicket( int $ticket_id ): array {
				return array();
			}
		};
		$page = new TicketsPageTestDouble( $attachment_service );
		$page->tickets_by_id[1] = array(
			'id'              => 1,
			'ticket_no'       => 'HD-00001',
			'subject'         => 'Test issue',
			'requester_name'  => 'Alice',
			'requester_email' => 'alice@example.test',
			'requester_phone' => '',
			'status'          => 'new',
		);

		$_GET['page']      = 'wp-helpdesk-tickets';
		$_GET['ticket_id'] = '1';

		ob_start();
		$page->render();
		$output = (string) ob_get_clean();

		unset( $_GET['ticket_id'] );

		self::assertStringContainsString( 'Ticket HD-00001', $output );
		self::assertStringContainsString( 'Back to Queue', $output );
		self::assertStringContainsString( 'Previous Ticket', $output );
		self::assertStringContainsString( 'Next Ticket', $output );
		self::assertStringNotContainsString( '<h2>Queue</h2>', $output );
		self::assertStringNotContainsString( 'id="hd-bulk-form"', $output );
		self::assertStringNotContainsString( 'id="hd-status-filter"', $output );
	}

	public function testRenderSingleTicketViewNavigationPreservesQueueFilter(): void {
		$attachment_service = new class extends AttachmentService {
			public function getForTicket( int $ticket_id ): array {
				return array();
			}
		};
		$page = new class ( $attachment_service ) extends TicketsPageTestDouble {
			protected function listTickets( int $limit, string $status_filter = '' ): array {
				return array(
					array(
						'id'        => 1,
						'ticket_no' => 'HD-00001',
						'subject'   => 'First issue',
						'status'    => 'new',
					),
					array(
						'id'        => 2,
						'ticket_no' => 'HD-00002',
						'subject'   => 'Second issue',
						'status'    => 'new',
					),
					array(
						'id'        => 3,
						'ticket_no' => 'HD-00003',
						'subject'   => 'Third issue',
						'status'    => 'new',
					),
				);
			}
		};
		$page->tickets_by_id[2] = array(
			'id'              => 2,
			'ticket_no'       => 'HD-00002',
			'subject'         => 'Second issue',
			'requester_name'  => 'Bob',
			'requester_email' => 'bob@example.test',
			'requester_phone' => '',
			'status'          => 'new',
		);

		$_GET['page']          = 'wp-helpdesk-tickets';
		$_GET['ticket_id']     = '2';
		$_GET['status_filter'] = 'new';

		ob_start();
		$page->render();
		$output = (string) ob_get_clean();

		unset( $_GET['ticket_id'], $_GET['status_filter'] );

		self::assertStringContainsString( 'admin.php?page=wp-helpdesk-tickets&status_filter=new', $output );
		self::assertStringContainsString( 'ticket_id=1&status_filter=new', $output );
		self::assertStringContainsString( 'ticket_id=3&status_filter=new', $output );
		self::assertStringContainsString( 'Filtered by New', $output );
	}

	public function testRenderQueueTicketLinksPreserveStatusFilter(): void {
		$page = new TicketsPageTestDouble();

		$_GET['page']          = 'wp-helpdesk-tickets';
		$_GET['status_filter'] = 'pending_agent_reply';

		ob_start();
		$page->render();
		$output = (string) ob_get_clean();

		unset( $_GET['status_filter'] );

		self::assertStringContainsString( 'ticket_id=1&status_filter=pending_agent_reply', $output );
		self::assertStringContainsString( 'ticket_id=2&status_filter=pending_agent_reply', $output );
	}

	public function testBulkStatusCallsUpdateTicketForEachSelectedId(): void {
		$ticket_service = new class extends TicketService {
			/** @var array<int, array{id:int,status:string}> */
			public array $updates = array();

			public function updateTicket( int $id, array $data ): bool {
				$this->updates[] = array(
					'id'     => $id,
					'status' => (string) ( $data['status'] ?? '' ),
				);
				return true;
			}
		};

		$page = new TicketsPageTestDouble();
		$page->injectTicketService( $ticket_service );
		$page->tickets_by_id = array(
			1 => array( 'id' => 1, 'status' => 'new' ),
			2 => array( 'id' => 2, 'status' => 'pending_agent_reply' ),
		);

		$_GET['page']              = 'wp-helpdesk-tickets';
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST                     = array(
			'hd_ticket_nonce'  => 'valid-ticket-nonce',
			'hd_ticket_action' => 'bulk_status',
			'hd_ticket_ids'    => array( '1', '2' ),
			'hd_bulk_status'   => 'pending_client_reply',
		);

		$page->handlePost();

		self::assertSame(
			array(
				array( 'id' => 1, 'status' => 'pending_client_reply' ),
				array( 'id' => 2, 'status' => 'pending_client_reply' ),
			),
			$ticket_service->updates
		);
		self::assertStringContainsString( 'wp-helpdesk-tickets', (string) $GLOBALS['wp_safe_redirect_to'] );
	}

	public function testNonceFailureDies(): void {
		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'Security check failed.' );

		$page = new TicketsPageTestDouble();

		$_GET['page']              = 'wp-helpdesk-tickets';
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST                     = array(
			'hd_ticket_nonce'  => 'bad-nonce',
			'hd_ticket_action' => 'bulk_delete',
			'hd_ticket_ids'    => array( '1' ),
		);

		$page->handlePost();
	}
}
