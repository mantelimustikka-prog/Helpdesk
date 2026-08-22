<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use WPHelpdesk\Domain\Ticket\TicketLifecycleService;
use WPHelpdesk\Domain\Ticket\TicketStatus;

require_once __DIR__ . '/bootstrap.php';

/**
 * Testable subclass that captures updateStatus calls instead of hitting the DB.
 */
class TestableTicketLifecycleService extends TicketLifecycleService {
	/** @var array<int, array{ticket_id: int, status: string}> */
	public array $updatedStatuses = array();

	protected function updateStatus( int $ticket_id, string $status ): bool {
		$this->updatedStatuses[] = array(
			'ticket_id' => $ticket_id,
			'status'    => $status,
		);
		return true;
	}
}

final class TicketLifecycleServiceTest extends TestCase {

	private function makeService(): TestableTicketLifecycleService {
		return new TestableTicketLifecycleService();
	}

	private function ticket( string $status, int $id = 1 ): array {
		return array( 'id' => $id, 'status' => $status );
	}

	private function message( string $author_type, int $is_internal = 0 ): array {
		return array( 'author_type' => $author_type, 'is_internal' => $is_internal );
	}

	// -----------------------------------------------------------------------
	// Agent/admin reply transitions
	// -----------------------------------------------------------------------

	public function testAgentReplyOnNewTicketTransitionsToPendingClientReply(): void {
		$svc = $this->makeService();
		$svc->syncStatusAfterReply(
			$this->ticket( TicketStatus::CANONICAL_NEW ),
			$this->message( 'agent' )
		);

		self::assertCount( 1, $svc->updatedStatuses );
		self::assertSame( TicketStatus::CANONICAL_PENDING_CLIENT_REPLY, $svc->updatedStatuses[0]['status'] );
	}

	public function testAgentReplyOnPendingAgentReplyTransitionsToPendingClientReply(): void {
		$svc = $this->makeService();
		$svc->syncStatusAfterReply(
			$this->ticket( TicketStatus::CANONICAL_PENDING_AGENT_REPLY ),
			$this->message( 'agent' )
		);

		self::assertCount( 1, $svc->updatedStatuses );
		self::assertSame( TicketStatus::CANONICAL_PENDING_CLIENT_REPLY, $svc->updatedStatuses[0]['status'] );
	}

	// -----------------------------------------------------------------------
	// Client reply transitions
	// -----------------------------------------------------------------------

	public function testGuestReplyOnNewTicketTransitionsToPendingAgentReply(): void {
		$svc = $this->makeService();
		$svc->syncStatusAfterReply(
			$this->ticket( TicketStatus::CANONICAL_NEW ),
			$this->message( 'guest' )
		);

		self::assertCount( 1, $svc->updatedStatuses );
		self::assertSame( TicketStatus::CANONICAL_PENDING_AGENT_REPLY, $svc->updatedStatuses[0]['status'] );
	}

	public function testMemberReplyOnPendingClientReplyTransitionsToPendingAgentReply(): void {
		$svc = $this->makeService();
		$svc->syncStatusAfterReply(
			$this->ticket( TicketStatus::CANONICAL_PENDING_CLIENT_REPLY ),
			$this->message( 'member' )
		);

		self::assertCount( 1, $svc->updatedStatuses );
		self::assertSame( TicketStatus::CANONICAL_PENDING_AGENT_REPLY, $svc->updatedStatuses[0]['status'] );
	}

	public function testClientFollowUpReplyOnPendingAgentReplyTransitionsToPendingAgentReply(): void {
		// Client sends a follow-up when the ticket is already Pending Agent reply.
		$svc = $this->makeService();
		$svc->syncStatusAfterReply(
			$this->ticket( TicketStatus::CANONICAL_PENDING_AGENT_REPLY ),
			$this->message( 'guest' )
		);

		// Status is already correct — no unnecessary DB write.
		self::assertCount( 0, $svc->updatedStatuses );
	}

	// -----------------------------------------------------------------------
	// Terminal / guard cases
	// -----------------------------------------------------------------------

	public function testClosedTicketIsNeverTransitioned(): void {
		$svc = $this->makeService();
		$svc->syncStatusAfterReply(
			$this->ticket( TicketStatus::CANONICAL_CLOSED ),
			$this->message( 'agent' )
		);

		self::assertCount( 0, $svc->updatedStatuses );
	}

	public function testInternalNoteDoesNotTriggerTransition(): void {
		$svc = $this->makeService();
		$svc->syncStatusAfterReply(
			$this->ticket( TicketStatus::CANONICAL_NEW ),
			$this->message( 'agent', 1 )
		);

		self::assertCount( 0, $svc->updatedStatuses );
	}

	public function testEmptyAuthorTypeDoesNotTriggerTransition(): void {
		$svc = $this->makeService();
		$svc->syncStatusAfterReply(
			$this->ticket( TicketStatus::CANONICAL_NEW ),
			array( 'author_type' => '', 'is_internal' => 0 )
		);

		self::assertCount( 0, $svc->updatedStatuses );
	}

	public function testInvalidTicketIdDoesNotTriggerTransition(): void {
		$svc = $this->makeService();
		$svc->syncStatusAfterReply(
			array( 'id' => 0, 'status' => TicketStatus::CANONICAL_NEW ),
			$this->message( 'agent' )
		);

		self::assertCount( 0, $svc->updatedStatuses );
	}

	public function testClientReplyOnResolvedTicketTransitionsToPendingAgentReply(): void {
		// Client replies after the ticket was resolved — it should re-open for agent.
		$svc = $this->makeService();
		$svc->syncStatusAfterReply(
			$this->ticket( TicketStatus::CANONICAL_RESOLVED ),
			$this->message( 'guest' )
		);

		self::assertCount( 1, $svc->updatedStatuses );
		self::assertSame( TicketStatus::CANONICAL_PENDING_AGENT_REPLY, $svc->updatedStatuses[0]['status'] );
	}

	public function testAgentReplyAlreadyPendingClientReplyIsNoOp(): void {
		$svc = $this->makeService();
		$svc->syncStatusAfterReply(
			$this->ticket( TicketStatus::CANONICAL_PENDING_CLIENT_REPLY ),
			$this->message( 'agent' )
		);

		// Already in the target state — no DB write.
		self::assertCount( 0, $svc->updatedStatuses );
	}
}
