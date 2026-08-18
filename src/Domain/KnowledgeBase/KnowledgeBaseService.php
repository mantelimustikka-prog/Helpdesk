<?php
/**
 * @package WP Helpdesk
 */

namespace WPHelpdesk\Domain\KnowledgeBase;

/**
 * Application-layer façade for knowledge-base operations.
 *
 * Delegates to the configured provider so callers are decoupled from the
 * concrete integration.  Additional providers (e.g. BuddyPress Docs, a
 * custom CPT-based KB, or a remote Zendesk/Freshdesk integration) can be
 * swapped in by implementing KnowledgeBaseProviderInterface and passing the
 * instance to the constructor.
 */
class KnowledgeBaseService {
	protected KnowledgeBaseProviderInterface $provider;

	public function __construct( ?KnowledgeBaseProviderInterface $provider = null ) {
		$this->provider = $provider ?? new WordPressKnowledgeBaseProvider();
	}

	/**
	 * Search knowledge-base entries using optional topic path context.
	 *
	 * @param string            $query      Query string.
	 * @param array<int, mixed> $topic_path Optional topic path.
	 * @param int               $limit      Maximum results.
	 * @return array<int, array<string, mixed>>
	 */
	public function searchTopics( string $query, array $topic_path = array(), int $limit = 5 ): array {
		return $this->provider->searchTopics( $query, $topic_path, max( 1, $limit ) );
	}

	/**
	 * Fetch one knowledge-base entry by id.
	 *
	 * @param int|string $article_id Article id.
	 * @return array<string, mixed>|null
	 */
	public function getTopicById( int|string $article_id ): ?array {
		return $this->provider->getTopicById( $article_id );
	}

	/**
	 * Suggest entries from a topic path.
	 *
	 * @param array<int, mixed> $topic_path Topic path.
	 * @param string            $query      Optional query.
	 * @param int               $limit      Maximum results.
	 * @return array<int, array<string, mixed>>
	 */
	public function suggestByPath( array $topic_path, string $query = '', int $limit = 5 ): array {
		return $this->provider->suggestByPath( $topic_path, $query, max( 1, $limit ) );
	}

	/**
	 * Return article suggestions for a query and optional topic.
	 *
	 * @param string   $query    User's question / ticket subject.
	 * @param int|null $topic_id Optional topic context.
	 * @param int      $limit    Max results.
	 * @return array<int, array<string, mixed>>
	 */
	public function suggest( string $query, ?int $topic_id = null, int $limit = 5 ): array {
		if ( '' === trim( $query ) && null === $topic_id ) {
			return array();
		}

		return $this->provider->suggest( $query, $topic_id, max( 1, $limit ) );
	}

	/**
	 * Retrieve a single article by ID.
	 *
	 * @param int|string $article_id Provider article identifier.
	 * @return array<string, mixed>|null
	 */
	public function get( int|string $article_id ): ?array {
		return $this->provider->get( $article_id );
	}
}
