<?php
/**
 * @package WP Helpdesk
 */

namespace WPHelpdesk\Domain\KnowledgeBase;

/**
 * Contract for knowledge-base provider implementations.
 *
 * Implementations return article suggestions that can be surfaced to end-users
 * before they submit a support ticket (reducing ticket volume) or shown to
 * agents inline during ticket handling.
 */
interface KnowledgeBaseProviderInterface {
	/**
	 * Search knowledge-base entries using free text and optional topic path context.
	 *
	 * @param string            $query      Query string.
	 * @param array<int, mixed> $topic_path Optional topic path context.
	 * @param int               $limit      Maximum results.
	 * @return array<int, array<string, mixed>>
	 */
	public function searchTopics( string $query, array $topic_path = array(), int $limit = 5 ): array;

	/**
	 * Return a single article by identifier.
	 *
	 * @param int|string $article_id Provider-specific article identifier.
	 * @return array<string, mixed>|null
	 */
	public function getTopicById( int|string $article_id ): ?array;

	/**
	 * Suggest articles from a topic path and optional free-text query.
	 *
	 * @param array<int, mixed> $topic_path Topic path ids or labels.
	 * @param string            $query      Optional query text.
	 * @param int               $limit      Maximum results.
	 * @return array<int, array<string, mixed>>
	 */
	public function suggestByPath( array $topic_path, string $query = '', int $limit = 5 ): array;

	/**
	 * Find articles that best match the given query text and optional topic.
	 *
	 * @param string   $query    Free-text search string.
	 * @param int|null $topic_id Optional topic ID to scope the search.
	 * @param int      $limit    Maximum number of results to return.
	 * @return array<int, array<string, mixed>> List of article records:
	 *   - id    : unique article identifier
	 *   - title : article title
	 *   - excerpt : short description or first paragraph
	 *   - url   : canonical URL for the article
	 */
	public function suggest( string $query, ?int $topic_id = null, int $limit = 5 ): array;

	/**
	 * Backward-compatible single article lookup.
	 *
	 * @param int|string $article_id Provider-specific article identifier.
	 * @return array<string, mixed>|null
	 */
	public function get( int|string $article_id ): ?array;
}
