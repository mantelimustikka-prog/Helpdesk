<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use WPHelpdesk\Domain\Session\SubmissionSessionRepository;
use WPHelpdesk\Domain\Session\SubmissionSessionService;

require_once __DIR__ . '/bootstrap.php';

final class SubmissionSessionServiceTest extends TestCase {

	public function testStartCreatesSessionAndReturnsRow(): void {
		$repository = new class extends SubmissionSessionRepository {
			public array $created   = [];
			public int   $next_id   = 10;

			public function create( array $data ): int {
				$this->created = $data;
				return $this->next_id;
			}

			public function find( int $id ): ?array {
				return array_merge( $this->created, [ 'id' => $id ] );
			}
		};

		$service = $this->makeService( $repository );

		$session = $service->start( 'guest', [ 'topic_id' => 3 ], null, 1, 30 );

		self::assertNotNull( $session );
		self::assertSame( 'guest', $session['form_type'] );
		self::assertSame( 3, $session['current_topic_id'] );
		self::assertSame( 1, $session['network_id'] );
		self::assertSame( 0, $session['step_index'] );
		// Token must be a 64-char hex string.
		self::assertRegExp( '/^[0-9a-f]{64}$/', $session['session_token'] );
	}

	public function testResumeReturnsNullForMissingToken(): void {
		$repository = new class extends SubmissionSessionRepository {
			public function findByToken( string $token ): ?array {
				return null;
			}
		};

		$service = $this->makeService( $repository );

		self::assertNull( $service->resume( 'nonexistent' ) );
	}

	public function testResumeReturnsNullAndDeletesExpiredSession(): void {
		$deleted = false;

		$repository = new class extends SubmissionSessionRepository {
			public bool $deleted = false;

			public function findByToken( string $token ): ?array {
				return [
					'id'          => 1,
					'session_token' => $token,
					'expires_at'  => '2000-01-01 00:00:00',
					'payload_json' => '{}',
				];
			}

			public function deleteByToken( string $token ): bool {
				$this->deleted = true;
				return true;
			}
		};

		$service = $this->makeService( $repository );

		$result = $service->resume( 'old-token' );

		self::assertNull( $result );
		self::assertTrue( $repository->deleted );
	}

	public function testResumeReturnsValidSession(): void {
		$repository = new class extends SubmissionSessionRepository {
			public function findByToken( string $token ): ?array {
				return [
					'id'           => 5,
					'session_token' => $token,
					'expires_at'   => date( 'Y-m-d H:i:s', strtotime( '+1 hour' ) ),
					'payload_json' => '{"topic_id":2}',
					'step_index'   => 1,
				];
			}
		};

		$service = $this->makeService( $repository );
		$session = $service->resume( 'valid-token' );

		self::assertNotNull( $session );
		self::assertSame( 5, $session['id'] );
		self::assertSame( 1, $session['step_index'] );
	}

	public function testAdvanceReturnsFalseForExpiredSession(): void {
		$repository = new class extends SubmissionSessionRepository {
			public function findByToken( string $token ): ?array {
				return [
					'id'           => 2,
					'session_token' => $token,
					'expires_at'   => '2000-01-01 00:00:00',
					'payload_json' => '{}',
				];
			}

			public function deleteByToken( string $token ): bool {
				return true;
			}
		};

		$service = $this->makeService( $repository );

		self::assertFalse( $service->advance( 'expired-token', 2, 4, [ 'name' => 'Alice' ] ) );
	}

	public function testAdvanceMergesPayloadAndUpdatesStep(): void {
		$updated = [];

		$repository = new class( $updated ) extends SubmissionSessionRepository {
			public array $updated = [];

			public function findByToken( string $token ): ?array {
				return [
					'id'           => 3,
					'session_token' => $token,
					'expires_at'   => date( 'Y-m-d H:i:s', strtotime( '+1 hour' ) ),
					'payload_json' => '{"topic_id":1}',
					'step_index'   => 0,
				];
			}

			public function deleteByToken( string $token ): bool {
				return true;
			}

			public function updateByToken( string $token, array $data ): bool {
				$this->updated = $data;
				return true;
			}
		};

		$service = $this->makeService( $repository );

		$result = $service->advance( 'ok-token', 1, 2, [ 'name' => 'Bob' ] );

		self::assertTrue( $result );
		self::assertSame( 1, $repository->updated['step_index'] );

		$merged = json_decode( $repository->updated['payload_json'], true );
		self::assertSame( 1, $merged['topic_id'] );
		self::assertSame( 'Bob', $merged['name'] );
	}

	public function testGetPayloadDecodesJson(): void {
		$service  = $this->makeService( new SubmissionSessionRepository() );
		$payload  = $service->getPayload( [ 'payload_json' => '{"a":1,"b":"hello"}' ] );

		self::assertSame( 1, $payload['a'] );
		self::assertSame( 'hello', $payload['b'] );
	}

	public function testGetPayloadReturnsEmptyArrayOnMissingJson(): void {
		$service = $this->makeService( new SubmissionSessionRepository() );

		self::assertSame( [], $service->getPayload( [] ) );
		self::assertSame( [], $service->getPayload( [ 'payload_json' => '' ] ) );
	}

	public function testCleanupExpiredDelegatesToRepository(): void {
		$repository = new class extends SubmissionSessionRepository {
			public function deleteExpired(): int {
				return 7;
			}
		};

		$service = $this->makeService( $repository );

		self::assertSame( 7, $service->cleanupExpired() );
	}

	public function testRestartReturnsFalseForExpiredSession(): void {
		$repository = new class extends SubmissionSessionRepository {
			public function findByToken( string $token ): ?array {
				return [
					'id'           => 9,
					'session_token' => $token,
					'expires_at'   => '2000-01-01 00:00:00',
					'payload_json' => '{"topic_id":5}',
					'step_index'   => 2,
				];
			}

			public function deleteByToken( string $token ): bool {
				return true;
			}
		};

		$service = $this->makeService( $repository );

		self::assertFalse( $service->restart( 'old-token' ) );
	}

	public function testRestartReturnsFalseForMissingSession(): void {
		$repository = new class extends SubmissionSessionRepository {
			public function findByToken( string $token ): ?array {
				return null;
			}
		};

		$service = $this->makeService( $repository );

		self::assertFalse( $service->restart( 'no-such-token' ) );
	}

	public function testRestartResetsStepAndClearsTopicAndPayload(): void {
		$repository = new class extends SubmissionSessionRepository {
			public array $updated = [];

			public function findByToken( string $token ): ?array {
				return [
					'id'               => 4,
					'session_token'    => $token,
					'expires_at'       => date( 'Y-m-d H:i:s', strtotime( '+1 hour' ) ),
					'payload_json'     => '{"topic_id":3,"name":"Alice","message":"Help!"}',
					'step_index'       => 2,
					'reset_counter'    => 0,
					'current_topic_id' => 3,
				];
			}

			public function updateByToken( string $token, array $data ): bool {
				$this->updated = $data;
				return true;
			}
		};

		$service = $this->makeService( $repository );

		$result = $service->restart( 'active-token' );

		self::assertSame( 1, $result );
		self::assertSame( 0, $repository->updated['step_index'] );
		self::assertSame( 1, $repository->updated['reset_counter'] );
		self::assertNull( $repository->updated['current_topic_id'] );
		$payload = json_decode( $repository->updated['payload_json'], true );
		self::assertSame( [], $payload );
	}

	public function testRestartAllowsNewTopicSelectionAfterReset(): void {
		$repository = new class extends SubmissionSessionRepository {
			public array $calls = [];

			public function findByToken( string $token ): ?array {
				// First call: active session with previous topic.
				// Subsequent calls (advance): return the updated session with step_index=0.
				if ( empty( $this->calls ) ) {
					$this->calls[] = 'first';
					return [
						'id'               => 6,
						'session_token'    => $token,
						'expires_at'       => date( 'Y-m-d H:i:s', strtotime( '+1 hour' ) ),
						'payload_json'     => '{"topic_id":1}',
						'step_index'       => 1,
						'reset_counter'    => 0,
						'current_topic_id' => 1,
					];
				}
				// After restart the session has step_index=0 and no topic.
				return [
					'id'               => 6,
					'session_token'    => $token,
					'expires_at'       => date( 'Y-m-d H:i:s', strtotime( '+1 hour' ) ),
					'payload_json'     => '{}',
					'step_index'       => 0,
					'reset_counter'    => 1,
					'current_topic_id' => null,
				];
			}

			public function updateByToken( string $token, array $data ): bool {
				$this->calls[] = $data;
				return true;
			}
		};

		$service = $this->makeService( $repository );

		// Simulate restart: returns new reset_counter (1).
		$new_counter = $service->restart( 'session-abc' );
		self::assertSame( 1, $new_counter );

		// After restart, advance with a completely different topic should work.
		$advanced = $service->advance( 'session-abc', 1, 99, [ 'topic_id' => 99, 'name' => 'Bob' ] );
		self::assertTrue( $advanced );

		// Verify the advance payload contains the NEW topic only (no contamination from topic_id=1).
		$advance_update = end( $repository->calls );
		$merged         = json_decode( $advance_update['payload_json'], true );
		self::assertSame( 99, $merged['topic_id'] );
		self::assertSame( 99, $advance_update['current_topic_id'] );
	}

	// ------------------------------------------------------------------
	// reset_counter tests
	// ------------------------------------------------------------------

	public function testRestartIncrementsResetCounter(): void {
		$repository = new class extends SubmissionSessionRepository {
			public array $updated = [];

			public function findByToken( string $token ): ?array {
				return [
					'id'            => 11,
					'session_token' => $token,
					'expires_at'    => date( 'Y-m-d H:i:s', strtotime( '+1 hour' ) ),
					'payload_json'  => '{"topic_id":5}',
					'step_index'    => 2,
					'reset_counter' => 3,
				];
			}

			public function updateByToken( string $token, array $data ): bool {
				$this->updated = $data;
				return true;
			}
		};

		$service     = $this->makeService( $repository );
		$new_counter = $service->restart( 'token-inc' );

		self::assertSame( 4, $new_counter );
		self::assertSame( 4, $repository->updated['reset_counter'] );
		self::assertSame( 0, $repository->updated['step_index'] );
	}

	public function testRestartReturnsIncrementedCounterStartingFromZero(): void {
		$repository = new class extends SubmissionSessionRepository {
			public function findByToken( string $token ): ?array {
				return [
					'id'            => 12,
					'session_token' => $token,
					'expires_at'    => date( 'Y-m-d H:i:s', strtotime( '+1 hour' ) ),
					'payload_json'  => '{}',
					'step_index'    => 1,
					// reset_counter absent (legacy row): treated as 0.
				];
			}

			public function updateByToken( string $token, array $data ): bool {
				return true;
			}
		};

		$service = $this->makeService( $repository );
		self::assertSame( 1, $service->restart( 'token-zero' ) );
	}

	public function testAdvanceIsRejectedWhenResetCounterIsStale(): void {
		$repository = new class extends SubmissionSessionRepository {
			public function findByToken( string $token ): ?array {
				return [
					'id'            => 13,
					'session_token' => $token,
					'expires_at'    => date( 'Y-m-d H:i:s', strtotime( '+1 hour' ) ),
					'payload_json'  => '{}',
					'step_index'    => 0,
					'reset_counter' => 2,
				];
			}

			public function updateByToken( string $token, array $data ): bool {
				return true;
			}
		};

		$service = $this->makeService( $repository );

		// Client sends reset_counter=1, but server has 2 → stale → rejected.
		$result = $service->advance( 'token-stale', 1, 5, [ 'name' => 'Stale' ], 60, 1 );

		self::assertFalse( $result );
	}

	public function testAdvanceSucceedsWhenResetCounterMatches(): void {
		$repository = new class extends SubmissionSessionRepository {
			public array $updated = [];

			public function findByToken( string $token ): ?array {
				return [
					'id'            => 14,
					'session_token' => $token,
					'expires_at'    => date( 'Y-m-d H:i:s', strtotime( '+1 hour' ) ),
					'payload_json'  => '{}',
					'step_index'    => 0,
					'reset_counter' => 2,
				];
			}

			public function updateByToken( string $token, array $data ): bool {
				$this->updated = $data;
				return true;
			}
		};

		$service = $this->makeService( $repository );

		// Matching reset_counter → should succeed.
		$result = $service->advance( 'token-fresh', 1, 5, [ 'name' => 'Alice' ], 60, 2 );

		self::assertTrue( $result );
		self::assertSame( 1, $repository->updated['step_index'] );
	}

	public function testAdvanceSucceedsWithoutResetCounterArgument(): void {
		$repository = new class extends SubmissionSessionRepository {
			public function findByToken( string $token ): ?array {
				return [
					'id'            => 15,
					'session_token' => $token,
					'expires_at'    => date( 'Y-m-d H:i:s', strtotime( '+1 hour' ) ),
					'payload_json'  => '{}',
					'step_index'    => 0,
					'reset_counter' => 5,
				];
			}

			public function updateByToken( string $token, array $data ): bool {
				return true;
			}
		};

		$service = $this->makeService( $repository );

		// Omitting reset_counter (null) bypasses the staleness check.
		$result = $service->advance( 'token-legacy', 1, 3, [ 'name' => 'Bob' ] );

		self::assertTrue( $result );
	}

	public function testRestartCounterIncreasesMonotonically(): void {
		$counter = 0;

		$repository = new class( $counter ) extends SubmissionSessionRepository {
			public int $counter;

			public function __construct( int $counter ) {
				$this->counter = $counter;
			}

			public function findByToken( string $token ): ?array {
				return [
					'id'            => 20,
					'session_token' => $token,
					'expires_at'    => date( 'Y-m-d H:i:s', strtotime( '+1 hour' ) ),
					'payload_json'  => '{}',
					'step_index'    => 1,
					'reset_counter' => $this->counter,
				];
			}

			public function updateByToken( string $token, array $data ): bool {
				// Simulate the DB applying the update.
				$this->counter = $data['reset_counter'];
				return true;
			}
		};

		$service = $this->makeService( $repository );

		$c1 = $service->restart( 'tok' );
		self::assertSame( 1, $c1 );

		$c2 = $service->restart( 'tok' );
		self::assertSame( 2, $c2 );

		self::assertGreaterThan( $c1, $c2 );
	}

	private function makeService( SubmissionSessionRepository $repository ): SubmissionSessionService {
		$service = new SubmissionSessionService();

		$prop = new ReflectionProperty( SubmissionSessionService::class, 'repository' );
		$prop->setAccessible( true );
		$prop->setValue( $service, $repository );

		$net_prop = new ReflectionProperty( SubmissionSessionService::class, 'network_id' );
		$net_prop->setAccessible( true );
		$net_prop->setValue( $service, 1 );

		return $service;
	}
}
