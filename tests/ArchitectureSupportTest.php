<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use WPHelpdesk\Domain\KnowledgeBase\KnowledgeBaseProviderInterface;
use WPHelpdesk\Domain\KnowledgeBase\KnowledgeBaseService;
use WPHelpdesk\Domain\KnowledgeBase\WordPressKnowledgeBaseProvider;
use WPHelpdesk\Interfaces\Frontend\FormDefinitionFactory;
use WPHelpdesk\Support\Constants;

require_once __DIR__ . '/bootstrap.php';

final class ArchitectureSupportTest extends TestCase {
	protected function setUp(): void {
		wp_helpdesk_test_reset_state();
	}

	public function testFormDefinitionFactoryExposesDeclarativeStepAndFieldMetadata(): void {
		$GLOBALS['wp_site_options'][ Constants::OPTION_GENERAL_REQUIRE_TOPIC ] = 0;

		$definitions = ( new FormDefinitionFactory() )->getDefinitions();

		self::assertArrayHasKey( 'guest', $definitions );
		self::assertSame( 'topic_selector', $definitions['guest']['fields']['topic_id']['type'] );
		self::assertFalse( $definitions['guest']['fields']['topic_id']['required'] );
		self::assertSame( 1, $definitions['guest']['next_step_map']['0']['continue'] );
	}

	public function testKnowledgeBaseServiceDelegatesExtendedProviderMethods(): void {
		$provider = new class implements KnowledgeBaseProviderInterface {
			public function searchTopics( string $query, array $topic_path = array(), int $limit = 5 ): array {
				return array( array( 'id' => 1, 'title' => 'Match ' . $query ) );
			}

			public function getTopicById( int|string $article_id ): ?array {
				return array( 'id' => (int) $article_id, 'title' => 'Article' );
			}

			public function suggestByPath( array $topic_path, string $query = '', int $limit = 5 ): array {
				return array( array( 'path' => $topic_path, 'query' => $query ) );
			}

			public function suggest( string $query, ?int $topic_id = null, int $limit = 5 ): array {
				return $this->searchTopics( $query, null === $topic_id ? array() : array( $topic_id ), $limit );
			}

			public function get( int|string $article_id ): ?array {
				return $this->getTopicById( $article_id );
			}
		};

		$service = new KnowledgeBaseService( $provider );

		self::assertSame( 'Match billing', $service->searchTopics( 'billing' )[0]['title'] );
		self::assertSame( 2, $service->getTopicById( 2 )['id'] );
		self::assertSame( 'reset', $service->suggestByPath( array( 'account' ), 'reset' )[0]['query'] );
	}

	public function testWordPressKnowledgeBaseProviderMapsSearchAndLookupResults(): void {
		$GLOBALS['wp_posts_index'] = array(
			10 => (object) array(
				'ID'           => 10,
				'post_title'   => 'Billing FAQ',
				'post_content' => 'Billing help article content',
				'post_excerpt' => '',
				'post_status'  => 'publish',
			),
			11 => (object) array(
				'ID'           => 11,
				'post_title'   => 'Draft Article',
				'post_content' => 'Draft',
				'post_excerpt' => '',
				'post_status'  => 'draft',
			),
		);

		$provider = new WordPressKnowledgeBaseProvider();

		$results = $provider->searchTopics( '', array( 'Billing' ), 5 );

		self::assertCount( 1, $results );
		self::assertSame( 10, $results[0]['id'] );
		self::assertSame( 'Billing FAQ', $provider->getTopicById( 10 )['title'] );
		self::assertNull( $provider->getTopicById( 11 ) );
		self::assertSame( 10, $provider->suggestByPath( array( 'Billing' ), '', 5 )[0]['id'] );
	}
}
