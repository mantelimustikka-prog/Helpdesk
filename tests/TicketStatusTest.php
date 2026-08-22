<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use WPHelpdesk\Domain\Ticket\TicketStatus;

require_once __DIR__ . '/bootstrap.php';

final class TicketStatusTest extends TestCase {
	public function testLegacyStorageValuesMapToCanonicalStatuses(): void {
		self::assertSame( TicketStatus::CANONICAL_PENDING_AGENT_REPLY, TicketStatus::toCanonical( 'triaged' ) );
		self::assertSame( TicketStatus::CANONICAL_PENDING_AGENT_REPLY, TicketStatus::toCanonical( 'in_progress' ) );
		self::assertSame( TicketStatus::CANONICAL_PENDING_CLIENT_REPLY, TicketStatus::toCanonical( 'waiting_customer' ) );
	}

	public function testCanonicalStatusesMapToStorageValues(): void {
		self::assertSame( 'new', TicketStatus::toStorage( TicketStatus::CANONICAL_NEW ) );
		self::assertSame( 'in_progress', TicketStatus::toStorage( TicketStatus::CANONICAL_PENDING_AGENT_REPLY ) );
		self::assertSame( 'waiting_customer', TicketStatus::toStorage( TicketStatus::CANONICAL_PENDING_CLIENT_REPLY ) );
		self::assertSame( 'resolved', TicketStatus::toStorage( TicketStatus::CANONICAL_RESOLVED ) );
		self::assertSame( 'closed', TicketStatus::toStorage( TicketStatus::CANONICAL_CLOSED ) );
	}
}
