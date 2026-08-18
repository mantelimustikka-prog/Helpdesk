<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use WPHelpdesk\Domain\Ticket\TicketRepository;
use WPHelpdesk\Domain\Ticket\TicketService;

require_once __DIR__ . '/bootstrap.php';

final class TicketServiceTest extends TestCase {

	public function testCreateTicketRejectsEmptySubject(): void {
		$repository = new class extends TicketRepository {
			public function create( array $data ): int {
				return 99;
			}
		};

		$service = $this->makeService( $repository );

		self::assertSame( 0, $service->createTicket( [ 'requester_email' => 'a@b.com', 'subject' => '  ' ] ) );
	}

	public function testCreateTicketRejectsInvalidEmail(): void {
		$repository = new class extends TicketRepository {
			public function create( array $data ): int {
				return 99;
			}
		};

		$service = $this->makeService( $repository );

		self::assertSame( 0, $service->createTicket( [ 'subject' => 'Help', 'requester_email' => 'notanemail' ] ) );
	}

	public function testCreateTicketSanitizesStatusAndPriority(): void {
		$repository = new class extends TicketRepository {
			public array $last_insert = [];

			public function create( array $data ): int {
				$this->last_insert = $data;
				return 1;
			}
		};

		$service = $this->makeService( $repository );

		$id = $service->createTicket(
			[
				'subject'         => 'Test ticket',
				'requester_email' => 'user@example.com',
				'status'          => 'invalid_status',
				'priority'        => 'mega',
			]
		);

		self::assertSame( 1, $id );
		self::assertSame( 'new', $repository->last_insert['status'] );
		self::assertSame( 'normal', $repository->last_insert['priority'] );
	}

	public function testCreateTicketAcceptsValidStatusAndPriority(): void {
		$repository = new class extends TicketRepository {
			public array $last_insert = [];

			public function create( array $data ): int {
				$this->last_insert = $data;
				return 5;
			}
		};

		$service = $this->makeService( $repository );

		$id = $service->createTicket(
			[
				'subject'         => 'Urgent issue',
				'requester_email' => 'admin@test.com',
				'status'          => 'in_progress',
				'priority'        => 'urgent',
			]
		);

		self::assertSame( 5, $id );
		self::assertSame( 'in_progress', $repository->last_insert['status'] );
		self::assertSame( 'urgent', $repository->last_insert['priority'] );
	}

	public function testUpdateTicketRejectsEmptySubject(): void {
		$repository = new class extends TicketRepository {
			public function find( int $id, int $network_id ): ?array {
				return [ 'id' => $id, 'subject' => 'Old subject', 'network_id' => 1 ];
			}
		};

		$service = $this->makeService( $repository );

		self::assertFalse( $service->updateTicket( 3, [ 'subject' => '' ] ) );
	}

	public function testUpdateTicketReturnsFalseForUnknownTicket(): void {
		$repository = new class extends TicketRepository {
			public function find( int $id, int $network_id ): ?array {
				return null;
			}
		};

		$service = $this->makeService( $repository );

		self::assertFalse( $service->updateTicket( 99, [ 'status' => 'resolved' ] ) );
	}

	public function testGetStatusCountsDelegatesToRepository(): void {
		$repository = new class extends TicketRepository {
			public function countByStatus( int $network_id, array $date_range = [] ): array {
				return [ 'new' => 5, 'resolved' => 2 ];
			}
		};

		$service = $this->makeService( $repository );
		$counts  = $service->getStatusCounts();

		self::assertSame( 5, $counts['new'] );
		self::assertSame( 2, $counts['resolved'] );
	}

	public function testGetPriorityCountsDelegatesToRepository(): void {
		$repository = new class extends TicketRepository {
			public function countByPriority( int $network_id, array $date_range = [] ): array {
				return [ 'high' => 3, 'normal' => 10 ];
			}
		};

		$service = $this->makeService( $repository );
		$counts  = $service->getPriorityCounts();

		self::assertSame( 3, $counts['high'] );
		self::assertSame( 10, $counts['normal'] );
	}

	public function testGetDailyMetricsDelegatesToRepository(): void {
		$repository = new class extends TicketRepository {
			public function countByDay( int $network_id, string $from, string $to ): array {
				return [ '2024-01-01' => 4, '2024-01-02' => 7 ];
			}
		};

		$service = $this->makeService( $repository );
		$metrics = $service->getDailyMetrics( '2024-01-01', '2024-01-07' );

		self::assertSame( 4, $metrics['2024-01-01'] );
		self::assertSame( 7, $metrics['2024-01-02'] );
	}

	public function testListTicketsDelegatesToRepository(): void {
		$repository = new class extends TicketRepository {
			public function list( int $network_id, array $args = [] ): array {
				return [
					[ 'id' => 1, 'subject' => 'First' ],
					[ 'id' => 2, 'subject' => 'Second' ],
				];
			}
		};

		$service = $this->makeService( $repository );
		$tickets = $service->listTickets();

		self::assertCount( 2, $tickets );
		self::assertSame( 'First', $tickets[0]['subject'] );
	}

	private function makeService( TicketRepository $repository ): TicketService {
		$service = new TicketService();

		$prop = new ReflectionProperty( TicketService::class, 'repository' );
		$prop->setAccessible( true );
		$prop->setValue( $service, $repository );

		$net_prop = new ReflectionProperty( TicketService::class, 'network_id' );
		$net_prop->setAccessible( true );
		$net_prop->setValue( $service, 1 );

		return $service;
	}
}
