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

	/** @var array<int, array<string, mixed>|null> */
	private array $ticketSequence;

	/**
	 * @param array<string, mixed>|null         $ticket
	 * @param array<int, array<string, mixed>>  $messages
	 */
	public function __construct( ?array $ticket, array $messages = array(), array $ticketSequence = array() ) {
		$this->ticket         = $ticket;
		$this->messages       = $messages;
		$this->ticketSequence = $ticketSequence;
	}

	protected function findTicket( int $ticket_id ): ?array {
		if ( ! empty( $this->ticketSequence ) ) {
			$ticket = array_shift( $this->ticketSequence );
			return is_array( $ticket ) ? $ticket : null;
		}
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

	public function testGetTicketAndGetMessagesExposeSameNormalizedRows(): void {
		$ticket = array(
			'id'        => 44,
			'ticket_no' => 'HD-000044',
			'subject'   => 'Parity check',
			'status'    => 'open',
		);
		$messages = array(
			array(
				'id'             => 601,
				'ticket_id'      => 44,
				'author_user_id' => 0,
				'author_type'    => 'guest',
				'body'           => 'Public message',
				'is_internal'    => 0,
				'created_at'     => '2026-08-24 09:00:00',
			),
			array(
				'id'             => 602,
				'ticket_id'      => 44,
				'author_user_id' => 7,
				'author_type'    => 'agent',
				'body'           => 'Internal follow-up',
				'is_internal'    => 1,
				'created_at'     => '2026-08-24 09:10:00',
			),
		);
		$GLOBALS['wp_users_index'][7] = (object) array( 'display_name' => 'Agent Smith' );

		$controller = new FakeAdminTicketController( $ticket, $messages );
		$request    = new WP_REST_Request();
		$request['id'] = 44;

		$ticket_response   = $controller->getTicket( $request );
		$messages_response = $controller->getMessages( $request );

		self::assertSame( 200, $ticket_response->status );
		self::assertSame( 200, $messages_response->status );
		self::assertSame( $ticket_response->data['data']['messages'], $messages_response->data['items'] );
	}

	public function testReplyReturnsWrappedNormalizedMessage(): void {
		global $wpdb;

		$ticket = array(
			'id'        => 5,
			'ticket_no' => 'HD-000005',
			'subject'   => 'Reply test',
			'status'    => 'open',
		);
		$controller = new FakeAdminTicketController( $ticket );
		$wpdb       = new class {
			public string $base_prefix = 'wp_';
			public int $insert_id = 401;
			/** @var array<string, mixed> */
			public array $last_insert = array();

			public function insert( string $table, array $data, array $format = array() ): int {
				$this->last_insert = $data;
				return 1;
			}

			public function prepare( string $query, ...$args ): string {
				return $query;
			}

			public function get_row( string $query, string $output ) {
				return array(
					'id'             => 401,
					'ticket_id'      => 5,
					'author_user_id' => 7,
					'author_type'    => 'agent',
					'body'           => $this->last_insert['body'] ?? '',
					'is_internal'    => $this->last_insert['is_internal'] ?? 0,
					'created_at'     => $this->last_insert['created_at'] ?? null,
				);
			}
		};
		$GLOBALS['wp_users_index'][7] = (object) array( 'display_name' => 'Agent Smith' );

		$request = new WP_REST_Request();
		$request['id'] = 5;
		$request->set_param( 'body', 'Thanks for the update.' );
		$request->set_param( 'is_internal', 0 );

		$response = $controller->reply( $request );

		self::assertSame( 201, $response->status );
		self::assertTrue( $response->data['success'] );
		self::assertSame( 401, $response->data['data']['id'] );
		self::assertSame( 'Agent Smith', $response->data['data']['author_name'] );
		self::assertSame( 'Thanks for the update.', $response->data['data']['body'] );
		self::assertSame( 'agent', $response->data['data']['author_type'] );
	}

	public function testAddNoteReturnsWrappedNormalizedMessage(): void {
		global $wpdb;

		$ticket = array(
			'id'        => 6,
			'ticket_no' => 'HD-000006',
			'subject'   => 'Internal note test',
			'status'    => 'open',
		);
		$controller = new FakeAdminTicketController( $ticket );
		$wpdb       = new class {
			public string $base_prefix = 'wp_';
			public int $insert_id = 501;
			/** @var array<string, mixed> */
			public array $last_insert = array();

			public function insert( string $table, array $data, array $format = array() ): int {
				$this->last_insert = $data;
				return 1;
			}

			public function prepare( string $query, ...$args ): string {
				return $query;
			}

			public function get_row( string $query, string $output ) {
				return array(
					'id'             => 501,
					'ticket_id'      => 6,
					'author_user_id' => 7,
					'author_type'    => 'agent',
					'body'           => $this->last_insert['body'] ?? '',
					'is_internal'    => 1,
					'created_at'     => $this->last_insert['created_at'] ?? null,
				);
			}
		};
		$GLOBALS['wp_users_index'][7] = (object) array( 'display_name' => 'Agent Smith' );

		$request = new WP_REST_Request();
		$request['id'] = 6;
		$request->set_param( 'body', 'Investigating logs.' );

		$response = $controller->addNote( $request );

		self::assertSame( 201, $response->status );
		self::assertTrue( $response->data['success'] );
		self::assertSame( 501, $response->data['data']['id'] );
		self::assertSame( 'Agent Smith', $response->data['data']['author_name'] );
		self::assertSame( 1, $response->data['data']['is_internal'] );
		self::assertSame( 'Investigating logs.', $response->data['data']['body'] );
	}

	public function testUpdateStatusReturnsWrappedNormalizedTicket(): void {
		global $wpdb;

		$original = array(
			'id'        => 7,
			'ticket_no' => 'HD-000007',
			'subject'   => 'Status test',
			'status'    => 'open',
		);
		$updated = $original;
		$updated['status'] = 'closed';
		$controller        = new FakeAdminTicketController( $original, array(), array( $original, $updated ) );
		$wpdb              = new class {
			public string $base_prefix = 'wp_';
			/** @var array<string, mixed> */
			public array $last_update = array();

			public function update( string $table, array $data, array $where, array $format = array(), array $where_format = array() ): int {
				$this->last_update = $data;
				return 1;
			}
		};

		$request = new WP_REST_Request();
		$request['id'] = 7;
		$request->set_param( 'status', 'closed' );

		$response = $controller->updateStatus( $request );

		self::assertSame( 200, $response->status );
		self::assertTrue( $response->data['success'] );
		self::assertSame( 7, $response->data['data']['id'] );
		self::assertSame( 'closed', $response->data['data']['status'] );
		self::assertSame( current_time( 'mysql' ), $wpdb->last_update['closed_at'] );
	}
}
