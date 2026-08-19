<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use WPHelpdesk\Domain\Message\MessageService;
use WPHelpdesk\Domain\Ticket\TicketRepository;
use WPHelpdesk\Interfaces\Frontend\WooCommerceAccountHelpdesk;

require_once __DIR__ . '/bootstrap.php';

final class WooCommerceAccountHelpdeskTest extends TestCase {
	private TicketRepositoryDouble $ticket_repository;
	private MessageServiceDouble $message_service;
	private WooCommerceAccountHelpdesk $integration;

	protected function setUp(): void {
		wp_helpdesk_test_reset_state();
		$GLOBALS['wp_current_user_caps']['hd_manage_tickets'] = false;
		$this->ticket_repository = new TicketRepositoryDouble();
		$this->message_service   = new MessageServiceDouble();
		$this->integration       = new WooCommerceAccountHelpdesk( $this->ticket_repository, $this->message_service );
	}

	public function testMenuIncludesHelpdeskBeforeLogout(): void {
		$items = array(
			'dashboard'       => 'Dashboard',
			'orders'          => 'Orders',
			'customer-logout' => 'Log out',
		);

		$updated = $this->integration->addMenuItem( $items );

		self::assertSame( array( 'dashboard', 'orders', 'helpdesk', 'customer-logout' ), array_keys( $updated ) );
		self::assertSame( 'Helpdesk', $updated['helpdesk'] );
	}

	public function testEndpointRegistrationAddsRewriteEndpointAndQueryVar(): void {
		$this->integration->addEndpoint();
		$vars = $this->integration->addQueryVars( array( 'paged' ) );

		self::assertSame( 'helpdesk', $GLOBALS['wp_rewrite_endpoints'][0]['name'] );
		self::assertContains( 'helpdesk', $vars );
	}

	public function testRenderOverviewShowsEmptyStateCta(): void {
		$GLOBALS['wp_query_vars']['helpdesk'] = '';

		ob_start();
		$this->integration->render();
		$output = (string) ob_get_clean();

		self::assertStringContainsString( 'My Account / Helpdesk', $output );
		self::assertStringContainsString( 'No requests yet', $output );
		self::assertStringContainsString( 'Create request', $output );
	}

	public function testRenderNewRequestSubviewShowsMemberFormEntryPoint(): void {
		$GLOBALS['wp_query_vars']['helpdesk'] = 'new';

		ob_start();
		$this->integration->render();
		$output = (string) ob_get_clean();

		self::assertStringContainsString( 'Open request form', $output );
		self::assertStringContainsString( '/helpdesk/member/new/', $output );
	}

	public function testRenderRequestsSubviewShowsOwnedTickets(): void {
		$GLOBALS['wp_query_vars']['helpdesk'] = 'requests';
		$this->ticket_repository->tickets     = array(
			array(
				'id'         => 11,
				'ticket_no'  => 'HD-10011',
				'user_id'    => 7,
				'subject'    => 'Need billing help',
				'status'     => 'in_progress',
				'updated_at' => '2026-08-19 07:00:00',
			),
		);

		ob_start();
		$this->integration->render();
		$output = (string) ob_get_clean();

		self::assertStringContainsString( 'Need billing help', $output );
		self::assertStringContainsString( 'in_progress', $output );
		self::assertStringContainsString( '/my-account/helpdesk/request/HD-10011/', $output );
	}

	public function testRenderDetailShowsMessagesAndReplyFormForOwnedTicket(): void {
		$GLOBALS['wp_query_vars']['helpdesk'] = 'request/HD-10011';
		$this->ticket_repository->ticket      = array(
			'id'              => 11,
			'ticket_no'       => 'HD-10011',
			'user_id'         => 7,
			'requester_email' => 'agent@example.test',
			'subject'         => 'Need billing help',
			'status'          => 'waiting_customer',
		);
		$this->message_service->messages      = array(
			array(
				'id'          => 51,
				'author_type' => 'agent',
				'created_at'  => '2026-08-19 07:10:00',
				'body'        => 'We need one more detail.',
			),
		);

		ob_start();
		$this->integration->render();
		$output = (string) ob_get_clean();

		self::assertStringContainsString( 'Need billing help', $output );
		self::assertStringContainsString( 'waiting_customer', $output );
		self::assertStringContainsString( 'Support', $output );
		self::assertStringContainsString( 'Send a reply', $output );
	}

	public function testRenderDetailBlocksUnownedTicket(): void {
		$GLOBALS['wp_query_vars']['helpdesk'] = 'request/HD-10011';
		$this->ticket_repository->ticket      = array(
			'id'              => 11,
			'ticket_no'       => 'HD-10011',
			'user_id'         => 99,
			'requester_email' => 'other@example.test',
			'subject'         => 'Need billing help',
			'status'          => 'waiting_customer',
		);

		ob_start();
		$this->integration->render();
		$output = (string) ob_get_clean();

		self::assertStringContainsString( 'Request not found', $output );
	}

	public function testReplySubmissionPostsMemberMessageAndRedirects(): void {
		$GLOBALS['wp_query_vars']['helpdesk'] = 'request/HD-10011';
		$this->ticket_repository->ticket      = array(
			'id'              => 11,
			'ticket_no'       => 'HD-10011',
			'user_id'         => 7,
			'requester_email' => 'agent@example.test',
			'subject'         => 'Need billing help',
			'status'          => 'waiting_customer',
		);
		$this->message_service->reply_id      = 88;
		$_SERVER['REQUEST_METHOD']            = 'POST';
		$_POST = array(
			'hd_helpdesk_action'        => 'reply',
			'hd_my_account_reply_nonce' => 'valid-reply-nonce',
			'hd_helpdesk_reply_body'    => 'Here is my extra detail.',
		);
		$GLOBALS['wp_valid_nonces']['hd_my_account_reply'] = 'valid-reply-nonce';

		$this->integration->render();

		self::assertSame( 11, $this->message_service->posted_ticket_id );
		self::assertSame( 'member', $this->message_service->posted_author_type );
		self::assertSame( 'Here is my extra detail.', $this->message_service->posted_body );
		self::assertSame( 'https://example.test/my-account/helpdesk/request/HD-10011/', $GLOBALS['wp_safe_redirect_to'] );
	}

	public function testRenderRequiresAuthentication(): void {
		$GLOBALS['wp_logged_in']             = false;
		$GLOBALS['wp_query_vars']['helpdesk'] = '';

		ob_start();
		$this->integration->render();
		$output = (string) ob_get_clean();

		self::assertStringContainsString( 'Please sign in to access your helpdesk requests.', $output );
	}
}

final class TicketRepositoryDouble extends TicketRepository {
	/** @var array<int, array<string, mixed>> */
	public array $tickets = array();

	/** @var array<string, mixed>|null */
	public ?array $ticket = null;

	public function listForUser( int $network_id, int $user_id, string $email, array $args = array() ): array {
		return $this->tickets;
	}

	public function findByTicketNo( string $ticket_no, int $network_id ): ?array {
		return $this->ticket;
	}
}

final class MessageServiceDouble extends MessageService {
	/** @var array<int, array<string, mixed>> */
	public array $messages = array();
	public int $reply_id = 0;
	public int $posted_ticket_id = 0;
	public string $posted_body = '';
	public string $posted_author_type = '';

	public function listMessages( int $ticket_id, array $args = array() ): array {
		return $this->messages;
	}

	public function postReply(
		int $ticket_id,
		string $body,
		string $author_type = 'agent',
		?int $author_user_id = null,
		bool $is_internal = false
	): int {
		$this->posted_ticket_id   = $ticket_id;
		$this->posted_body        = $body;
		$this->posted_author_type = $author_type;

		return $this->reply_id;
	}

	public function getMessage( int $id ): ?array {
		return array(
			'id'          => $id,
			'author_type' => 'member',
			'body'        => $this->posted_body,
		);
	}
}
