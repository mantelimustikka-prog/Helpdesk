<?php
/**
 * @package WP Helpdesk
 */

namespace WPHelpdesk\Interfaces\Rest;

use WP_REST_Request;
use WP_REST_Response;
use WPHelpdesk\Domain\Topic\TopicService;

class AdminTopicsController {
	protected TopicService $topic_service;

	public function __construct() {
		$this->topic_service = new TopicService();
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
		$topic_id = $this->topic_service->createTopic( $this->extractPayload( $request ) );
		if ( $topic_id <= 0 ) {
			return new WP_REST_Response( array( 'message' => 'Failed to create topic.' ), 400 );
		}

		$topic = $this->topic_service->getTopic( $topic_id );

		return new WP_REST_Response( $topic, 201 );
	}

	/**
	 * Get a single admin topic.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function getTopic( WP_REST_Request $request ): WP_REST_Response {
		$topic = $this->topic_service->getTopic( (int) $request['id'] );
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
		$topic_id = (int) $request['id'];
		$updated  = $this->topic_service->updateTopic( $topic_id, $this->extractPayload( $request ) );
		if ( ! $updated ) {
			return new WP_REST_Response( array( 'message' => 'Unable to update topic.' ), 400 );
		}

		$topic = $this->topic_service->getTopic( $topic_id );

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

		foreach ( array( 'name', 'slug', 'description', 'sort_order', 'is_active' ) as $field ) {
			if ( null !== $request->get_param( $field ) ) {
				$payload[ $field ] = $request->get_param( $field );
			}
		}

		return $payload;
	}
}
