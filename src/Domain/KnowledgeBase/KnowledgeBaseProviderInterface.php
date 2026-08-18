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
	 * Return a single article by identifier.
	 *
	 * @param int|string $article_id Provider-specific article identifier.
	 * @return array<string, mixed>|null Article record or null if not found.
	 */
	public function get( int|string $article_id ): ?array;
}
