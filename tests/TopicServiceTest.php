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
		};

		$service = new TopicService();
		$this->injectRepository( $service, $repository );

		$topics = $service->listTopics();

		self::assertSame( 'Account Access', $topics[0]['name'] );
		self::assertSame( 'account-access', $topics[0]['slug'] );
		self::assertSame( 'branch', $topics[0]['node_type'] );
		self::assertTrue( $topics[0]['graph_is_valid'] );
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

	private function injectRepository( TopicService $service, TopicRepository $repository ): void {
		$repository_property = new ReflectionProperty( TopicService::class, 'repository' );
		$repository_property->setAccessible( true );
		$repository_property->setValue( $service, $repository );

		$network_property = new ReflectionProperty( TopicService::class, 'network_id' );
		$network_property->setAccessible( true );
		$network_property->setValue( $service, 1 );
	}
}
