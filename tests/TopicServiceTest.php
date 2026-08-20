<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use WPHelpdesk\Domain\Topic\TopicRepository;
use WPHelpdesk\Domain\Topic\TopicService;

require_once __DIR__ . '/bootstrap.php';

final class TopicServiceTest extends TestCase {
	public function testCreateTopicMapsNameToTitleAndGeneratesUniqueSlug(): void {
		$repository = new class extends TopicRepository {
			public array $created_data = array();

			public function findBySlug( string $slug, int $network_id ): ?array {
				return 'billing' === $slug ? array( 'id' => 12 ) : null;
			}

			public function create( array $data ): int {
				$this->created_data = $data;
				return 99;
			}
		};

		$service = new TopicService();
		$this->injectRepository( $service, $repository );

		$topic_id = $service->createTopic(
			array(
				'name'        => 'Billing',
				'description' => 'Invoices and payments',
				'node_type'   => 'final',
			)
		);

		self::assertSame( 99, $topic_id );
		self::assertSame( 'Billing', $repository->created_data['title'] );
		self::assertSame( 'billing-2', $repository->created_data['slug'] );
		self::assertSame( 1, $repository->created_data['is_final'] );
		self::assertSame( 1, $repository->created_data['network_id'] );
	}

	public function testCreateTopicNormalizesRootParentToNullWhenSubmitted(): void {
		$repository = new class extends TopicRepository {
			public array $created_data = array();

			public function findBySlug( string $slug, int $network_id ): ?array {
				return null;
			}

			public function create( array $data ): int {
				$this->created_data = $data;
				return 22;
			}
		};

		$service = new TopicService();
		$this->injectRepository( $service, $repository );

		$topic_id = $service->createTopic(
			array(
				'name'      => 'General',
				'type'      => 'ROOT',
				'parent_id' => '17',
			)
		);

		self::assertSame( 22, $topic_id );
		self::assertSame( 'root', $repository->created_data['type'] );
		self::assertNull( $repository->created_data['parent_id'] );
	}

	public function testCreateTopicPersistsFollowupParent(): void {
		$repository = new class extends TopicRepository {
			public array $created_data = array();

			public function findBySlug( string $slug, int $network_id ): ?array {
				return null;
			}

			public function create( array $data ): int {
				$this->created_data = $data;
				return 24;
			}
		};

		$service = new TopicService();
		$this->injectRepository( $service, $repository );

		$topic_id = $service->createTopic(
			array(
				'name'      => 'Invoices',
				'type'      => 'FOLLOWUP',
				'parent_id' => '9',
			)
		);

		self::assertSame( 24, $topic_id );
		self::assertSame( 'followup', $repository->created_data['type'] );
		self::assertSame( 9, $repository->created_data['parent_id'] );
	}

	public function testUpdateTopicRejectsEmptyName(): void {
		$repository = new class extends TopicRepository {
			public function find( int $id, int $network_id ): ?array {
				return array(
					'id'    => $id,
					'title' => 'Existing Topic',
					'slug'  => 'existing-topic',
				);
			}
		};

		$service = new TopicService();
		$this->injectRepository( $service, $repository );

		self::assertFalse( $service->updateTopic( 7, array( 'name' => '   ' ) ) );
	}

	public function testUpdateTopicPersistsNodeTypeChanges(): void {
		$repository = new class extends TopicRepository {
			public array $updated = array();

			public function find( int $id, int $network_id ): ?array {
				return array(
					'id'       => $id,
					'title'    => 'Existing Topic',
					'slug'     => 'existing-topic',
					'is_final' => 0,
				);
			}

			public function update( int $id, array $data, int $network_id ): bool {
				$this->updated = $data;
				return true;
			}
		};

		$service = new TopicService();
		$this->injectRepository( $service, $repository );

		self::assertTrue( $service->updateTopic( 7, array( 'node_type' => 'final' ) ) );
		self::assertSame( 1, $repository->updated['is_final'] );
	}

	public function testUpdateTopicChangingToRootClearsExistingParent(): void {
		$repository = new class extends TopicRepository {
			public array $updated = array();

			public function find( int $id, int $network_id ): ?array {
				return array(
					'id'        => $id,
					'title'     => 'Existing Topic',
					'slug'      => 'existing-topic',
					'type'      => 'followup',
					'parent_id' => 5,
				);
			}

			public function update( int $id, array $data, int $network_id ): bool {
				$this->updated = $data;
				return true;
			}
		};

		$service = new TopicService();
		$this->injectRepository( $service, $repository );

		self::assertTrue( $service->updateTopic( 7, array( 'type' => 'ROOT', 'parent_id' => '5' ) ) );
		self::assertSame( 'root', $repository->updated['type'] );
		self::assertNull( $repository->updated['parent_id'] );
	}

	public function testListTopicsAddsNameAlias(): void {
		$repository = new class extends TopicRepository {
			public function list( int $network_id, array $args = [] ): array {
				return array(
					array(
						'id'       => 3,
						'title'    => 'Account Access',
						'slug'     => 'account-access',
						'is_final' => 0,
					),
				);
			}

			public function countActiveTransitionsFromTopic( int $topic_id, int $network_id ): int {
				return 2;
			}

			public function getActiveTransitionCounts( array $topic_ids, int $network_id ): array {
				return array( 3 => 2 );
			}

			public function getActiveIncomingTransitionCounts( array $topic_ids, int $network_id ): array {
				return array();
			}
		};

		$service = new TopicService();
		$this->injectRepository( $service, $repository );

		$topics = $service->listTopics();

		self::assertSame( 'Account Access', $topics[0]['name'] );
		self::assertSame( 'account-access', $topics[0]['slug'] );
		self::assertSame( 'branch', $topics[0]['node_type'] );
		self::assertTrue( $topics[0]['graph_is_valid'] );
		self::assertSame( 'top_level', $topics[0]['hierarchy_type'] );
	}

	public function testListTopLevelTopicsUsesRepositoryTopLevelQuery(): void {
		$repository = new class extends TopicRepository {
			public function listActiveRootTopics( int $network_id ): array {
				// Return empty to trigger legacy fallback.
				return array();
			}

			public function listActiveTopLevel( int $network_id ): array {
				return array(
					array( 'id' => 1, 'title' => 'Billing', 'slug' => 'billing', 'is_final' => 0 ),
				);
			}

			public function getActiveTransitionCounts( array $topic_ids, int $network_id ): array {
				return array( 1 => 1 );
			}
		};

		$service = new TopicService();
		$this->injectRepository( $service, $repository );

		$topics = $service->listTopLevelTopics();

		self::assertCount( 1, $topics );
		self::assertSame( 1, $topics[0]['id'] );
		self::assertSame( 'top_level', $topics[0]['hierarchy_type'] );
	}

	public function testBranchTopicValidationRequiresAtLeastOneActiveTransition(): void {
		$repository = new class extends TopicRepository {
			public function find( int $id, int $network_id ): ?array {
				return array(
					'id'       => $id,
					'title'    => 'Billing',
					'is_final' => 0,
				);
			}

			public function countActiveTransitionsFromTopic( int $topic_id, int $network_id ): int {
				return 0;
			}
		};

		$service = new TopicService();
		$this->injectRepository( $service, $repository );

		self::assertFalse( $service->isBranchTopicValid( 8 ) );
	}

	public function testBuildTopicTreeCreatesOrderedHierarchyWithDepthAndChildCounts(): void {
		$service = new TopicService();

		$tree = $service->buildTopicTree(
			array(
				array( 'id' => 10, 'name' => 'Root', 'slug' => 'root', 'sort_order' => 1, 'is_active' => 1 ),
				array( 'id' => 11, 'name' => 'Child A', 'slug' => 'child-a', 'sort_order' => 2, 'is_active' => 1 ),
				array( 'id' => 12, 'name' => 'Child B', 'slug' => 'child-b', 'sort_order' => 3, 'is_active' => 1 ),
				array( 'id' => 13, 'name' => 'Grandchild', 'slug' => 'grandchild', 'sort_order' => 4, 'is_active' => 1 ),
			),
			array(
				11 => array( 10 ),
				12 => array( 10 ),
				13 => array( 11 ),
			)
		);

		self::assertCount( 1, $tree );
		self::assertSame( 10, $tree[0]['id'] );
		self::assertSame( 2, $tree[0]['child_count'] );
		self::assertSame( 1, $tree[0]['depth'] );
		self::assertSame( array( 11, 12 ), array_column( $tree[0]['children'], 'id' ) );
		self::assertSame( 2, $tree[0]['children'][0]['depth'] );
		self::assertSame( array( 10 ), $tree[0]['children'][0]['parent_topic_ids'] );
		self::assertSame( 3, $tree[0]['children'][0]['children'][0]['depth'] );
	}

	public function testBuildTopicTreeKeepsAncestorContextForSearchMatches(): void {
		$service = new TopicService();

		$tree = $service->buildTopicTree(
			array(
				array( 'id' => 1, 'name' => 'Billing', 'slug' => 'billing', 'sort_order' => 0, 'is_active' => 1 ),
				array( 'id' => 2, 'name' => 'Invoices', 'slug' => 'invoices', 'sort_order' => 1, 'is_active' => 1 ),
				array( 'id' => 3, 'name' => 'Shipping', 'slug' => 'shipping', 'sort_order' => 2, 'is_active' => 1 ),
			),
			array(
				2 => array( 1 ),
			),
			'invoice'
		);

		self::assertCount( 1, $tree );
		self::assertSame( 1, $tree[0]['id'] );
		self::assertFalse( $tree[0]['matches_search'] );
		self::assertCount( 1, $tree[0]['children'] );
		self::assertSame( 2, $tree[0]['children'][0]['id'] );
		self::assertTrue( $tree[0]['children'][0]['matches_search'] );
	}

	public function testValidateTypeConstraintsRootSkipsParentRequirement(): void {
		$service = new TopicService();
		$this->injectRepository( $service, new TopicRepository() );

		$error_code = $service->validateTypeConstraints( 'root', 5, 0 );
		self::assertNull( $error_code );
	}

	public function testValidateTypeConstraintsFollowupRequiresParent(): void {
		$service = new TopicService();
		$this->injectRepository( $service, new TopicRepository() );

		$error_code = $service->validateTypeConstraints( 'followup', null, 0 );
		self::assertSame( 'followup-missing-parent', $error_code );
	}

	public function testValidateTypeConstraintsRootWithNoParentIsValid(): void {
		$service = new TopicService();
		$this->injectRepository( $service, new TopicRepository() );

		$error_code = $service->validateTypeConstraints( 'root', null, 0 );
		self::assertNull( $error_code );
	}

	public function testValidateTypeConstraintsFollowupWithParentIsValid(): void {
		$repository = new class extends TopicRepository {
			public function find( int $id, int $network_id ): ?array {
				return array( 'id' => $id, 'title' => 'Parent', 'slug' => 'parent', 'parent_id' => null );
			}
		};

		$service = new TopicService();
		$this->injectRepository( $service, $repository );

		$error_code = $service->validateTypeConstraints( 'followup', 10, 0 );
		self::assertNull( $error_code );
	}

	public function testWouldCreateCycleDetectsDirectSelfReference(): void {
		$service = new TopicService();
		$this->injectRepository( $service, new TopicRepository() );

		self::assertTrue( $service->wouldCreateCycle( 5, 5 ) );
	}

	public function testWouldCreateCycleDetectsChainedCircularReference(): void {
		$repository = new class extends TopicRepository {
			public function find( int $id, int $network_id ): ?array {
				// Topic 2 has parent 1, topic 3 has parent 2.
				$map = array(
					1 => array( 'id' => 1, 'title' => 'T1', 'slug' => 't1', 'parent_id' => null ),
					2 => array( 'id' => 2, 'title' => 'T2', 'slug' => 't2', 'parent_id' => 1 ),
					3 => array( 'id' => 3, 'title' => 'T3', 'slug' => 't3', 'parent_id' => 2 ),
				);
				return $map[ $id ] ?? null;
			}
		};

		$service = new TopicService();
		$this->injectRepository( $service, $repository );

		// Making topic 1's parent = topic 3 would create a cycle (1 -> 3 -> 2 -> 1).
		self::assertTrue( $service->wouldCreateCycle( 1, 3 ) );
	}

	public function testWouldCreateCycleReturnsFalseForLegitimateParent(): void {
		$repository = new class extends TopicRepository {
			public function find( int $id, int $network_id ): ?array {
				$map = array(
					10 => array( 'id' => 10, 'title' => 'Root', 'slug' => 'root', 'parent_id' => null ),
				);
				return $map[ $id ] ?? null;
			}
		};

		$service = new TopicService();
		$this->injectRepository( $service, $repository );

		// Setting topic 20's parent = 10 is fine (no cycle).
		self::assertFalse( $service->wouldCreateCycle( 20, 10 ) );
	}

	public function testIsLeafTopicReturnsTrueWhenNoActiveChildren(): void {
		$repository = new class extends TopicRepository {
			public function hasActiveChildren( int $parent_id, int $network_id ): bool {
				return false;
			}
		};

		$service = new TopicService();
		$this->injectRepository( $service, $repository );

		self::assertTrue( $service->isLeafTopic( 42 ) );
	}

	public function testIsLeafTopicReturnsFalseWhenChildrenExist(): void {
		$repository = new class extends TopicRepository {
			public function hasActiveChildren( int $parent_id, int $network_id ): bool {
				return true;
			}
		};

		$service = new TopicService();
		$this->injectRepository( $service, $repository );

		self::assertFalse( $service->isLeafTopic( 42 ) );
	}


	private function injectRepository( TopicService $service, TopicRepository $repository ): void {
		$repository_property = new ReflectionProperty( TopicService::class, 'repository' );
		$repository_property->setAccessible( true );
		$repository_property->setValue( $service, $repository );

		$network_property = new ReflectionProperty( TopicService::class, 'network_id' );
		$network_property->setAccessible( true );
		$network_property->setValue( $service, 1 );
	}
}
