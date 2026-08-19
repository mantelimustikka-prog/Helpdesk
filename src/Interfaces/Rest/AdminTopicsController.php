<?php
/**
 * @package WP Helpdesk
 */

namespace WPHelpdesk\Interfaces\Rest;

use WP_REST_Request;
use WP_REST_Response;
use WPHelpdesk\Domain\Topic\TopicService;
use WPHelpdesk\Domain\Topic\TopicTransitionService;

class AdminTopicsController {
	protected TopicService $topic_service;
	protected TopicTransitionService $topic_transition_service;

	public function __construct( ?TopicService $topic_service = null, ?TopicTransitionService $topic_transition_service = null ) {
		$this->topic_service            = $topic_service ?: new TopicService();
		$this->topic_transition_service = $topic_transition_service ?: new TopicTransitionService();
	}

	/**
	 * List admin topics.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function listTopics( WP_REST_Request $request ): WP_REST_Response {
		$page     = max( 1, (int) $request->get_param( 'page' ) );
		$per_page = max( 1, min( 100, (int) ( $request->get_param( 'per_page' ) ?: 20 ) ) );
		$search   = sanitize_text_field( (string) $request->get_param( 'search' ) );
		$args     = array(
			'page'     => $page,
			'per_page' => $per_page,
		);

		if ( '' !== $search ) {
			$args['search'] = $search;
		}

		if ( null !== $request->get_param( 'is_active' ) && '' !== (string) $request->get_param( 'is_active' ) ) {
			$args['is_active'] = (int) (bool) $request->get_param( 'is_active' );
		}

		$total = $this->topic_service->countTopics( $args );
		$items = $this->topic_service->listTopics( $args );

		return new WP_REST_Response(
			array(
				'items'       => $items,
				'page'        => $page,
				'per_page'    => $per_page,
				'total'       => $total,
				'total_pages' => max( 1, (int) ceil( $total / $per_page ) ),
			),
			200
		);
	}

	/**
	 * Create an admin topic.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function createTopic( WP_REST_Request $request ): WP_REST_Response {
		$payload        = $this->extractPayload( $request );
		$next_topic_ids = $this->extractNextTopicIds( $request );
		$parent_topic_ids = $this->extractParentTopicIds( $request );
		$error_code     = $this->topic_transition_service->validateBranchConfiguration( 0, ! empty( $payload['is_final'] ), $next_topic_ids );

		if ( null !== $error_code ) {
			return new WP_REST_Response( array( 'message' => $this->messageForErrorCode( $error_code ) ), 400 );
		}

		$error_code = $this->topic_transition_service->validateHierarchyConfiguration( 0, (string) ( $payload['hierarchy_type'] ?? 'top_level' ), $parent_topic_ids );
		if ( null !== $error_code ) {
			return new WP_REST_Response( array( 'message' => $this->messageForErrorCode( $error_code ) ), 400 );
		}

		$topic_id = $this->topic_service->createTopic( $payload );
		if ( $topic_id <= 0 ) {
			return new WP_REST_Response( array( 'message' => 'Failed to create topic.' ), 400 );
		}

		if ( ! $this->topic_transition_service->syncAdminParentTopics( $topic_id, 'follow_up' === (string) ( $payload['hierarchy_type'] ?? 'top_level' ) ? $parent_topic_ids : array() ) ) {
			return new WP_REST_Response( array( 'message' => 'Failed to save parent topics.' ), 400 );
		}

		if ( ! $this->topic_transition_service->syncAdminNextTopics( $topic_id, ! empty( $payload['is_final'] ) ? array() : $next_topic_ids ) ) {
			return new WP_REST_Response( array( 'message' => 'Failed to save follow-up topics.' ), 400 );
		}

		$topic = $this->buildTopicResponse( $topic_id );

		return new WP_REST_Response( $topic, 201 );
	}

	/**
	 * Get a single admin topic.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function getTopic( WP_REST_Request $request ): WP_REST_Response {
		$topic = $this->buildTopicResponse( (int) $request['id'] );
		if ( ! $topic ) {
			return new WP_REST_Response( array( 'message' => 'Topic not found.' ), 404 );
		}

		return new WP_REST_Response( $topic, 200 );
	}

	/**
	 * Update an admin topic.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function updateTopic( WP_REST_Request $request ): WP_REST_Response {
		$topic_id       = (int) $request['id'];
		$payload        = $this->extractPayload( $request );
		$next_topic_ids = $this->extractNextTopicIds( $request );
		$parent_topic_ids = $this->extractParentTopicIds( $request );
		$error_code     = $this->topic_transition_service->validateBranchConfiguration( $topic_id, ! empty( $payload['is_final'] ), $next_topic_ids );

		if ( null !== $error_code ) {
			return new WP_REST_Response( array( 'message' => $this->messageForErrorCode( $error_code ) ), 400 );
		}

		$error_code = $this->topic_transition_service->validateHierarchyConfiguration( $topic_id, (string) ( $payload['hierarchy_type'] ?? 'top_level' ), $parent_topic_ids );
		if ( null !== $error_code ) {
			return new WP_REST_Response( array( 'message' => $this->messageForErrorCode( $error_code ) ), 400 );
		}

		$updated = $this->topic_service->updateTopic( $topic_id, $payload );
		if ( ! $updated ) {
			return new WP_REST_Response( array( 'message' => 'Unable to update topic.' ), 400 );
		}

		if ( ! $this->topic_transition_service->syncAdminParentTopics( $topic_id, 'follow_up' === (string) ( $payload['hierarchy_type'] ?? 'top_level' ) ? $parent_topic_ids : array() ) ) {
			return new WP_REST_Response( array( 'message' => 'Failed to save parent topics.' ), 400 );
		}

		if ( ! $this->topic_transition_service->syncAdminNextTopics( $topic_id, ! empty( $payload['is_final'] ) ? array() : $next_topic_ids ) ) {
			return new WP_REST_Response( array( 'message' => 'Failed to save follow-up topics.' ), 400 );
		}

		$topic = $this->buildTopicResponse( $topic_id );

		return new WP_REST_Response( $topic, 200 );
	}

	/**
	 * Delete an admin topic.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function deleteTopic( WP_REST_Request $request ): WP_REST_Response {
		$topic_id = (int) $request['id'];
		$topic    = $this->topic_service->getTopic( $topic_id );
		if ( ! $topic ) {
			return new WP_REST_Response( array( 'message' => 'Topic not found.' ), 404 );
		}

		$deleted = $this->topic_service->deleteTopic( $topic_id );
		if ( ! $deleted ) {
			return new WP_REST_Response( array( 'message' => 'Unable to delete topic.' ), 400 );
		}

		return new WP_REST_Response( array( 'deleted' => true ), 200 );
	}

	/**
	 * Reorder topics.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function reorderTopics( WP_REST_Request $request ): WP_REST_Response {
		$items = $request->get_param( 'items' );
		if ( ! is_array( $items ) || empty( $items ) ) {
			return new WP_REST_Response( array( 'message' => 'The items parameter is required for reorder.' ), 400 );
		}

		$orders = array();
		foreach ( array_values( $items ) as $index => $item ) {
			$topic_id   = 0;
			$sort_order = $index;

			if ( is_array( $item ) ) {
				$topic_id   = isset( $item['id'] ) ? (int) $item['id'] : 0;
				$sort_order = isset( $item['sort_order'] ) ? (int) $item['sort_order'] : $index;
			} else {
				$topic_id = (int) $item;
			}

			if ( $topic_id <= 0 ) {
				continue;
			}

			$orders[ $topic_id ] = $sort_order;
		}

		$updated        = $this->topic_service->reorderTopics( $orders );
		$reordered_ids  = array_keys( $orders );
		$reordered_rows = array();

		foreach ( $reordered_ids as $topic_id ) {
			$topic = $this->topic_service->getTopic( (int) $topic_id );
			if ( $topic ) {
				$reordered_rows[] = $topic;
			}
		}

		return new WP_REST_Response(
			array(
				'updated' => $updated,
				'items'   => $reordered_rows,
			),
			200
		);
	}

	/**
	 * Extract a topic payload from the request.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return array<string, mixed>
	 */
	protected function extractPayload( WP_REST_Request $request ): array {
		$payload = array();

		foreach ( array( 'name', 'slug', 'description', 'sort_order', 'is_active', 'is_final', 'node_type', 'hierarchy_type' ) as $field ) {
			if ( null !== $request->get_param( $field ) ) {
				$payload[ $field ] = $request->get_param( $field );
			}
		}

		return $payload;
	}

	/**
	 * Extract selected follow-up topic ids.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return array<int, int>
	 */
	protected function extractNextTopicIds( WP_REST_Request $request ): array {
		$next_topic_ids = $request->get_param( 'next_topic_ids' );

		if ( ! is_array( $next_topic_ids ) ) {
			return array();
		}

		return array_values( array_unique( array_filter( array_map( 'intval', $next_topic_ids ) ) ) );
	}

	/**
	 * Extract selected parent topic ids.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return array<int, int>
	 */
	protected function extractParentTopicIds( WP_REST_Request $request ): array {
		$parent_topic_ids = $request->get_param( 'parent_topic_ids' );

		if ( ! is_array( $parent_topic_ids ) ) {
			return array();
		}

		return array_values( array_unique( array_filter( array_map( 'intval', $parent_topic_ids ) ) ) );
	}

	/**
	 * Build a normalized topic response payload.
	 *
	 * @param int $topic_id Topic id.
	 * @return array<string, mixed>|null
	 */
	protected function buildTopicResponse( int $topic_id ): ?array {
		$topic = $this->topic_service->getTopic( $topic_id );
		if ( ! $topic ) {
			return null;
		}

		$topic['next_topic_ids'] = $this->topic_transition_service->getSelectableNextTopicIds( $topic_id );
		$topic['parent_topic_ids'] = $this->topic_transition_service->getSelectableParentTopicIds( $topic_id );
		$topic['hierarchy_type'] = ! empty( $topic['parent_topic_ids'] ) ? 'follow_up' : 'top_level';

		return $topic;
	}

	/**
	 * Map an internal validation code to an API response message.
	 *
	 * @param string $error_code Validation code.
	 * @return string
	 */
	protected function messageForErrorCode( string $error_code ): string {
		$messages = array(
			'branch-missing-transition' => 'Branch topics must include at least one valid follow-up topic.',
			'invalid-transition'        => 'One or more selected follow-up topics are invalid.',
			'follow-up-missing-parent'  => 'Follow-up topics must include at least one valid parent topic.',
			'invalid-parent-topic'      => 'One or more selected parent topics are invalid.',
			'top-level-has-parent'      => 'Top-level topics cannot have incoming parent transitions.',
		);

		return $messages[ $error_code ] ?? 'Unable to save topic graph.';
	}
}
