<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use WPHelpdesk\Domain\KnowledgeBase\KnowledgeBaseProviderInterface;
use WPHelpdesk\Domain\KnowledgeBase\KnowledgeBaseService;
use WPHelpdesk\Domain\Topic\TopicService;
use WPHelpdesk\Domain\Topic\TopicTransitionService;
use WPHelpdesk\Interfaces\Rest\PublicTicketController;

require_once __DIR__ . '/bootstrap.php';

final class PublicTicketControllerTest extends TestCase {
	protected function setUp(): void {
		wp_helpdesk_test_reset_state();
	}

	public function testListTransitionsReturnsOnlyValidRuntimeTransitions(): void {
		$topic_service = new class extends TopicService {
			public function getTopic( int $id ): ?array {
				return array(
					'id'          => $id,
					'name'        => 3 === $id ? 'Shipping' : 'Billing',
					'description' => 'Topic description',
					'is_final'    => 3 === $id ? 1 : 0,
				);
			}
		};

		$transition_service = new class extends TopicTransitionService {
			public function listValidFrom( int $from_topic_id, bool $active_only = true ): array {
				return array(
					array( 'to_topic_id' => 3, 'label' => 'Shipping' ),
				);
			}
		};

		$controller = new PublicTicketController( $topic_service, $transition_service, new KnowledgeBaseService() );
		$request    = new WP_REST_Request();
		$request['id'] = 1;

		$response = $controller->listTransitions( $request );

		self::assertSame( 200, $response->status );
		self::assertSame( 3, $response->data[0]['to_topic_id'] );
		self::assertSame( 1, $response->data[0]['to_topic']['is_final'] );
	}

	public function testSuggestKnowledgeBaseDelegatesToProvider(): void {
		$provider = new class implements KnowledgeBaseProviderInterface {
			public function searchTopics( string $query, array $topic_path = array(), int $limit = 5 ): array {
				return array();
			}

			public function getTopicById( int|string $article_id ): ?array {
				return null;
			}

			public function suggestByPath( array $topic_path, string $query = '', int $limit = 5 ): array {
				return array( array( 'topic_path' => $topic_path, 'query' => $query ) );
			}

			public function suggest( string $query, ?int $topic_id = null, int $limit = 5 ): array {
				return array();
			}

			public function get( int|string $article_id ): ?array {
				return null;
			}
		};

		$controller = new PublicTicketController( new TopicService(), new TopicTransitionService(), new KnowledgeBaseService( $provider ) );
		$request    = new WP_REST_Request();
		$request->set_param( 'query', 'billing' );
		$request->set_param( 'topic_path', array( 2, 4 ) );

		$response = $controller->suggestKnowledgeBase( $request );

		self::assertSame( 200, $response->status );
		self::assertSame( array( 2, 4 ), $response->data[0]['topic_path'] );
		self::assertSame( 'billing', $response->data[0]['query'] );
	}
}
