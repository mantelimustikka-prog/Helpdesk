<?php
/**
 * @package WP Helpdesk
 */

namespace WPHelpdesk\Domain\Topic;

use WPHelpdesk\Support\Helpers;

class TopicTransitionService {

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
		$transitions = $this->repository->listFrom( $from_topic_id, $this->network_id, true );

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

		return $this->repository->countActiveFrom( $topic_id, $this->network_id ) >= 1;
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
}
