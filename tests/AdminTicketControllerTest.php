<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use WPHelpdesk\Interfaces\Rest\AdminTicketController;

require_once __DIR__ . '/bootstrap.php';

/**
 * Minimal testable sub-class of AdminTicketController.
 *
 * Overrides the two database-touching methods so the unit tests can run
 * without a real MySQL connection.
 */
final class FakeAdminTicketController extends AdminTicketController {
	/** @var array<string, mixed>|null */
	private ?array $ticket;

	/** @var array<int, array<string, mixed>> */
	private array $messages;

	/**
	 * @param array<string, mixed>|null         $ticket
	 * @param array<int, array<string, mixed>>  $messages
	 */
	public function __construct( ?array $ticket, array $messages = array() ) {
		$this->ticket   = $ticket;
		$this->messages = $messages;
	}

	protected function findTicket( int $ticket_id ): ?array {
		return $this->ticket;
	}

	protected function fetchMessagesForTicket( int $ticket_id ): array {
		return $this->messages;
	}
}

final class AdminTicketControllerTest extends TestCase {
	protected function setUp(): void {
		wp_helpdesk_test_reset_state();
	}

	public function testGetTicketReturns404WhenTicketNotFound(): void {
		$controller = new FakeAdminTicketController( null );
		$request    = new WP_REST_Request();
		$request['id'] = 99;

		$response = $controller->getTicket( $request );

		self::assertSame( 404, $response->status );
		self::assertSame( 'Ticket not found.', $response->data['message'] );
	}

	public function testGetTicketEmbedsEmptyMessagesWhenNoneExist(): void {
		$ticket = array(
			'id'        => 1,
			'ticket_no' => 'HD-000001',
			'subject'   => 'Test ticket',
			'status'    => 'open',
		);

		$controller = new FakeAdminTicketController( $ticket, array() );
		$request    = new WP_REST_Request();
		$request['id'] = 1;

		$response = $controller->getTicket( $request );

		self::assertSame( 200, $response->status );
		self::assertTrue( $response->data['success'] );
		self::assertArrayHasKey( 'messages', $response->data['data'] );
		self::assertSame( array(), $response->data['data']['messages'] );
		self::assertSame( 'HD-000001', $response->data['data']['ticket_no'] );
	}

	public function testGetTicketEmbedsMessagesInResponse(): void {
		$ticket = array(
			'id'        => 2,
			'ticket_no' => 'HD-000002',
			'subject'   => 'Login issue',
			'status'    => 'open',
		);
		$messages = array(
			array(
				'id'             => 101,
				'ticket_id'      => 2,
				'author_user_id' => 0,
				'author_type'    => 'customer',
				'author_name'    => null,
				'body'           => 'I cannot log in.',
				'is_internal'    => 0,
				'created_at'     => '2026-08-22 10:00:00',
			),
			array(
				'id'             => 102,
				'ticket_id'      => 2,
				'author_user_id' => 0,
				'author_type'    => 'agent',
				'author_name'    => null,
				'body'           => 'Please try resetting your password.',
				'is_internal'    => 0,
				'created_at'     => '2026-08-22 10:05:00',
			),
		);

		$controller = new FakeAdminTicketController( $ticket, $messages );
		$request    = new WP_REST_Request();
		$request['id'] = 2;

		$response = $controller->getTicket( $request );

		self::assertSame( 200, $response->status );
		self::assertTrue( $response->data['success'] );
		self::assertCount( 2, $response->data['data']['messages'] );
		self::assertSame( 'I cannot log in.', $response->data['data']['messages'][0]['body'] );
		self::assertSame( 'agent', $response->data['data']['messages'][1]['author_type'] );
	}

	public function testGetTicketAddsAuthorNameFromWpUserWhenAuthorUserIdIsSet(): void {
		$ticket = array(
			'id'        => 3,
			'ticket_no' => 'HD-000003',
			'subject'   => 'Billing',
			'status'    => 'open',
		);
		$messages = array(
			array(
				'id'             => 201,
				'ticket_id'      => 3,
				'author_user_id' => 7,
				'author_type'    => 'agent',
				'body'           => 'I will look into this.',
				'is_internal'    => 0,
				'created_at'     => '2026-08-22 09:00:00',
			),
		);

		// Register a fake WP user in the global users index.
		$GLOBALS['wp_users_index'][7] = (object) array( 'display_name' => 'Agent Smith' );

		$controller = new FakeAdminTicketController( $ticket, $messages );
		$request    = new WP_REST_Request();
		$request['id'] = 3;

		$response = $controller->getTicket( $request );

		self::assertSame( 200, $response->status );
		self::assertSame( 'Agent Smith', $response->data['data']['messages'][0]['author_name'] );
	}

	public function testGetMessagesReturnsItemsArray(): void {
		$ticket = array(
			'id'        => 4,
			'ticket_no' => 'HD-000004',
			'subject'   => 'Shipping',
			'status'    => 'open',
		);
		$messages = array(
			array(
				'id'             => 301,
				'ticket_id'      => 4,
				'author_user_id' => 0,
				'author_type'    => 'customer',
				'body'           => 'Where is my order?',
				'is_internal'    => 0,
				'created_at'     => '2026-08-22 08:00:00',
			),
		);

		$controller = new FakeAdminTicketController( $ticket, $messages );
		$request    = new WP_REST_Request();
		$request['id'] = 4;

		$response = $controller->getMessages( $request );

		self::assertSame( 200, $response->status );
		self::assertArrayHasKey( 'items', $response->data );
		self::assertCount( 1, $response->data['items'] );
		self::assertSame( 'Where is my order?', $response->data['items'][0]['body'] );
	}
}
