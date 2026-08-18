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
