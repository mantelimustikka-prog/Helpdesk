<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use WPHelpdesk\Domain\Ticket\TicketService;
use WPHelpdesk\Interfaces\Admin\Pages\TicketsPage;

require_once __DIR__ . '/bootstrap.php';

/**
 * Test double that intercepts DB calls and lets us inject a TicketService stub.
 */
final class TicketsPageTestDouble extends TicketsPage {
	public ?string $redirect_target = null;

	/** @var array<int, int> */
	public array $deleted_ticket_ids = array();

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
		return null;
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
