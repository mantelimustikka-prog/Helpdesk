<?php
/**
 * @package WP Helpdesk
 */

namespace WPHelpdesk\Domain\Topic;

use WPHelpdesk\Support\Helpers;

class TopicService {
	protected TopicRepository $repository;
	protected int $network_id;

	public function __construct() {
		$this->repository = new TopicRepository();
		$this->network_id = Helpers::getNetworkId();
	}

	/**
	 * List normalized topics for the current network.
	 *
	 * @param array<string, mixed> $args Query args.
	 * @return array<int, array<string, mixed>>
	 */
	public function listTopics( array $args = [] ): array {
		$rows = $this->repository->list( $this->network_id, $args );
		$transition_counts = $this->repository->getActiveTransitionCounts(
			array_map(
				static fn( array $row ): int => (int) ( $row['id'] ?? 0 ),
				$rows
			),
			$this->network_id
		);

		return array_map(
			fn( array $row ): array => $this->normalizeRow( $row, $transition_counts[ (int) ( $row['id'] ?? 0 ) ] ?? 0 ),
			$rows
		);
	}

	/**
	 * Count topics for the current network.
	 *
	 * @param array<string, mixed> $args Query args.
	 * @return int
	 */
	public function countTopics( array $args = [] ): int {
		return $this->repository->count( $this->network_id, $args );
	}

	/**
	 * Reorder topics for the current network.
	 *
	 * @param array<int, int> $orders Topic id => sort order map.
	 * @return int
	 */
	public function reorderTopics( array $orders ): int {
		$normalized = array();

		foreach ( $orders as $topic_id => $sort_order ) {
			$topic_id = (int) $topic_id;
			if ( $topic_id <= 0 ) {
				continue;
			}

			$normalized[ $topic_id ] = (int) $sort_order;
		}

		if ( empty( $normalized ) ) {
			return 0;
		}

		$updated = $this->repository->updateSortOrders( $normalized, $this->network_id );

		return $updated ? count( $normalized ) : 0;
	}

	/**
	 * Fetch a single normalized topic.
	 *
	 * @param int $id Topic id.
	 * @return array<string, mixed>|null
	 */
	public function getTopic( int $id ): ?array {
		$row = $this->repository->find( $id, $this->network_id );

		return $row ? $this->normalizeRow( $row, $this->repository->countActiveTransitionsFromTopic( $id, $this->network_id ) ) : null;
	}

	/**
	 * Determine whether a branch topic has at least one active next edge.
	 *
	 * @param int $id Topic id.
	 * @return bool
	 */
	public function isBranchTopicValid( int $id ): bool {
		$topic = $this->repository->find( $id, $this->network_id );
		if ( ! $topic ) {
			return false;
		}

		if ( ! empty( $topic['is_final'] ) ) {
			return true;
		}

		return $this->repository->countActiveTransitionsFromTopic( $id, $this->network_id ) >= 1;
	}

	/**
	 * Create a topic.
	 *
	 * @param array<string, mixed> $data Topic payload.
	 * @return int
	 */
	public function createTopic( array $data ): int {
		$name = isset( $data['name'] ) ? sanitize_text_field( trim( (string) $data['name'] ) ) : '';
		if ( '' === $name ) {
			return 0;
		}

		$base_slug = isset( $data['slug'] ) && '' !== trim( (string) $data['slug'] )
			? sanitize_title( trim( (string) $data['slug'] ) )
			: sanitize_title( $name );
		$slug      = $this->ensureUniqueSlug( $base_slug );

		return $this->repository->create(
			array(
				'network_id'  => $this->network_id,
				'title'       => $name,
				'slug'        => $slug,
				'description' => isset( $data['description'] ) ? sanitize_textarea_field( (string) $data['description'] ) : '',
				'is_final'    => $this->resolveIsFinalFlag( $data ) ? 1 : 0,
				'is_active'   => isset( $data['is_active'] ) ? (int) (bool) $data['is_active'] : 1,
				'sort_order'  => isset( $data['sort_order'] ) ? (int) $data['sort_order'] : 0,
				'created_at'  => current_time( 'mysql' ),
				'updated_at'  => current_time( 'mysql' ),
			)
		);
	}

	/**
	 * Update a topic.
	 *
	 * @param int                  $id   Topic id.
	 * @param array<string, mixed> $data Topic payload.
	 * @return bool
	 */
	public function updateTopic( int $id, array $data ): bool {
		$existing = $this->repository->find( $id, $this->network_id );
		if ( ! $existing ) {
			return false;
		}

		$update = array(
			'updated_at' => current_time( 'mysql' ),
		);

		if ( isset( $data['name'] ) ) {
			$name = sanitize_text_field( trim( (string) $data['name'] ) );
			if ( '' === $name ) {
				return false;
			}

			$update['title'] = $name;
		}

		if ( array_key_exists( 'slug', $data ) ) {
			$slug_source = trim( (string) $data['slug'] );
			if ( '' === $slug_source ) {
				$slug_source = isset( $update['title'] ) ? (string) $update['title'] : (string) $existing['title'];
			}

			$update['slug'] = $this->ensureUniqueSlug( sanitize_title( $slug_source ), $id );
		}

		if ( isset( $data['description'] ) ) {
			$update['description'] = sanitize_textarea_field( (string) $data['description'] );
		}

		if ( isset( $data['is_active'] ) ) {
			$update['is_active'] = (int) (bool) $data['is_active'];
		}

		if ( isset( $data['sort_order'] ) ) {
			$update['sort_order'] = (int) $data['sort_order'];
		}

		if ( array_key_exists( 'is_final', $data ) || array_key_exists( 'node_type', $data ) ) {
			$update['is_final'] = $this->resolveIsFinalFlag( $data, ! empty( $existing['is_final'] ) ) ? 1 : 0;
		}

		return $this->repository->update( $id, $update, $this->network_id );
	}

	/**
	 * Delete a topic.
	 *
	 * @param int $id Topic id.
	 * @return bool
	 */
	public function deleteTopic( int $id ): bool {
		return $this->repository->delete( $id, $this->network_id );
	}

	/**
	 * Toggle topic activity.
	 *
	 * @param int  $id     Topic id.
	 * @param bool $active Whether the topic should be active.
	 * @return bool
	 */
	public function setActive( int $id, bool $active ): bool {
		$existing = $this->repository->find( $id, $this->network_id );
		if ( ! $existing ) {
			return false;
		}

		return $this->repository->update(
			$id,
			array(
				'is_active'  => $active ? 1 : 0,
				'updated_at' => current_time( 'mysql' ),
			),
			$this->network_id
		);
	}

	/**
	 * Ensure a slug is unique within the network.
	 *
	 * @param string $base_slug  Base slug.
	 * @param int    $exclude_id Topic id to exclude.
	 * @return string
	 */
	public function ensureUniqueSlug( string $base_slug, int $exclude_id = 0 ): string {
		$base_slug = sanitize_title( $base_slug );
		$base_slug = '' !== $base_slug ? $base_slug : 'topic';
		$slug      = $base_slug;
		$counter   = 2;

		while ( true ) {
			$existing = $this->repository->findBySlug( $slug, $this->network_id );
			if ( ! $existing || (int) $existing['id'] === $exclude_id ) {
				return $slug;
			}

			$slug = $base_slug . '-' . $counter;
			++$counter;
		}
	}

	/**
	 * Normalize a raw repository row for consumers.
	 *
	 * @param array<string, mixed> $row Topic row.
	 * @return array<string, mixed>
	 */
	protected function normalizeRow( array $row, int $transition_count = 0 ): array {
		$row['name'] = isset( $row['title'] ) ? (string) $row['title'] : '';
		$row['node_type'] = ! empty( $row['is_final'] ) ? 'final' : 'branch';
		$row['graph_is_valid'] = ! empty( $row['is_final'] )
			? true
			: $transition_count >= 1;

		return $row;
	}

	/**
	 * Resolve the persisted final-step flag from a payload.
	 *
	 * @param array<string, mixed> $data Topic payload.
	 * @param bool                 $default Default fallback.
	 * @return bool
	 */
	protected function resolveIsFinalFlag( array $data, bool $default = false ): bool {
		if ( array_key_exists( 'node_type', $data ) ) {
			return 'final' === sanitize_key( (string) $data['node_type'] );
		}

		if ( array_key_exists( 'is_final', $data ) ) {
			return (bool) $data['is_final'];
		}

		return $default;
	}
}
