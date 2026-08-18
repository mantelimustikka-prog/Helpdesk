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
		$this->provider = $provider ?? new NullKnowledgeBaseProvider();
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
		if ( '' === trim( $query ) ) {
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
