<?php
/**
 * @package WP Helpdesk
 */

namespace WPHelpdesk\Domain\KnowledgeBase;

class WordPressKnowledgeBaseProvider implements KnowledgeBaseProviderInterface {
	/**
	 * @inheritDoc
	 */
	public function searchTopics( string $query, array $topic_path = array(), int $limit = 5 ): array {
		if ( ! class_exists( '\WP_Query' ) ) {
			return array();
		}

		$search_terms = array_filter(
			array_merge(
				array( trim( $query ) ),
				array_values(
					array_filter(
						array_map(
							static function ( $segment ): string {
								return is_string( $segment ) ? trim( $segment ) : '';
							},
							$topic_path
						)
					)
				)
			)
		);
		if ( empty( $search_terms ) ) {
			return array();
		}

		$post_types = apply_filters( 'hd_kb_native_post_types', array( 'post', 'page' ) );
		$wp_query   = new \WP_Query(
			array(
				'post_type'           => $post_types,
				'post_status'         => 'publish',
				'posts_per_page'      => max( 1, $limit ),
				'ignore_sticky_posts' => true,
				'no_found_rows'       => true,
				's'                   => implode( ' ', $search_terms ),
			)
		);

		$items = array();
		foreach ( $wp_query->posts as $post ) {
			$items[] = $this->mapPost( $post );
		}

		return $items;
	}

	/**
	 * @inheritDoc
	 */
	public function getTopicById( int|string $article_id ): ?array {
		if ( ! function_exists( 'get_post' ) ) {
			return null;
		}

		$post = get_post( $article_id );
		if ( ! $post || 'publish' !== $post->post_status ) {
			return null;
		}

		return $this->mapPost( $post );
	}

	/**
	 * @inheritDoc
	 */
	public function suggestByPath( array $topic_path, string $query = '', int $limit = 5 ): array {
		return $this->searchTopics( $query, $topic_path, $limit );
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

	/**
	 * Map a WP_Post into a provider payload.
	 *
	 * @param object $post Post object.
	 * @return array<string, mixed>
	 */
	protected function mapPost( object $post ): array {
		$excerpt = '';
		if ( isset( $post->post_excerpt ) && '' !== trim( (string) $post->post_excerpt ) ) {
			$excerpt = (string) $post->post_excerpt;
		} elseif ( isset( $post->post_content ) ) {
			$excerpt = wp_trim_words( wp_strip_all_tags( (string) $post->post_content ), 30 );
		}

		return array(
			'id'      => (int) $post->ID,
			'title'   => (string) $post->post_title,
			'excerpt' => $excerpt,
			'url'     => function_exists( 'get_permalink' ) ? (string) get_permalink( $post ) : '',
		);
	}
}
