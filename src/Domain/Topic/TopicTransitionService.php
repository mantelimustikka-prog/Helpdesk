<?php
/**
 * @package WP Helpdesk
 */

namespace WPHelpdesk\Domain\Topic;

use WPHelpdesk\Support\Helpers;

class TopicTransitionService {
	public const ADMIN_TRANSITION_MARKER = '__hd_admin_topic_link__';

	protected TopicTransitionRepository $repository;
	protected TopicRepository $topic_repository;
	protected int $network_id;

	public function __construct() {
		$this->repository       = new TopicTransitionRepository();
		$this->topic_repository = new TopicRepository();
		$this->network_id       = Helpers::getNetworkId();
	}

	/**
	 * List active transitions from a given topic.
	 *
	 * @param int  $from_topic_id Source topic id.
	 * @param bool $active_only   Whether to return only active transitions.
	 * @return array<int, array<string, mixed>>
	 */
	public function listFrom( int $from_topic_id, bool $active_only = true ): array {
		return $this->repository->listFrom( $from_topic_id, $this->network_id, $active_only );
	}

	/**
	 * List only runtime-valid transitions from a topic.
	 *
	 * @param int  $from_topic_id Source topic id.
	 * @param bool $active_only   Whether to limit to active transitions.
	 * @return array<int, array<string, mixed>>
	 */
	public function listValidFrom( int $from_topic_id, bool $active_only = true ): array {
		$topic = $this->topic_repository->find( $from_topic_id, $this->network_id );
		if ( ! $topic || ! empty( $topic['is_final'] ) || ( isset( $topic['is_active'] ) && empty( $topic['is_active'] ) ) ) {
			return array();
		}

		$transitions = $this->repository->listFrom( $from_topic_id, $this->network_id, $active_only );
		$target_ids  = array_values(
			array_filter(
				array_map(
					static fn( array $transition ): int => (int) ( $transition['to_topic_id'] ?? 0 ),
					$transitions
				),
				static fn( int $topic_id ): bool => $topic_id > 0 && $topic_id !== $from_topic_id
			)
		);
		$targets     = array();

		foreach ( $this->topic_repository->findMany( $target_ids, $this->network_id ) as $target ) {
			$target_id = (int) ( $target['id'] ?? 0 );
			if ( $target_id > 0 ) {
				$targets[ $target_id ] = $target;
			}
		}

		$valid = array();
		foreach ( $transitions as $transition ) {
			$to_topic_id = (int) ( $transition['to_topic_id'] ?? 0 );
			if ( $to_topic_id <= 0 || $to_topic_id === $from_topic_id ) {
				continue;
			}

			$target = $targets[ $to_topic_id ] ?? null;
			if ( ! $target || ( isset( $target['is_active'] ) && empty( $target['is_active'] ) ) ) {
				continue;
			}

			$valid[] = $transition;
		}

		return $valid;
	}

	/**
	 * List all transitions for the current network.
	 *
	 * @param array<string, mixed> $args Query args.
	 * @return array<int, array<string, mixed>>
	 */
	public function listTransitions( array $args = [] ): array {
		return $this->repository->list( $this->network_id, $args );
	}

	/**
	 * Get a single transition.
	 *
	 * @param int $id Transition id.
	 * @return array<string, mixed>|null
	 */
	public function getTransition( int $id ): ?array {
		return $this->repository->find( $id, $this->network_id );
	}

	/**
	 * Create a transition, enforcing that both topics belong to the network.
	 *
	 * @param array<string, mixed> $data Transition data.
	 * @return int|WP_Error
	 */
	public function createTransition( array $data ) {
		$from_id = isset( $data['from_topic_id'] ) ? (int) $data['from_topic_id'] : 0;
		$to_id   = isset( $data['to_topic_id'] ) ? (int) $data['to_topic_id'] : 0;

		if ( $from_id <= 0 || $to_id <= 0 ) {
			return 0;
		}

		if ( $from_id === $to_id ) {
			return 0;
		}

		// Both topics must exist and belong to the network.
		if ( ! $this->topic_repository->find( $from_id, $this->network_id ) ) {
			return 0;
		}

		if ( ! $this->topic_repository->find( $to_id, $this->network_id ) ) {
			return 0;
		}

		$label = isset( $data['label'] ) ? sanitize_text_field( trim( (string) $data['label'] ) ) : '';
		if ( '' === $label ) {
			return 0;
		}

		$now = current_time( 'mysql' );

		return $this->repository->create(
			[
				'network_id'      => $this->network_id,
				'from_topic_id'   => $from_id,
				'to_topic_id'     => $to_id,
				'label'           => $label,
				'condition_type'  => $this->sanitizeConditionType( (string) ( $data['condition_type'] ?? 'always' ) ),
				'condition_value' => isset( $data['condition_value'] ) ? (string) $data['condition_value'] : null,
				'sort_order'      => isset( $data['sort_order'] ) ? (int) $data['sort_order'] : 0,
				'is_active'       => isset( $data['is_active'] ) ? (int) (bool) $data['is_active'] : 1,
				'created_at'      => $now,
				'updated_at'      => $now,
			]
		);
	}

	/**
	 * Update a transition.
	 *
	 * @param int                  $id   Transition id.
	 * @param array<string, mixed> $data Transition data.
	 * @return bool
	 */
	public function updateTransition( int $id, array $data ): bool {
		$existing = $this->repository->find( $id, $this->network_id );
		if ( ! $existing ) {
			return false;
		}

		$update = [ 'updated_at' => current_time( 'mysql' ) ];

		if ( isset( $data['label'] ) ) {
			$label = sanitize_text_field( trim( (string) $data['label'] ) );
			if ( '' === $label ) {
				return false;
			}

			$update['label'] = $label;
		}

		if ( isset( $data['condition_type'] ) ) {
			$update['condition_type'] = $this->sanitizeConditionType( (string) $data['condition_type'] );
		}

		if ( array_key_exists( 'condition_value', $data ) ) {
			$update['condition_value'] = '' !== (string) $data['condition_value'] ? (string) $data['condition_value'] : null;
		}

		if ( isset( $data['sort_order'] ) ) {
			$update['sort_order'] = (int) $data['sort_order'];
		}

		if ( isset( $data['is_active'] ) ) {
			$update['is_active'] = (int) (bool) $data['is_active'];
		}

		return $this->repository->update( $id, $update, $this->network_id );
	}

	/**
	 * Delete a transition.
	 *
	 * @param int $id Transition id.
	 * @return bool
	 */
	public function deleteTransition( int $id ): bool {
		return $this->repository->delete( $id, $this->network_id );
	}

	/**
	 * Resolve the next topic given a source topic and an optional condition context.
	 *
	 * The resolver evaluates transitions in sort_order and returns the first whose
	 * condition_type matches the context. Condition types:
	 *   - 'always'       – always matches.
	 *   - 'field_equals' – condition_value is "field_name:expected_value".
	 *
	 * @param int                  $from_topic_id Source topic id.
	 * @param array<string, mixed> $context       Context values for condition evaluation.
	 * @return int|null Next topic id, or null if no matching transition found.
	 */
	public function resolveNextTopic( int $from_topic_id, array $context = [] ): ?int {
		$transitions = $this->listValidFrom( $from_topic_id, true );

		foreach ( $transitions as $transition ) {
			if ( $this->evaluateCondition( $transition, $context ) ) {
				return (int) $transition['to_topic_id'];
			}
		}

		return null;
	}

	/**
	 * Validate that a branch topic has at least one active outgoing transition.
	 *
	 * @param int $topic_id Topic id.
	 * @return bool
	 */
	public function branchTopicHasValidTransitions( int $topic_id ): bool {
		$topic = $this->topic_repository->find( $topic_id, $this->network_id );
		if ( ! $topic ) {
			return false;
		}

		// Final topics do not require outgoing transitions.
		if ( ! empty( $topic['is_final'] ) ) {
			return true;
		}

		return count( $this->listValidFrom( $topic_id, true ) ) >= 1;
	}

	/**
	 * Return unique valid next-topic ids for admin editing.
	 *
	 * @param int $topic_id Source topic id.
	 * @return array<int, int>
	 */
	public function getSelectableNextTopicIds( int $topic_id ): array {
		$ids = array();
		foreach ( $this->listValidFrom( $topic_id, false ) as $transition ) {
			$to_topic_id = (int) ( $transition['to_topic_id'] ?? 0 );
			if ( $to_topic_id > 0 ) {
				$ids[ $to_topic_id ] = $to_topic_id;
			}
		}

		return array_values( $ids );
	}

	/**
	 * Normalize candidate next-topic ids for persistence.
	 *
	 * @param array<int, mixed> $topic_ids Raw ids.
	 * @param int               $current_topic_id Current topic id.
	 * @return array<int, int>
	 */
	public function normalizeNextTopicIds( array $topic_ids, int $current_topic_id = 0 ): array {
		$normalized = array();

		foreach ( $topic_ids as $topic_id ) {
			$topic_id = (int) $topic_id;
			if ( $topic_id <= 0 || $topic_id === $current_topic_id ) {
				continue;
			}

			$normalized[ $topic_id ] = $topic_id;
		}

		return array_values( $normalized );
	}

	/**
	 * Validate final-vs-branch configuration selected in admin flows.
	 *
	 * @param int               $topic_id Topic id.
	 * @param bool              $is_final Whether the topic is final.
	 * @param array<int, mixed> $next_topic_ids Selected next topic ids.
	 * @return string|null
	 */
	public function validateBranchConfiguration( int $topic_id, bool $is_final, array $next_topic_ids ): ?string {
		if ( $is_final ) {
			return null;
		}

		foreach ( $next_topic_ids as $next_topic_id ) {
			if ( $topic_id > 0 && (int) $next_topic_id === $topic_id ) {
				return 'invalid-transition';
			}
		}

		$next_topic_ids = $this->normalizeNextTopicIds( $next_topic_ids, $topic_id );
		if ( empty( $next_topic_ids ) ) {
			return 'branch-missing-transition';
		}

		foreach ( $next_topic_ids as $next_topic_id ) {
			$topic = $this->topic_repository->find( $next_topic_id, $this->network_id );
			if ( ! $topic || ( isset( $topic['is_active'] ) && empty( $topic['is_active'] ) ) ) {
				return 'invalid-transition';
			}
		}

		return null;
	}

	/**
	 * Validate hierarchy placement selected in admin.
	 *
	 * @param int               $topic_id Topic id (0 on create).
	 * @param string            $hierarchy_type Selected hierarchy type.
	 * @param array<int, mixed> $parent_topic_ids Selected parent topic ids.
	 * @return string|null
	 */
	public function validateHierarchyConfiguration( int $topic_id, string $hierarchy_type, array $parent_topic_ids ): ?string {
		$hierarchy_type = 'follow_up' === sanitize_key( $hierarchy_type ) ? 'follow_up' : 'top_level';

		foreach ( $parent_topic_ids as $parent_topic_id ) {
			if ( $topic_id > 0 && (int) $parent_topic_id === $topic_id ) {
				return 'invalid-parent-topic';
			}
		}

		$parent_topic_ids = $this->normalizeNextTopicIds( $parent_topic_ids, $topic_id );

		if ( 'follow_up' === $hierarchy_type && empty( $parent_topic_ids ) ) {
			return 'follow-up-missing-parent';
		}

		if ( 'top_level' === $hierarchy_type && ! empty( $parent_topic_ids ) ) {
			return 'top-level-has-parent';
		}

		foreach ( $parent_topic_ids as $parent_topic_id ) {
			$parent = $this->topic_repository->find( (int) $parent_topic_id, $this->network_id );
			if ( ! $parent || ( isset( $parent['is_active'] ) && empty( $parent['is_active'] ) ) ) {
				return 'invalid-parent-topic';
			}
		}

		if ( $topic_id > 0 && ! empty( $parent_topic_ids ) && $this->wouldCreateHierarchyCycle( $topic_id, $parent_topic_ids ) ) {
			return 'circular-parent-topic';
		}

		return null;
	}

	/**
	 * Return a topic id => parent topic ids map for admin-managed hierarchy links.
	 *
	 * @return array<int, array<int, int>>
	 */
	public function getAdminParentTopicIdsMap(): array {
		$page       = 1;
		$per_page   = 250;
		$parent_map = array();

		do {
			$transitions = $this->repository->list(
				$this->network_id,
				array(
					'page'      => $page,
					'per_page'  => $per_page,
					'is_active' => 1,
				)
			);

			foreach ( $transitions as $transition ) {
				if ( ! $this->isAdminManagedTransition( $transition ) ) {
					continue;
				}

				$from_topic_id = (int) ( $transition['from_topic_id'] ?? 0 );
				$to_topic_id   = (int) ( $transition['to_topic_id'] ?? 0 );

				if ( $from_topic_id <= 0 || $to_topic_id <= 0 || $from_topic_id === $to_topic_id ) {
					continue;
				}

				if ( ! isset( $parent_map[ $to_topic_id ] ) ) {
					$parent_map[ $to_topic_id ] = array();
				}

				$parent_map[ $to_topic_id ][ $from_topic_id ] = $from_topic_id;
			}

			++$page;
		} while ( count( $transitions ) === $per_page );

		return $parent_map;
	}

	/**
	 * Sync admin-managed follow-up transitions for a topic.
	 *
	 * @param int               $from_topic_id Source topic id.
	 * @param array<int, mixed> $next_topic_ids Selected next topic ids.
	 * @return bool
	 */
	public function syncAdminNextTopics( int $from_topic_id, array $next_topic_ids ): bool {
		$source = $this->topic_repository->find( $from_topic_id, $this->network_id );
		if ( ! $source ) {
			return false;
		}

		$next_topic_ids = $this->normalizeNextTopicIds( $next_topic_ids, $from_topic_id );
		$target_topics  = array();
		foreach ( $this->topic_repository->findMany( $next_topic_ids, $this->network_id ) as $topic ) {
			$topic_id = (int) ( $topic['id'] ?? 0 );
			if ( $topic_id > 0 ) {
				$target_topics[ $topic_id ] = $topic;
			}
		}

		foreach ( $next_topic_ids as $next_topic_id ) {
			$topic = $target_topics[ $next_topic_id ] ?? null;
			if ( ! $topic || ( isset( $topic['is_active'] ) && empty( $topic['is_active'] ) ) ) {
				return false;
			}
		}

		$existing_transitions = $this->repository->listFrom( $from_topic_id, $this->network_id, false );
		$admin_transitions    = array();
		$custom_targets       = array();

		foreach ( $existing_transitions as $transition ) {
			$to_topic_id = (int) ( $transition['to_topic_id'] ?? 0 );
			if ( $to_topic_id <= 0 ) {
				continue;
			}

			if ( $this->isAdminManagedTransition( $transition ) ) {
				$admin_transitions[ $to_topic_id ] = $transition;
				continue;
			}

			if ( ! empty( $transition['is_active'] ) ) {
				$custom_targets[ $to_topic_id ] = true;
			}
		}

		foreach ( $next_topic_ids as $next_topic_id ) {
			$target = $target_topics[ $next_topic_id ] ?? array();
			$label  = isset( $target['title'] ) ? (string) $target['title'] : 'Topic #' . $next_topic_id;

			if ( isset( $admin_transitions[ $next_topic_id ] ) ) {
				$transition = $admin_transitions[ $next_topic_id ];
				$transition_id = (int) ( $transition['id'] ?? 0 );
				if ( $transition_id > 0 && ( empty( $transition['is_active'] ) || (string) ( $transition['label'] ?? '' ) !== $label ) ) {
					$this->updateTransition(
						$transition_id,
						array(
							'label'     => $label,
							'is_active' => 1,
						)
					);
				}
				continue;
			}

			if ( isset( $custom_targets[ $next_topic_id ] ) ) {
				continue;
			}

			if ( $this->createTransition(
				array(
					'from_topic_id'   => $from_topic_id,
					'to_topic_id'     => $next_topic_id,
					'label'           => $label,
					'condition_type'  => 'always',
					'condition_value' => self::ADMIN_TRANSITION_MARKER,
					'is_active'       => 1,
				)
			) <= 0 ) {
				return false;
			}
		}

		foreach ( $admin_transitions as $to_topic_id => $transition ) {
			if ( in_array( $to_topic_id, $next_topic_ids, true ) ) {
				continue;
			}

			$transition_id = (int) ( $transition['id'] ?? 0 );
			if ( $transition_id > 0 && ! $this->repository->delete( $transition_id, $this->network_id ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Return unique valid parent-topic ids for admin editing.
	 *
	 * @param int $topic_id Target topic id.
	 * @return array<int, int>
	 */
	public function getSelectableParentTopicIds( int $topic_id ): array {
		$ids = array();
		foreach ( $this->repository->listTo( $topic_id, $this->network_id, false ) as $transition ) {
			if ( ! $this->isAdminManagedTransition( $transition ) ) {
				continue;
			}

			$from_topic_id = (int) ( $transition['from_topic_id'] ?? 0 );
			if ( $from_topic_id > 0 ) {
				$ids[ $from_topic_id ] = $from_topic_id;
			}
		}

		return array_values( $ids );
	}

	/**
	 * Return incoming valid parent transitions for a topic.
	 *
	 * @param int  $topic_id Target topic id.
	 * @param bool $active_only Whether to return only active transitions.
	 * @return array<int, array<string, mixed>>
	 */
	public function listValidParentsForTopic( int $topic_id, bool $active_only = true ): array {
		$topic = $this->topic_repository->find( $topic_id, $this->network_id );
		if ( ! $topic || ( isset( $topic['is_active'] ) && empty( $topic['is_active'] ) ) ) {
			return array();
		}

		$transitions = $this->repository->listTo( $topic_id, $this->network_id, $active_only );
		$from_ids    = array_values(
			array_filter(
				array_map(
					static fn( array $transition ): int => (int) ( $transition['from_topic_id'] ?? 0 ),
					$transitions
				),
				static fn( int $id ): bool => $id > 0 && $id !== $topic_id
			)
		);
		$sources     = array();
		foreach ( $this->topic_repository->findMany( $from_ids, $this->network_id ) as $source ) {
			$source_id = (int) ( $source['id'] ?? 0 );
			if ( $source_id > 0 ) {
				$sources[ $source_id ] = $source;
			}
		}

		$valid = array();
		foreach ( $transitions as $transition ) {
			$from_topic_id = (int) ( $transition['from_topic_id'] ?? 0 );
			if ( $from_topic_id <= 0 || $from_topic_id === $topic_id ) {
				continue;
			}

			$source = $sources[ $from_topic_id ] ?? null;
			if ( ! $source || ( isset( $source['is_active'] ) && empty( $source['is_active'] ) ) ) {
				continue;
			}

			$valid[] = $transition;
		}

		return $valid;
	}

	/**
	 * Sync admin-managed parent transitions for a topic.
	 *
	 * @param int               $to_topic_id Target topic id.
	 * @param array<int, mixed> $parent_topic_ids Selected parent topic ids.
	 * @return bool
	 */
	public function syncAdminParentTopics( int $to_topic_id, array $parent_topic_ids ): bool {
		$target = $this->topic_repository->find( $to_topic_id, $this->network_id );
		if ( ! $target ) {
			return false;
		}

		$parent_topic_ids = $this->normalizeNextTopicIds( $parent_topic_ids, $to_topic_id );
		$parent_topics    = array();
		foreach ( $this->topic_repository->findMany( $parent_topic_ids, $this->network_id ) as $topic ) {
			$topic_id = (int) ( $topic['id'] ?? 0 );
			if ( $topic_id > 0 ) {
				$parent_topics[ $topic_id ] = $topic;
			}
		}

		foreach ( $parent_topic_ids as $parent_topic_id ) {
			$topic = $parent_topics[ $parent_topic_id ] ?? null;
			if ( ! $topic || ( isset( $topic['is_active'] ) && empty( $topic['is_active'] ) ) ) {
				return false;
			}
		}

		$existing_transitions = $this->repository->listTo( $to_topic_id, $this->network_id, false );
		$admin_transitions    = array();
		$custom_sources       = array();

		foreach ( $existing_transitions as $transition ) {
			$from_topic_id = (int) ( $transition['from_topic_id'] ?? 0 );
			if ( $from_topic_id <= 0 ) {
				continue;
			}

			if ( $this->isAdminManagedTransition( $transition ) ) {
				$admin_transitions[ $from_topic_id ] = $transition;
				continue;
			}

			if ( ! empty( $transition['is_active'] ) ) {
				$custom_sources[ $from_topic_id ] = true;
			}
		}

		$label = isset( $target['title'] ) ? (string) $target['title'] : 'Topic #' . $to_topic_id;
		foreach ( $parent_topic_ids as $parent_topic_id ) {
			if ( isset( $admin_transitions[ $parent_topic_id ] ) ) {
				$transition    = $admin_transitions[ $parent_topic_id ];
				$transition_id = (int) ( $transition['id'] ?? 0 );
				if ( $transition_id > 0 && ( empty( $transition['is_active'] ) || (string) ( $transition['label'] ?? '' ) !== $label ) ) {
					$this->updateTransition(
						$transition_id,
						array(
							'label'     => $label,
							'is_active' => 1,
						)
					);
				}
				continue;
			}

			if ( isset( $custom_sources[ $parent_topic_id ] ) ) {
				continue;
			}

			if ( $this->createTransition(
				array(
					'from_topic_id'   => $parent_topic_id,
					'to_topic_id'     => $to_topic_id,
					'label'           => $label,
					'condition_type'  => 'always',
					'condition_value' => self::ADMIN_TRANSITION_MARKER,
					'is_active'       => 1,
				)
			) <= 0 ) {
				return false;
			}
		}

		foreach ( $admin_transitions as $from_topic_id => $transition ) {
			if ( in_array( $from_topic_id, $parent_topic_ids, true ) ) {
				continue;
			}

			$transition_id = (int) ( $transition['id'] ?? 0 );
			if ( $transition_id > 0 && ! $this->repository->delete( $transition_id, $this->network_id ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Evaluate whether a transition condition matches the given context.
	 *
	 * @param array<string, mixed> $transition Transition row.
	 * @param array<string, mixed> $context    Context values.
	 * @return bool
	 */
	protected function evaluateCondition( array $transition, array $context ): bool {
		$type = (string) ( $transition['condition_type'] ?? 'always' );

		if ( 'always' === $type ) {
			return true;
		}

		if ( 'field_equals' === $type ) {
			$value_spec = (string) ( $transition['condition_value'] ?? '' );
			$colon_pos  = strpos( $value_spec, ':' );
			if ( false === $colon_pos ) {
				return false;
			}

			$field    = substr( $value_spec, 0, $colon_pos );
			$expected = substr( $value_spec, $colon_pos + 1 );

			return isset( $context[ $field ] ) && (string) $context[ $field ] === $expected;
		}

		// Extensibility: allow filter hooks for custom condition types.
		return (bool) apply_filters(
			'hd_transition_condition_match',
			false,
			$type,
			(string) ( $transition['condition_value'] ?? '' ),
			$context
		);
	}

	/**
	 * Sanitize a condition type.
	 *
	 * @param string $type Raw condition type.
	 * @return string
	 */
	private function sanitizeConditionType( string $type ): string {
		$allowed = [ 'always', 'field_equals' ];

		return in_array( $type, $allowed, true ) ? $type : 'always';
	}

	/**
	 * Determine whether selecting the provided parents would create a cycle.
	 *
	 * @param int               $topic_id Topic id.
	 * @param array<int, mixed> $parent_topic_ids Candidate parent ids.
	 * @return bool
	 */
	private function wouldCreateHierarchyCycle( int $topic_id, array $parent_topic_ids ): bool {
		$parent_map         = $this->getAdminParentTopicIdsMap();
		$children_by_parent = array();

		foreach ( $parent_map as $child_topic_id => $existing_parent_ids ) {
			foreach ( $existing_parent_ids as $parent_topic_id ) {
				if ( ! isset( $children_by_parent[ $parent_topic_id ] ) ) {
					$children_by_parent[ $parent_topic_id ] = array();
				}

				$children_by_parent[ $parent_topic_id ][ (int) $child_topic_id ] = (int) $child_topic_id;
			}
		}

		foreach ( $parent_topic_ids as $parent_topic_id ) {
			if ( $this->topicCanReach( $topic_id, (int) $parent_topic_id, $children_by_parent, array() ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Determine whether a hierarchy path exists between two topics.
	 *
	 * @param int                    $from_topic_id Start topic id.
	 * @param int                    $target_topic_id Target topic id.
	 * @param array<int, array<int>> $children_by_parent Parent => child ids map.
	 * @param array<int, int>        $visited Visited topic ids.
	 * @return bool
	 */
	private function topicCanReach( int $from_topic_id, int $target_topic_id, array $children_by_parent, array $visited ): bool {
		if ( $from_topic_id <= 0 ) {
			return false;
		}

		if ( $from_topic_id === $target_topic_id ) {
			return true;
		}

		if ( isset( $visited[ $from_topic_id ] ) ) {
			return false;
		}

		$visited[ $from_topic_id ] = $from_topic_id;

		foreach ( $children_by_parent[ $from_topic_id ] ?? array() as $child_topic_id ) {
			if ( $this->topicCanReach( (int) $child_topic_id, $target_topic_id, $children_by_parent, $visited ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Determine whether a transition is managed by the topic admin form.
	 *
	 * @param array<string, mixed> $transition Transition row.
	 * @return bool
	 */
	private function isAdminManagedTransition( array $transition ): bool {
		return 'always' === (string) ( $transition['condition_type'] ?? 'always' )
			&& self::ADMIN_TRANSITION_MARKER === (string) ( $transition['condition_value'] ?? '' );
	}
}
