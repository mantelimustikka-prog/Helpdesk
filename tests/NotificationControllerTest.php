<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use WPHelpdesk\Interfaces\Rest\NotificationController;

require_once __DIR__ . '/bootstrap.php';

/**
 * Testable sub-class that bypasses database queries.
 */
final class FakeNotificationController extends NotificationController {
	/** @var array<int, array<string, mixed>> */
	private array $tickets;

	/** @var array<int, array<string, mixed>> */
	private array $replies;

	/**
	 * @param array<int, array<string, mixed>> $tickets
	 * @param array<int, array<string, mixed>> $replies
	 */
	public function __construct( array $tickets = array(), array $replies = array() ) {
		$this->tickets = $tickets;
		$this->replies = $replies;
	}

	/**
	 * @param int $since Unix timestamp.
	 * @return array<int, array<string, mixed>>
	 */
	protected function fetchNewTickets( int $since ): array {
		return $this->tickets;
	}

	/**
	 * @param int $since Unix timestamp.
	 * @return array<int, array<string, mixed>>
	 */
	protected function fetchNewReplies( int $since ): array {
		return $this->replies;
	}

	/**
	 * Expose the protected method for testing.
	 *
	 * @return array<int, string>
	 */
	public function getAgentFacingStorageStatuses(): array {
		return $this->agentFacingStorageStatuses();
	}
}

final class NotificationControllerTest extends TestCase {
	protected function setUp(): void {
		wp_helpdesk_test_reset_state();
		$GLOBALS['wp_current_user_logged_in'] = true;
		$GLOBALS['wp_current_user_caps']      = array( 'hd_manage_tickets' => true );
	}

	protected function tearDown(): void {
		$GLOBALS['wp_current_user_logged_in'] = false;
		$GLOBALS['wp_current_user_caps']      = array();
	}

	public function testReturnsEmptyArraysWhenNoNewItems(): void {
		$controller = new FakeNotificationController();
		$request    = new WP_REST_Request();
		$request->set_param( 'since', (string) ( time() - 3600 ) );

		$response = $controller->since( $request );

		self::assertSame( 200, $response->status );
		$data = $response->data;
		self::assertIsArray( $data );
		self::assertArrayHasKey( 'new_tickets', $data );
		self::assertArrayHasKey( 'new_replies', $data );
		self::assertEmpty( $data['new_tickets'] );
		self::assertEmpty( $data['new_replies'] );
		self::assertTrue( $data['success'] );
	}

	public function testReturnsNewTicketsWhenPresent(): void {
		$ticket = array(
			'id'         => 42,
			'ticket_no'  => 'HD-42',
			'subject'    => 'Test subject',
			'status'     => 'open',
			'created_at' => '2026-08-24 20:00:00',
		);

		$controller = new FakeNotificationController( array( $ticket ) );
		$request    = new WP_REST_Request();
		$request->set_param( 'since', (string) ( time() - 3600 ) );

		$response = $controller->since( $request );

		self::assertSame( 200, $response->status );
		$data = $response->data;
		self::assertCount( 1, $data['new_tickets'] );
		self::assertSame( 42, $data['new_tickets'][0]['id'] );
		self::assertSame( 'HD-42', $data['new_tickets'][0]['ticket_no'] );
		self::assertSame( 'Test subject', $data['new_tickets'][0]['subject'] );
		self::assertEmpty( $data['new_replies'] );
	}

	public function testReturnsNewRepliesWhenPresent(): void {
		$reply = array(
			'id'         => 123,
			'ticket_id'  => 42,
			'ticket_no'  => 'HD-42',
			'author'     => 'Staff Member',
			'message_excerpt' => 'First 100 chars of the reply...',
			'created_at' => '2026-08-24 20:05:00',
		);

		$controller = new FakeNotificationController( array(), array( $reply ) );
		$request    = new WP_REST_Request();
		$request->set_param( 'since', (string) ( time() - 3600 ) );

		$response = $controller->since( $request );

		self::assertSame( 200, $response->status );
		$data = $response->data;
		self::assertEmpty( $data['new_tickets'] );
		self::assertCount( 1, $data['new_replies'] );
		self::assertSame( 123, $data['new_replies'][0]['id'] );
		self::assertSame( 42, $data['new_replies'][0]['ticket_id'] );
		self::assertSame( 'HD-42', $data['new_replies'][0]['ticket_no'] );
	}

	public function testReturnsBothTicketsAndReplies(): void {
		$ticket = array(
			'id'         => 1,
			'ticket_no'  => 'HD-1',
			'subject'    => 'Subject',
			'status'     => 'new',
			'created_at' => '2026-08-24 20:00:00',
		);
		$reply = array(
			'id'              => 10,
			'ticket_id'       => 2,
			'ticket_no'       => 'HD-2',
			'author'          => 'Staff',
			'message_excerpt' => 'Reply body',
			'created_at'      => '2026-08-24 20:05:00',
		);

		$controller = new FakeNotificationController( array( $ticket ), array( $reply ) );
		$request    = new WP_REST_Request();
		$request->set_param( 'since', (string) ( time() - 3600 ) );

		$response = $controller->since( $request );

		self::assertSame( 200, $response->status );
		$data = $response->data;
		self::assertCount( 1, $data['new_tickets'] );
		self::assertCount( 1, $data['new_replies'] );
	}

	public function testHandlesInvalidSinceGracefully(): void {
		$controller = new FakeNotificationController();
		$request    = new WP_REST_Request();
		$request->set_param( 'since', 'not-a-number' );

		$response = $controller->since( $request );

		self::assertSame( 200, $response->status );
		$data = $response->data;
		self::assertEmpty( $data['new_tickets'] );
		self::assertEmpty( $data['new_replies'] );
	}

	public function testHandlesMissingSinceGracefully(): void {
		$controller = new FakeNotificationController();
		$request    = new WP_REST_Request();

		$response = $controller->since( $request );

		self::assertSame( 200, $response->status );
		$data = $response->data;
		self::assertEmpty( $data['new_tickets'] );
		self::assertEmpty( $data['new_replies'] );
	}

	public function testHandlesZeroSinceGracefully(): void {
		$controller = new FakeNotificationController();
		$request    = new WP_REST_Request();
		$request->set_param( 'since', '0' );

		$response = $controller->since( $request );

		self::assertSame( 200, $response->status );
		$data = $response->data;
		self::assertEmpty( $data['new_tickets'] );
		self::assertEmpty( $data['new_replies'] );
	}

	public function testSuccessFlagAlwaysPresent(): void {
		$controller = new FakeNotificationController();
		$request    = new WP_REST_Request();
		$request->set_param( 'since', (string) time() );

		$response = $controller->since( $request );

		$data = $response->data;
		self::assertArrayHasKey( 'success', $data );
		self::assertTrue( $data['success'] );
	}

	public function testToIso8601ConvertsDateCorrectly(): void {
		$controller = new class extends NotificationController {
			public function callToIso8601( string $dt ): string {
				return $this->toIso8601( $dt );
			}

			// These are no-ops in the unit test context.
			protected function fetchNewTickets( int $since ): array {
				return array();
			}

			protected function fetchNewReplies( int $since ): array {
				return array();
			}
		};

		$result = $controller->callToIso8601( '2026-08-24 20:00:00' );
		self::assertSame( '2026-08-24T20:00:00Z', $result );
	}

	public function testAgentFacingStorageStatusesIncludesNew(): void {
		$controller = new FakeNotificationController();
		$statuses   = $controller->getAgentFacingStorageStatuses();

		self::assertContains( 'new', $statuses );
	}

	public function testAgentFacingStorageStatusesIncludesPendingAgentReplyValues(): void {
		$controller = new FakeNotificationController();
		$statuses   = $controller->getAgentFacingStorageStatuses();

		// All legacy storage values that map to CANONICAL_PENDING_AGENT_REPLY must be included.
		self::assertContains( 'in_progress', $statuses );
		self::assertContains( 'triaged', $statuses );
		self::assertContains( 'pending', $statuses );
	}

	public function testAgentFacingStorageStatusesExcludesClientFacingStatuses(): void {
		$controller = new FakeNotificationController();
		$statuses   = $controller->getAgentFacingStorageStatuses();

		// Tickets waiting on the client must never trigger agent notifications.
		self::assertNotContains( 'waiting_customer', $statuses );
		self::assertNotContains( 'pending_client_reply', $statuses );
		self::assertNotContains( 'resolved', $statuses );
		self::assertNotContains( 'closed', $statuses );
	}
}
