<?php
/**
 * @package WP Helpdesk
 */

namespace WPHelpdesk\Domain\KnowledgeBase;

/**
 * No-op KB provider used when no integration is configured.
 */
class NullKnowledgeBaseProvider implements KnowledgeBaseProviderInterface {
	/**
	 * @inheritDoc
	 */
	public function searchTopics( string $query, array $topic_path = array(), int $limit = 5 ): array {
		return array();
	}

	/**
	 * @inheritDoc
	 */
	public function getTopicById( int|string $article_id ): ?array {
		return null;
	}

	/**
	 * @inheritDoc
	 */
	public function suggestByPath( array $topic_path, string $query = '', int $limit = 5 ): array {
		return array();
	}

	/**
	 * @inheritDoc
	 */
	public function suggest( string $query, ?int $topic_id = null, int $limit = 5 ): array {
		$topic_path = null === $topic_id ? array() : array( $topic_id );
		return $this->searchTopics( $query, $topic_path, $limit );
	}

	/**
	 * @inheritDoc
	 */
	public function get( int|string $article_id ): ?array {
		return $this->getTopicById( $article_id );
	}
}
