<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use WPHelpdesk\Domain\Topic\TopicRepository;
use WPHelpdesk\Domain\Topic\TopicTransitionRepository;
use WPHelpdesk\Domain\Topic\TopicTransitionService;

require_once __DIR__ . '/bootstrap.php';

final class TopicTransitionServiceTest extends TestCase {

	public function testCreateTransitionRejectsIdenticalFromAndTo(): void {
		$service = $this->makeService( $this->stubTopicRepo( true ), new TopicTransitionRepository() );

		self::assertSame( 0, $service->createTransition( [ 'from_topic_id' => 1, 'to_topic_id' => 1, 'label' => 'Loop' ] ) );
	}

	public function testCreateTransitionRejectsUnknownFromTopic(): void {
		$topic_repo = new class extends TopicRepository {
			public function find( int $id, int $network_id ): ?array {
				return null;
			}
		};

		$service = $this->makeService( $topic_repo, new TopicTransitionRepository() );

		self::assertSame( 0, $service->createTransition( [ 'from_topic_id' => 1, 'to_topic_id' => 2, 'label' => 'Next' ] ) );
	}

	public function testCreateTransitionRejectsEmptyLabel(): void {
		$service = $this->makeService( $this->stubTopicRepo( true ), new TopicTransitionRepository() );

		self::assertSame( 0, $service->createTransition( [ 'from_topic_id' => 1, 'to_topic_id' => 2, 'label' => '' ] ) );
	}

	public function testCreateTransitionSucceedsAndNormalizesConditionType(): void {
		$transition_repo = new class extends TopicTransitionRepository {
			public array $last_insert = [];

			public function create( array $data ): int {
				$this->last_insert = $data;
				return 42;
			}
		};

		$service = $this->makeService( $this->stubTopicRepo( true ), $transition_repo );

		$id = $service->createTransition(
			[
				'from_topic_id'  => 1,
				'to_topic_id'    => 2,
				'label'          => 'Billing issue',
				'condition_type' => 'unknown_type',
			]
		);

		self::assertSame( 42, $id );
		self::assertSame( 'always', $transition_repo->last_insert['condition_type'] );
	}

	public function testResolveNextTopicPicksFirstMatchingAlwaysCondition(): void {
		$transition_repo = new class extends TopicTransitionRepository {
			public function listFrom( int $from_topic_id, int $network_id, bool $active_only = true ): array {
				return [
					[ 'to_topic_id' => 5, 'condition_type' => 'always', 'condition_value' => null, 'sort_order' => 0 ],
					[ 'to_topic_id' => 6, 'condition_type' => 'always', 'condition_value' => null, 'sort_order' => 1 ],
				];
			}
		};

		$service = $this->makeService( $this->stubTopicRepo( true ), $transition_repo );

		self::assertSame( 5, $service->resolveNextTopic( 1 ) );
	}

	public function testResolveNextTopicEvaluatesFieldEqualsCondition(): void {
		$transition_repo = new class extends TopicTransitionRepository {
			public function listFrom( int $from_topic_id, int $network_id, bool $active_only = true ): array {
				return [
					[ 'to_topic_id' => 10, 'condition_type' => 'field_equals', 'condition_value' => 'category:billing', 'sort_order' => 0 ],
					[ 'to_topic_id' => 11, 'condition_type' => 'always', 'condition_value' => null, 'sort_order' => 1 ],
				];
			}
		};

		$service = $this->makeService( $this->stubTopicRepo( true ), $transition_repo );

		self::assertSame( 10, $service->resolveNextTopic( 1, [ 'category' => 'billing' ] ) );
		self::assertSame( 11, $service->resolveNextTopic( 1, [ 'category' => 'other' ] ) );
	}

	public function testResolveNextTopicReturnsNullWhenNoMatch(): void {
		$transition_repo = new class extends TopicTransitionRepository {
			public function listFrom( int $from_topic_id, int $network_id, bool $active_only = true ): array {
				return [
					[ 'to_topic_id' => 7, 'condition_type' => 'field_equals', 'condition_value' => 'type:premium', 'sort_order' => 0 ],
				];
			}
		};

		$service = $this->makeService( $this->stubTopicRepo( true ), $transition_repo );

		self::assertNull( $service->resolveNextTopic( 1, [ 'type' => 'basic' ] ) );
	}

	public function testResolveNextTopicReturnsNullForFinalSourceTopic(): void {
		$topic_repo = new class extends TopicRepository {
			public function find( int $id, int $network_id ): ?array {
				return [ 'id' => $id, 'is_final' => 1 ];
			}
		};

		$transition_repo = new class extends TopicTransitionRepository {
			public function listFrom( int $from_topic_id, int $network_id, bool $active_only = true ): array {
				return [
					[ 'to_topic_id' => 2, 'condition_type' => 'always', 'condition_value' => null, 'sort_order' => 0 ],
				];
			}
		};

		$service = $this->makeService( $topic_repo, $transition_repo );

		self::assertNull( $service->resolveNextTopic( 1 ) );
	}

	public function testResolveNextTopicSkipsBrokenAndSelfReferentialTransitions(): void {
		$topic_repo = new class extends TopicRepository {
			public function find( int $id, int $network_id ): ?array {
				return match ( $id ) {
					1 => [ 'id' => 1, 'is_final' => 0 ],
					3 => [ 'id' => 3, 'is_final' => 0, 'is_active' => 1 ],
					default => null,
				};
			}

			public function findMany( array $ids, int $network_id ): array {
				return array_values(
					array_filter(
						array_map( fn( int $id ): ?array => $this->find( $id, $network_id ), $ids )
					)
				);
			}
		};

		$transition_repo = new class extends TopicTransitionRepository {
			public function listFrom( int $from_topic_id, int $network_id, bool $active_only = true ): array {
				return [
					[ 'to_topic_id' => 1, 'condition_type' => 'always', 'condition_value' => null, 'sort_order' => 0 ],
					[ 'to_topic_id' => 2, 'condition_type' => 'always', 'condition_value' => null, 'sort_order' => 1 ],
					[ 'to_topic_id' => 3, 'condition_type' => 'always', 'condition_value' => null, 'sort_order' => 2 ],
				];
			}
		};

		$service = $this->makeService( $topic_repo, $transition_repo );

		self::assertSame( 3, $service->resolveNextTopic( 1 ) );
	}

	public function testValidateBranchConfigurationRequiresValidNextTopics(): void {
		$topic_repo = new class extends TopicRepository {
			public function find( int $id, int $network_id ): ?array {
				return 9 === $id ? null : [ 'id' => $id, 'is_final' => 0, 'is_active' => 1 ];
			}
		};

		$service = $this->makeService( $topic_repo, new TopicTransitionRepository() );

		self::assertSame( 'branch-missing-transition', $service->validateBranchConfiguration( 4, false, [] ) );
		self::assertSame( 'invalid-transition', $service->validateBranchConfiguration( 4, false, [ 4 ] ) );
		self::assertSame( 'invalid-transition', $service->validateBranchConfiguration( 4, false, [ 9 ] ) );
		self::assertNull( $service->validateBranchConfiguration( 4, true, [] ) );
	}

	public function testSyncAdminNextTopicsCreatesAndRemovesManagedTransitions(): void {
		$topic_repo = new class extends TopicRepository {
			public function find( int $id, int $network_id ): ?array {
				return match ( $id ) {
					1 => [ 'id' => 1, 'title' => 'Start', 'is_final' => 0, 'is_active' => 1 ],
					2 => [ 'id' => 2, 'title' => 'Billing', 'is_final' => 0, 'is_active' => 1 ],
					3 => [ 'id' => 3, 'title' => 'Shipping', 'is_final' => 1, 'is_active' => 1 ],
					default => null,
				};
			}

			public function findMany( array $ids, int $network_id ): array {
				return array_values(
					array_filter(
						array_map( fn( int $id ): ?array => $this->find( $id, $network_id ), $ids )
					)
				);
			}
		};

		$transition_repo = new class extends TopicTransitionRepository {
			public array $created = array();
			public array $deleted = array();

			public function listFrom( int $from_topic_id, int $network_id, bool $active_only = true ): array {
				return array(
					array(
						'id'              => 8,
						'to_topic_id'     => 2,
						'label'           => 'Billing',
						'condition_type'  => 'always',
						'condition_value' => \WPHelpdesk\Domain\Topic\TopicTransitionService::ADMIN_TRANSITION_MARKER,
						'is_active'       => 1,
					),
				);
			}

			public function create( array $data ): int {
				$this->created[] = $data;
				return 25;
			}

			public function delete( int $id, int $network_id ): bool {
				$this->deleted[] = $id;
				return true;
			}
		};

		$service = $this->makeService( $topic_repo, $transition_repo );

		self::assertTrue( $service->syncAdminNextTopics( 1, [ 3 ] ) );
		self::assertSame( 8, $transition_repo->deleted[0] );
		self::assertSame( 3, $transition_repo->created[0]['to_topic_id'] );
	}

	public function testBranchTopicValidationRequiresAtLeastOneActiveTransition(): void {
		$topic_repo = new class extends TopicRepository {
			public function find( int $id, int $network_id ): ?array {
				return [ 'id' => $id, 'is_final' => 0 ];
			}
		};

		$transition_repo = new class extends TopicTransitionRepository {
			public function listFrom( int $from_topic_id, int $network_id, bool $active_only = true ): array {
				return [];
			}
		};

		$service = $this->makeService( $topic_repo, $transition_repo );

		self::assertFalse( $service->branchTopicHasValidTransitions( 1 ) );
	}

	public function testFinalTopicAlwaysPassesValidation(): void {
		$topic_repo = new class extends TopicRepository {
			public function find( int $id, int $network_id ): ?array {
				return [ 'id' => $id, 'is_final' => 1 ];
			}
		};

		$service = $this->makeService( $topic_repo, new TopicTransitionRepository() );

		self::assertTrue( $service->branchTopicHasValidTransitions( 5 ) );
	}

	public function testUpdateTransitionReturnsFalseForUnknown(): void {
		$transition_repo = new class extends TopicTransitionRepository {
			public function find( int $id, int $network_id ): ?array {
				return null;
			}
		};

		$service = $this->makeService( $this->stubTopicRepo( true ), $transition_repo );

		self::assertFalse( $service->updateTransition( 99, [ 'label' => 'New label' ] ) );
	}

	private function makeService( TopicRepository $topic_repo, TopicTransitionRepository $transition_repo ): TopicTransitionService {
		$service = new TopicTransitionService();

		$tp = new ReflectionProperty( TopicTransitionService::class, 'topic_repository' );
		$tp->setAccessible( true );
		$tp->setValue( $service, $topic_repo );

		$tr = new ReflectionProperty( TopicTransitionService::class, 'repository' );
		$tr->setAccessible( true );
		$tr->setValue( $service, $transition_repo );

		$np = new ReflectionProperty( TopicTransitionService::class, 'network_id' );
		$np->setAccessible( true );
		$np->setValue( $service, 1 );

		return $service;
	}

	private function stubTopicRepo( bool $exists ): TopicRepository {
		return new class( $exists ) extends TopicRepository {
			public function __construct( private readonly bool $exists ) {}

			public function find( int $id, int $network_id ): ?array {
				return $this->exists ? [ 'id' => $id, 'is_final' => 0 ] : null;
			}

			public function findMany( array $ids, int $network_id ): array {
				if ( ! $this->exists ) {
					return array();
				}

				return array_map(
					static fn( int $id ): array => [ 'id' => $id, 'is_final' => 0 ],
					$ids
				);
			}
		};
	}
}
