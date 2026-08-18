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
	public function suggest( string $query, ?int $topic_id = null, int $limit = 5 ): array {
		return array();
	}

	/**
	 * @inheritDoc
	 */
	public function get( int|string $article_id ): ?array {
		return null;
	}
}
