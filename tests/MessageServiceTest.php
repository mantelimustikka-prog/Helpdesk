<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use WPHelpdesk\Domain\Message\MessageRepository;
use WPHelpdesk\Domain\Message\MessageService;

require_once __DIR__ . '/bootstrap.php';

final class MessageServiceTest extends TestCase {

	protected function setUp(): void {
		// Ensure capability checks return false by default.
		$GLOBALS['wp_current_user_caps'] = [];
	}

	public function testPostReplyRejectsEmptyBody(): void {
		$repository = new class extends MessageRepository {
			public function create( array $data ): int {
				return 1;
			}
		};

		$service = $this->makeService( $repository );

		self::assertSame( 0, $service->postReply( 1, '   ' ) );
	}

	public function testPostReplyRejectsZeroTicketId(): void {
		$repository = new class extends MessageRepository {
			public function create( array $data ): int {
				return 1;
			}
		};

		$service = $this->makeService( $repository );

		self::assertSame( 0, $service->postReply( 0, 'Hello' ) );
	}

	public function testPostReplySanitizesUnknownAuthorType(): void {
		$repository = new class extends MessageRepository {
			public array $last_insert = [];

			public function create( array $data ): int {
				$this->last_insert = $data;
				return 7;
			}
		};

		$service = $this->makeService( $repository );

		$id = $service->postReply( 2, 'A reply', 'robot' );

		self::assertSame( 7, $id );
		self::assertSame( 'agent', $repository->last_insert['author_type'] );
	}

	public function testPostReplyStoresCorrectFields(): void {
		$repository = new class extends MessageRepository {
			public array $last_insert = [];

			public function create( array $data ): int {
				$this->last_insert = $data;
				return 10;
			}
		};

		$service = $this->makeService( $repository );
		$id      = $service->postReply( 5, 'Customer reply', 'member', 42, false );

		self::assertSame( 10, $id );
		self::assertSame( 5, $repository->last_insert['ticket_id'] );
		self::assertSame( 42, $repository->last_insert['author_user_id'] );
		self::assertSame( 'member', $repository->last_insert['author_type'] );
		self::assertSame( 0, $repository->last_insert['is_internal'] );
	}

	public function testPostReplyFiresActionForNonInternalMessage(): void {
		$fired = false;

		$GLOBALS['wp_filters']['hd_ticket_reply_posted'][] = static function () use ( &$fired ): void {
			$fired = true;
		};

		$repository = new class extends MessageRepository {
			public function create( array $data ): int {
				return 3;
			}
		};

		$service = $this->makeService( $repository );
		$service->postReply( 1, 'Hello', 'agent', 1, false );

		self::assertTrue( $fired );

		unset( $GLOBALS['wp_filters']['hd_ticket_reply_posted'] );
	}

	public function testPostReplyDoesNotFireActionForInternalNote(): void {
		$fired = false;

		$GLOBALS['wp_filters']['hd_ticket_reply_posted'][] = static function () use ( &$fired ): void {
			$fired = true;
		};

		$repository = new class extends MessageRepository {
			public function create( array $data ): int {
				return 4;
			}
		};

		$service = $this->makeService( $repository );
		$service->postReply( 1, 'Internal note', 'agent', 1, true );

		self::assertFalse( $fired );

		unset( $GLOBALS['wp_filters']['hd_ticket_reply_posted'] );
	}

	public function testDeleteMessageReturnsFalseForMissingMessage(): void {
		$repository = new class extends MessageRepository {
			public function find( int $id ): ?array {
				return null;
			}
		};

		$service = $this->makeService( $repository );

		self::assertFalse( $service->deleteMessage( 99, 1 ) );
	}

	public function testDeleteMessageReturnsFalseForNonOwner(): void {
		$repository = new class extends MessageRepository {
			public function find( int $id ): ?array {
				return [ 'id' => $id, 'author_user_id' => 5 ];
			}
		};

		$service = $this->makeService( $repository );

		// user_id 10 is not the author and doesn't have manage_tickets.
		self::assertFalse( $service->deleteMessage( 1, 10 ) );
	}

	public function testDeleteMessageSucceedsForOwner(): void {
		$deleted = false;

		$repository = new class( $deleted ) extends MessageRepository {
			public bool $deleted = false;

			public function find( int $id ): ?array {
				return [ 'id' => $id, 'author_user_id' => 5 ];
			}

			public function delete( int $id ): bool {
				$this->deleted = true;
				return true;
			}
		};

		$service = $this->makeService( $repository );

		$result = $service->deleteMessage( 1, 5 );

		self::assertTrue( $result );
		self::assertTrue( $repository->deleted );
	}

	public function testListMessagesDelegatesToRepository(): void {
		$repository = new class extends MessageRepository {
			public function list( int $ticket_id, array $args = [] ): array {
				return [
					[ 'id' => 1, 'body' => 'First' ],
					[ 'id' => 2, 'body' => 'Second' ],
				];
			}
		};

		$service  = $this->makeService( $repository );
		$messages = $service->listMessages( 5 );

		self::assertCount( 2, $messages );
		self::assertSame( 'First', $messages[0]['body'] );
	}

	public function testCountMessagesDelegatesToRepository(): void {
		$repository = new class extends MessageRepository {
			public function count( int $ticket_id, array $args = [] ): int {
				return 12;
			}
		};

		$service = $this->makeService( $repository );

		self::assertSame( 12, $service->countMessages( 3 ) );
	}

	private function makeService( MessageRepository $repository ): MessageService {
		$service = new MessageService();

		$prop = new ReflectionProperty( MessageService::class, 'repository' );
		$prop->setAccessible( true );
		$prop->setValue( $service, $repository );

		return $service;
	}
}
