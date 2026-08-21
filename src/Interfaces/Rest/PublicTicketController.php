<?php
/**
 * @package WP Helpdesk
 */

namespace WPHelpdesk\Interfaces\Rest;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WPHelpdesk\Domain\KnowledgeBase\KnowledgeBaseService;
use WPHelpdesk\Infrastructure\Database\Schema;
use WPHelpdesk\Infrastructure\Security\RateLimiter;
use WPHelpdesk\Support\Constants;
use WPHelpdesk\Support\Helpers;
use WPHelpdesk\Domain\Topic\TopicService;
use WPHelpdesk\Domain\Topic\TopicTransitionService;

/**
 * Handles public (customer-facing) REST endpoints:
 *  GET  /helpdesk/v1/topics            – list active topics
 *  POST /helpdesk/v1/tickets/guest     – create ticket as non-logged-in user
 *  POST /helpdesk/v1/tickets/member    – create ticket as logged-in user
 */
class PublicTicketController {
	protected TopicService $topic_service;
	protected TopicTransitionService $topic_transition_service;
	protected KnowledgeBaseService $kb_service;

	public function __construct( ?TopicService $topic_service = null, ?TopicTransitionService $topic_transition_service = null, ?KnowledgeBaseService $kb_service = null ) {
		$this->topic_service            = $topic_service ?: new TopicService();
		$this->topic_transition_service = $topic_transition_service ?: new TopicTransitionService();
		$this->kb_service               = $kb_service ?: new KnowledgeBaseService();
	}

	/**
	 * Register routes via Routes class; called from Routes::register_rest_routes().
	 *
	 * @param string $namespace REST namespace.
	 * @return void
	 */
	public function register( string $namespace ): void {
		register_rest_route(
			$namespace,
			'/topics',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'listTopics' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			$namespace,
			'/topics/(?P<id>\d+)/transitions',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'listTransitions' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			$namespace,
			'/topics/(?P<id>\d+)/children',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'listChildren' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			$namespace,
			'/tickets/guest',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'submitGuestTicket' ),
				'permission_callback' => '__return_true',
				'args'                => $this->guestTicketArgs(),
			)
		);

		register_rest_route(
			$namespace,
			'/tickets/member',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'submitMemberTicket' ),
				'permission_callback' => array( $this, 'requireLogin' ),
				'args'                => $this->memberTicketArgs(),
			)
		);

		register_rest_route(
			$namespace,
			'/form-sessions',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'upsertFormSession' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			$namespace,
			'/form-sessions/restart',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'restartFormSession' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			$namespace,
			'/user-orders',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'listUserOrders' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			$namespace,
			'/tickets/guest-reply',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'submitGuestReply' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'ticket_no'   => array( 'required' => true, 'type' => 'string', 'minLength' => 1 ),
					'guest_token' => array( 'required' => true, 'type' => 'string', 'minLength' => 1 ),
					'message'     => array( 'required' => true, 'type' => 'string', 'minLength' => 1 ),
				),
			)
		);

		register_rest_route(
			$namespace,
			'/kb/search',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'searchKnowledgeBase' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			$namespace,
			'/kb/topics/(?P<article_id>[^/]+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'getKnowledgeBaseTopic' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			$namespace,
			'/kb/suggest',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'suggestKnowledgeBase' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Permission callback that enforces authentication.
	 *
	 * @return bool|WP_Error
	 */
	public function requireLogin() {
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'hd_auth_required', __( 'You must be logged in to submit a member request.', 'wp-helpdesk' ), array( 'status' => 401 ) );
		}

		return true;
	}

	/**
	 * GET /topics – return active topics for the current network.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response
	 */
	public function listTopics( WP_REST_Request $request ): WP_REST_Response {
		$topics = array_map(
			static fn( array $topic ): array => array(
				'id'          => (int) ( $topic['id'] ?? 0 ),
				'slug'        => (string) ( $topic['slug'] ?? '' ),
				'title'       => (string) ( $topic['name'] ?? $topic['title'] ?? '' ),
				'description' => (string) ( $topic['description'] ?? '' ),
				'is_final'    => (int) ! empty( $topic['is_final'] ),
			),
			$this->topic_service->listTopLevelTopics()
		);

		return new WP_REST_Response( $topics, 200 );
	}

	/**
	 * GET /topics/{id}/transitions - return active follow-up transitions.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response
	 */
	public function listTransitions( WP_REST_Request $request ): WP_REST_Response {
		$topic_id          = (int) $request['id'];
		$payload           = array();
		$transitions       = $this->topic_transition_service->listValidFrom( $topic_id, true );
		$target_topics     = $this->topic_service->getTopicsByIds(
			array_map(
				static fn( array $transition ): int => (int) ( $transition['to_topic_id'] ?? 0 ),
				$transitions
			)
		);

		foreach ( $transitions as $transition ) {
			$to_topic_id = (int) ( $transition['to_topic_id'] ?? 0 );
			$target      = $target_topics[ $to_topic_id ] ?? null;
			if ( ! $target ) {
				continue;
			}

			$payload[] = array(
				'to_topic_id' => $to_topic_id,
				'label'       => (string) ( $transition['label'] ?? '' ),
				'to_topic'    => array(
					'title'       => (string) ( $target['name'] ?? $target['title'] ?? '' ),
					'description' => (string) ( $target['description'] ?? '' ),
					'is_final'    => (int) ! empty( $target['is_final'] ),
				),
			);
		}

		return new WP_REST_Response( $payload, 200 );
	}

	/**
	 * GET /topics/{id}/children - return active child topics (parent_id model).
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response
	 */
	public function listChildren( WP_REST_Request $request ): WP_REST_Response {
		$topic_id = (int) $request['id'];
		$topic    = $this->topic_service->getTopic( $topic_id );
		if ( ! $topic ) {
			return new WP_REST_Response( array( 'message' => 'Topic not found.' ), 404 );
		}

		$children = $this->topic_service->listChildrenOf( $topic_id );

		$payload = array_map(
			static fn( array $topic ): array => array(
				'id'          => (int) ( $topic['id'] ?? 0 ),
				'slug'        => (string) ( $topic['slug'] ?? '' ),
				'title'       => (string) ( $topic['name'] ?? $topic['title'] ?? '' ),
				'description' => (string) ( $topic['description'] ?? '' ),
				'is_final'    => (int) ! empty( $topic['is_final'] ),
				'has_children' => false, // Will be enriched below.
			),
			$children
		);

		// Enrich each child with a has_children flag.
		foreach ( $payload as &$child ) {
			$child['has_children'] = ! $this->topic_service->isLeafTopic( (int) $child['id'] );
		}
		unset( $child );

		return new WP_REST_Response( $payload, 200 );
	}

	/**
	 * GET /user-orders – return order objects available for the current user.
	 *
	 * Each object has `id` (WC order ID as string) and `number` (human-readable
	 * order number). Returns an empty array for guests (not authenticated).
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response
	 */
	public function listUserOrders( WP_REST_Request $request ): WP_REST_Response {
		if ( ! is_user_logged_in() ) {
			return new WP_REST_Response( array(), 200 );
		}

		$orders = $this->getUserLifetimeOrderObjects( get_current_user_id() );

		return new WP_REST_Response( $orders, 200 );
	}

	/**
	 * GET /kb – search knowledge base articles.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response
	 */
	public function searchKnowledgeBase( WP_REST_Request $request ): WP_REST_Response {
		$query      = sanitize_text_field( (string) $request->get_param( 'query' ) );
		$topic_path = $this->normalizeKnowledgeBasePath( $request->get_param( 'topic_path' ) );
		$limit      = max( 1, min( 10, (int) ( $request->get_param( 'limit' ) ?: 5 ) ) );

		return new WP_REST_Response( $this->kb_service->searchTopics( $query, $topic_path, $limit ), 200 );
	}

	/**
	 * GET /kb/topics/{article_id} – retrieve one KB result.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response
	 */
	public function getKnowledgeBaseTopic( WP_REST_Request $request ): WP_REST_Response {
		$article = $this->kb_service->getTopicById( (string) $request['article_id'] );
		if ( null === $article ) {
			return new WP_REST_Response( array( 'message' => 'Knowledge base topic not found.' ), 404 );
		}

		return new WP_REST_Response( $article, 200 );
	}

	/**
	 * GET /kb/suggest – suggest KB entries from the current topic path.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response
	 */
	public function suggestKnowledgeBase( WP_REST_Request $request ): WP_REST_Response {
		$query      = sanitize_text_field( (string) $request->get_param( 'query' ) );
		$topic_path = $this->normalizeKnowledgeBasePath( $request->get_param( 'topic_path' ) );
		$limit      = max( 1, min( 10, (int) ( $request->get_param( 'limit' ) ?: 5 ) ) );

		return new WP_REST_Response( $this->kb_service->suggestByPath( $topic_path, $query, $limit ), 200 );
	}

	/**
	 * POST /tickets/guest – create a ticket for a non-authenticated user.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function submitGuestTicket( WP_REST_Request $request ) {
		if ( 1 !== (int) get_site_option( Constants::OPTION_GENERAL_ALLOW_GUEST, 1 ) ) {
			return new WP_Error( 'hd_guest_disabled', __( 'Guest ticket submission is disabled.', 'wp-helpdesk' ), array( 'status' => 403 ) );
		}

		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( empty( $nonce ) ) {
			$nonce = $request->get_param( '_wpnonce' );
		}

		if ( empty( $nonce ) || ! wp_verify_nonce( (string) $nonce, 'wp_rest' ) ) {
			return new WP_Error( 'hd_invalid_nonce', __( 'Invalid or missing nonce.', 'wp-helpdesk' ), array( 'status' => 403 ) );
		}

		// Reject guests that select "Existing order related" before rate-limiting so
		// the attempt is not counted and the user immediately sees the login prompt.
		$order_relation = sanitize_text_field( (string) $request->get_param( 'order_relation' ) );
		if ( 'existing_order_related' === $order_relation ) {
			return new WP_Error( 'hd_login_required', __( 'You must login to create this support request.', 'wp-helpdesk' ), array( 'status' => 401 ) );
		}

		$rate_limiter = new RateLimiter();
		$rate_key     = 'guest-ticket:' . $this->resolveClientIp( $request ) . ':' . strtolower( (string) $request->get_param( 'requester_email' ) );
		if ( ! $rate_limiter->checkAndIncrement( $rate_key, 8, HOUR_IN_SECONDS ) ) {
			return new WP_Error( 'hd_rate_limited', __( 'Too many requests. Please wait and try again.', 'wp-helpdesk' ), array( 'status' => 429 ) );
		}

		$topic_id = (int) $request->get_param( 'topic_id' );
		$name     = sanitize_text_field( (string) $request->get_param( 'requester_name' ) );
		$email    = sanitize_email( (string) $request->get_param( 'requester_email' ) );
		$phone    = sanitize_text_field( (string) $request->get_param( 'requester_phone' ) );
		$subject  = sanitize_text_field( (string) $request->get_param( 'subject' ) );
		$message  = sanitize_textarea_field( (string) $request->get_param( 'message' ) );

		if ( ! is_email( $email ) ) {
			return new WP_Error( 'hd_invalid_email', __( 'Please provide a valid email address.', 'wp-helpdesk' ), array( 'status' => 422 ) );
		}
		if ( '' === trim( $phone ) ) {
			return new WP_Error( 'hd_invalid_phone', __( 'Please provide a phone number.', 'wp-helpdesk' ), array( 'status' => 422 ) );
		}

		return $this->createTicket(
			array(
				'topic_id'        => $topic_id,
				'requester_name'  => $name,
				'requester_email' => $email,
				'requester_phone' => $phone,
				'subject'         => $subject,
				'message'         => $message,
				'user_id'         => null,
				'form_type'       => 'guest',
				'topic_path'      => $this->normaliseTopicPath( $request->get_param( 'topic_path' ), $topic_id ),
				'session_token'   => sanitize_text_field( (string) $request->get_param( 'form_session_token' ) ),
				'order_relation'  => $order_relation,
			)
		);
	}

	/**
	 * POST /tickets/member – create a ticket for a logged-in user.
	 *
	 * Identity fields (name, email, phone) are sourced exclusively from the user
	 * account – any values submitted by the client are ignored server-side.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function submitMemberTicket( WP_REST_Request $request ) {
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( empty( $nonce ) ) {
			$nonce = $request->get_param( '_wpnonce' );
		}

		if ( empty( $nonce ) || ! wp_verify_nonce( (string) $nonce, 'wp_rest' ) ) {
			return new WP_Error( 'hd_invalid_nonce', __( 'Invalid or missing nonce.', 'wp-helpdesk' ), array( 'status' => 403 ) );
		}

		$user    = wp_get_current_user();
		$topic_id = (int) $request->get_param( 'topic_id' );
		$subject  = sanitize_text_field( (string) $request->get_param( 'subject' ) );
		$message  = sanitize_textarea_field( (string) $request->get_param( 'message' ) );

		// Identity is locked to the authenticated user – client-submitted values are discarded.
		$name = $user->display_name ?: trim( $user->first_name . ' ' . $user->last_name );
		if ( '' === $name ) {
			$name = $user->user_login;
		}
		$email = $user->user_email;
		$phone = (string) get_user_meta( $user->ID, 'phone', true );
		if ( '' === trim( $phone ) ) {
			$phone = (string) get_user_meta( $user->ID, 'billing_phone', true );
		}
		if ( '' === trim( $phone ) ) {
			return new WP_Error( 'hd_invalid_phone', __( 'Please provide a phone number.', 'wp-helpdesk' ), array( 'status' => 422 ) );
		}

		$order_relation = sanitize_text_field( (string) $request->get_param( 'order_relation' ) );
		$order_validation = $this->validateOrderRelation( $order_relation, $user->ID );
		if ( is_wp_error( $order_validation ) ) {
			return $order_validation;
		}

		return $this->createTicket(
			array(
				'topic_id'        => $topic_id,
				'requester_name'  => $name,
				'requester_email' => $email,
				'requester_phone' => $phone,
				'subject'         => $subject,
				'message'         => $message,
				'user_id'         => $user->ID,
				'form_type'       => 'member',
				'topic_path'      => $this->normaliseTopicPath( $request->get_param( 'topic_path' ), $topic_id ),
				'session_token'   => sanitize_text_field( (string) $request->get_param( 'form_session_token' ) ),
				'order_relation'  => $order_relation,
			)
		);
	}

	/**
	 * Insert a ticket and its first message, then fire the created action.
	 *
	 * @param array<string, mixed> $data Normalised ticket data.
	 * @return WP_REST_Response|WP_Error
	 */
	protected function createTicket( array $data ) {
		global $wpdb;

		$require_topic = 1 === (int) get_site_option( Constants::OPTION_GENERAL_REQUIRE_TOPIC, 1 );

		if ( ( $require_topic && empty( $data['topic_id'] ) ) || empty( $data['subject'] ) || empty( $data['message'] ) || empty( $data['requester_phone'] ) ) {
			return new WP_Error( 'hd_missing_fields', __( 'Please fill in all required fields.', 'wp-helpdesk' ), array( 'status' => 422 ) );
		}

		// Validate order_relation is present and non-empty.
		$order_relation = isset( $data['order_relation'] ) ? sanitize_text_field( (string) $data['order_relation'] ) : '';
		if ( '' === $order_relation ) {
			return new WP_Error( 'hd_missing_order_relation', __( 'Please select an order relation.', 'wp-helpdesk' ), array( 'status' => 422 ) );
		}

		$topic_path = $this->normaliseTopicPath( $data['topic_path'] ?? array(), (int) ( $data['topic_id'] ?? 0 ) );
		if ( ! empty( $data['topic_id'] ) ) {
			$topic_path_validation = $this->validateTopicPath( $topic_path, (int) $data['topic_id'] );
			if ( is_wp_error( $topic_path_validation ) ) {
				return $topic_path_validation;
			}
		}

		$ticket_no   = Helpers::generateTicketNo();
		$network_id  = Helpers::getNetworkId();
		$site_id     = Helpers::getCurrentSiteId();
		$table       = Schema::table( Constants::TABLE_TICKETS );
		$msg_table   = Schema::table( Constants::TABLE_TICKET_MESSAGES );
		$guest_token = 'guest' === ( $data['form_type'] ?? '' ) ? $this->generateGuestToken() : null;
		$ticket_link = null;

		$insert_data   = array(
			'network_id'      => $network_id,
			'site_id'         => $site_id,
			'ticket_no'       => $ticket_no,
			'requester_name'  => $data['requester_name'],
			'requester_email' => $data['requester_email'],
			'requester_phone' => $data['requester_phone'],
			'user_id'         => $data['user_id'],
			'subject'         => $data['subject'],
			'topic_path_json' => wp_json_encode( $topic_path ),
			'order_relation'  => $order_relation,
			'status'          => 'new',
			'created_at'      => current_time( 'mysql' ),
			'updated_at'      => current_time( 'mysql' ),
		);
		$insert_format = array( '%d', '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s' );

		if ( null !== $guest_token ) {
			$insert_data['guest_token_hash'] = $this->hashGuestToken( $guest_token );
			$insert_format[]                 = '%s';
			$ticket_link                     = home_url( '/helpdesk/ticket/' . rawurlencode( $ticket_no ) . '/' . rawurlencode( $guest_token ) . '/' );
		}

		$inserted = $wpdb->insert( $table, $insert_data, $insert_format );

		if ( ! $inserted ) {
			return new WP_Error( 'hd_db_error', __( 'Could not save your request. Please try again.', 'wp-helpdesk' ), array( 'status' => 500 ) );
		}

		$ticket_id = (int) $wpdb->insert_id;

		$msg_inserted = $wpdb->insert(
			$msg_table,
			array(
				'ticket_id'  => $ticket_id,
				'author_user_id'  => $data['user_id'] ?? 0,
				'author_type'     => empty( $data['user_id'] ) ? 'guest' : 'member',
				'body'       => $data['message'],
				'is_internal' => 0,
				'created_at' => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%s', '%d', '%s' )
		);

		if ( ! $msg_inserted ) {
			return new WP_Error( 'hd_db_error', __( 'Could not save your request. Please try again.', 'wp-helpdesk' ), array( 'status' => 500 ) );
		}

		$ticket = array_merge(
			$data,
			array(
				'id'         => $ticket_id,
				'ticket_no'  => $ticket_no,
				'ticket_link' => $ticket_link,
			)
		);
		if ( null !== $guest_token ) {
			$ticket['guest_token'] = $guest_token;
		}

		do_action( 'hd_ticket_created', $ticket );

		if ( ! empty( $data['session_token'] ) ) {
			$this->deleteFormSession( (string) $data['session_token'] );
		}

		$response_data = array(
			'ticket_no' => $ticket_no,
			'message'   => __( 'Your support request has been submitted.', 'wp-helpdesk' ),
		);

		if ( null !== $ticket_link ) {
			$response_data['ticket_link'] = $ticket_link;
		}

		return new WP_REST_Response( $response_data, 201 );
	}

	/**
	 * Argument definitions for the guest ticket endpoint.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	protected function guestTicketArgs(): array {
		return array(
			'topic_id'        => array( 'required' => false, 'type' => 'integer', 'minimum' => 0 ),
			'requester_name'  => array( 'required' => true, 'type' => 'string', 'minLength' => 1, 'maxLength' => 255 ),
			'requester_email' => array( 'required' => true, 'type' => 'string', 'format' => 'email' ),
			'requester_phone' => array( 'required' => true, 'type' => 'string', 'minLength' => 1, 'maxLength' => 50 ),
			'subject'         => array( 'required' => true, 'type' => 'string', 'minLength' => 1, 'maxLength' => 255 ),
			'message'         => array( 'required' => true, 'type' => 'string', 'minLength' => 1 ),
			'order_relation'  => array( 'required' => false, 'type' => 'string' ),
		);
	}

	/**
	 * Argument definitions for the member ticket endpoint.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	protected function memberTicketArgs(): array {
		return array(
			'topic_id'       => array( 'required' => false, 'type' => 'integer', 'minimum' => 0 ),
			'subject'        => array( 'required' => true, 'type' => 'string', 'minLength' => 1, 'maxLength' => 255 ),
			'message'        => array( 'required' => true, 'type' => 'string', 'minLength' => 1 ),
			'order_relation' => array( 'required' => false, 'type' => 'string' ),
		);
	}

	/**
	 * Persist or refresh a form session row.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function upsertFormSession( WP_REST_Request $request ) {
		global $wpdb;

		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( empty( $nonce ) ) {
			$nonce = $request->get_param( '_wpnonce' );
		}
		if ( empty( $nonce ) || ! wp_verify_nonce( (string) $nonce, 'wp_rest' ) ) {
			return new WP_Error( 'hd_invalid_nonce', __( 'Invalid or missing nonce.', 'wp-helpdesk' ), array( 'status' => 403 ) );
		}

		$token = sanitize_text_field( (string) $request->get_param( 'session_token' ) );
		if ( '' === $token ) {
			return new WP_Error( 'hd_missing_session_token', __( 'Missing form session token.', 'wp-helpdesk' ), array( 'status' => 422 ) );
		}

		$table      = Schema::table( Constants::TABLE_FORM_SESSIONS );
		$network_id = Helpers::getNetworkId();
		$site_id    = Helpers::getCurrentSiteId();
		$form_type  = 'member' === $request->get_param( 'form_type' ) ? 'member' : 'guest';
		$step_index = max( 0, (int) $request->get_param( 'step_index' ) );
		$topic_id   = max( 0, (int) $request->get_param( 'current_topic_id' ) );
		$payload    = $request->get_param( 'payload' );
		$payload_json = wp_json_encode( is_array( $payload ) ? $payload : array() );
		$ttl        = max( 900, (int) get_site_option( 'hd_guest_session_ttl', DAY_IN_SECONDS ) );
		$expires_at = gmdate( 'Y-m-d H:i:s', time() + $ttl );
		$user_id    = is_user_logged_in() ? get_current_user_id() : null;

		// Client-supplied reset counter: null means omitted (legacy / new row).
		$client_reset_counter = $request->get_param( 'reset_counter' );
		$client_reset_counter = null !== $client_reset_counter ? (int) $client_reset_counter : null;

		$existing = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, reset_counter FROM {$table} WHERE session_token = %s LIMIT 1",
				$token
			),
			ARRAY_A
		);

		if ( $existing ) {
			$server_counter = (int) ( $existing['reset_counter'] ?? 0 );

			// If the client provided a reset_counter that doesn't match the server's,
			// this write is stale (post-reset) – reject it silently so the server
			// state set by the restart endpoint is not overwritten.
			if ( null !== $client_reset_counter && $client_reset_counter !== $server_counter ) {
				return new WP_REST_Response( array( 'ok' => false, 'stale' => true ), 409 );
			}

			$wpdb->update(
				$table,
				array(
					'user_id'          => $user_id,
					'form_type'        => $form_type,
					'current_topic_id' => $topic_id ?: null,
					'step_index'       => $step_index,
					'payload_json'     => $payload_json,
					'expires_at'       => $expires_at,
					'updated_at'       => current_time( 'mysql' ),
				),
				array( 'id' => (int) $existing['id'] ),
				array( '%s', '%s', '%d', '%d', '%s', '%s', '%s' ),
				array( '%d' )
			);
		} else {
			$wpdb->insert(
				$table,
				array(
					'network_id'       => $network_id,
					'site_id'          => $site_id,
					'session_token'    => $token,
					'user_id'          => $user_id,
					'form_type'        => $form_type,
					'current_topic_id' => $topic_id ?: null,
					'step_index'       => $step_index,
					'reset_counter'    => 0,
					'payload_json'     => $payload_json,
					'expires_at'       => $expires_at,
					'created_at'       => current_time( 'mysql' ),
					'updated_at'       => current_time( 'mysql' ),
				),
				array( '%d', '%d', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s', '%s', '%s' )
			);
		}

		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}

	/**
	 * POST /form-sessions/restart – reset a session to step 0 and clear branch state.
	 *
	 * Requires the same nonce protection as upsertFormSession.
	 * Returns 200 {"ok": true, "reset_counter": N} on success, or a WP_Error on failure.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function restartFormSession( WP_REST_Request $request ) {
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( empty( $nonce ) ) {
			$nonce = $request->get_param( '_wpnonce' );
		}
		if ( empty( $nonce ) || ! wp_verify_nonce( (string) $nonce, 'wp_rest' ) ) {
			return new WP_Error( 'hd_invalid_nonce', __( 'Invalid or missing nonce.', 'wp-helpdesk' ), array( 'status' => 403 ) );
		}

		$token = sanitize_text_field( (string) $request->get_param( 'session_token' ) );
		if ( '' === $token ) {
			return new WP_Error( 'hd_missing_session_token', __( 'Missing form session token.', 'wp-helpdesk' ), array( 'status' => 422 ) );
		}

		$session_service = new \WPHelpdesk\Domain\Session\SubmissionSessionService();
		$new_counter     = $session_service->restart( $token );

		if ( false === $new_counter ) {
			return new WP_Error( 'hd_session_not_found', __( 'Session not found or expired.', 'wp-helpdesk' ), array( 'status' => 404 ) );
		}

		return new WP_REST_Response( array( 'ok' => true, 'reset_counter' => $new_counter ), 200 );
	}

	/**
	 * Validate an order_relation value.
	 *
	 * Acceptable values:
	 *  - 'not_order_related'
	 *  - a WooCommerce order ID (as a string) belonging to the current user
	 *
	 * For guests (user_id = null), only 'not_order_related' is valid; guests
	 * cannot submit an existing-order relation without logging in.
	 *
	 * @param string   $order_relation The submitted value.
	 * @param int|null $user_id        Authenticated user id, or null for guests.
	 * @return true|WP_Error
	 */
	protected function validateOrderRelation( string $order_relation, ?int $user_id ) {
		if ( '' === $order_relation ) {
			return new WP_Error( 'hd_missing_order_relation', __( 'Please select an order relation.', 'wp-helpdesk' ), array( 'status' => 422 ) );
		}

		if ( 'not_order_related' === $order_relation ) {
			return true;
		}

		// For authenticated users validate ownership against their order IDs.
		if ( null !== $user_id && $user_id > 0 ) {
			$user_orders = $this->getUserLifetimeOrders( $user_id );
			if ( ! in_array( $order_relation, $user_orders, true ) ) {
				return new WP_Error( 'hd_invalid_order_relation', __( 'The selected order does not belong to your account.', 'wp-helpdesk' ), array( 'status' => 422 ) );
			}
		}

		return true;
	}

	/**
	 * Return all lifetime WooCommerce order IDs for a user as strings.
	 *
	 * The stored value in order_relation is the WC order ID (numeric string) so
	 * that the admin can later construct a direct link to the order.
	 *
	 * Falls back to an empty array when WooCommerce is not active.
	 *
	 * @param int $user_id WordPress user id.
	 * @return array<int, string>
	 */
	protected function getUserLifetimeOrders( int $user_id ): array {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return array();
		}

		$orders = wc_get_orders(
			array(
				'customer' => $user_id,
				'limit'    => -1,
				'return'   => 'ids',
			)
		);

		if ( ! is_array( $orders ) ) {
			return array();
		}

		return array_values(
			array_filter(
				array_map(
					static function ( $order_id ): string {
						$order_id = (int) $order_id;
						return $order_id > 0 ? (string) $order_id : '';
					},
					$orders
				),
				static fn( string $v ): bool => '' !== $v
			)
		);
	}

	/**
	 * Return all lifetime WooCommerce orders for a user as {id, number} objects.
	 *
	 * Used by the listUserOrders REST endpoint so the frontend can display the
	 * human-readable order number while submitting the order ID as the value.
	 *
	 * @param int $user_id WordPress user id.
	 * @return array<int, array{id: string, number: string}>
	 */
	protected function getUserLifetimeOrderObjects( int $user_id ): array {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return array();
		}

		$ids = $this->getUserLifetimeOrders( $user_id );
		$result = array();

		foreach ( $ids as $order_id_str ) {
			$order_id = (int) $order_id_str;
			$number   = $order_id_str;

			if ( function_exists( 'wc_get_order' ) ) {
				$order = wc_get_order( $order_id );
				if ( $order ) {
					$number = (string) $order->get_order_number();
				}
			}

			$result[] = array(
				'id'     => $order_id_str,
				'number' => $number,
			);
		}

		return $result;
	}

	/**
	 * Remove a session row after successful ticket creation.
	 *
	 * @param string $token Session token.
	 * @return void
	 */
	protected function deleteFormSession( string $token ): void {
		global $wpdb;

		$table = Schema::table( Constants::TABLE_FORM_SESSIONS );
		$wpdb->delete(
			$table,
			array( 'session_token' => $token ),
			array( '%s' )
		);
	}

	/**
	 * Build a normalised topic path array.
	 *
	 * @param mixed $raw_path Raw input path.
	 * @param int   $topic_id Final topic id.
	 * @return array<int, int>
	 */
	protected function normaliseTopicPath( $raw_path, int $topic_id ): array {
		$path = is_array( $raw_path ) ? $raw_path : array();
		$path = array_values(
			array_filter(
				array_map( 'intval', $path ),
				static fn( int $value ): bool => $value > 0
			)
		);
		if ( empty( $path ) || (int) end( $path ) !== $topic_id ) {
			$path[] = $topic_id;
		}

		return $path;
	}

	/**
	 * Validate that a topic path follows runtime hierarchy constraints.
	 *
	 * @param array<int, int> $topic_path Normalized topic path.
	 * @param int             $topic_id Final topic id.
	 * @return true|WP_Error
	 */
	protected function validateTopicPath( array $topic_path, int $topic_id ) {
		if ( $topic_id <= 0 ) {
			return true;
		}

		if ( empty( $topic_path ) || (int) end( $topic_path ) !== $topic_id ) {
			return new WP_Error( 'hd_invalid_topic_path', __( 'Selected topic path is invalid.', 'wp-helpdesk' ), array( 'status' => 422 ) );
		}

		$first_topic_id = (int) $topic_path[0];
		if ( ! $this->topic_service->isTopLevelTopic( $first_topic_id ) ) {
			return new WP_Error( 'hd_invalid_topic_path', __( 'Selected topic path is invalid.', 'wp-helpdesk' ), array( 'status' => 422 ) );
		}

		$topics_by_id = $this->topic_service->getTopicsByIds( $topic_path );
		$valid_next_ids_by_topic = array();

		foreach ( $topic_path as $index => $path_topic_id ) {
			$topic = $topics_by_id[ (int) $path_topic_id ] ?? null;
			if ( ! $topic || ( isset( $topic['is_active'] ) && empty( $topic['is_active'] ) ) ) {
				return new WP_Error( 'hd_invalid_topic_path', __( 'Selected topic path is invalid.', 'wp-helpdesk' ), array( 'status' => 422 ) );
			}

			if ( 0 === $index ) {
				continue;
			}

			$from_topic_id = (int) $topic_path[ $index - 1 ];
			if ( ! isset( $valid_next_ids_by_topic[ $from_topic_id ] ) ) {
				$valid_next_ids_by_topic[ $from_topic_id ] = array_map(
					static fn( array $transition ): int => (int) ( $transition['to_topic_id'] ?? 0 ),
					$this->topic_transition_service->listValidFrom( $from_topic_id, true )
				);
			}

			if ( ! in_array( (int) $path_topic_id, $valid_next_ids_by_topic[ $from_topic_id ], true ) ) {
				return new WP_Error( 'hd_invalid_topic_path', __( 'Selected topic path is invalid.', 'wp-helpdesk' ), array( 'status' => 422 ) );
			}
		}

		return true;
	}

	/**
	 * Normalize topic-path context for knowledge-base queries.
	 *
	 * @param mixed $raw_path Raw topic path input.
	 * @return array<int, int>
	 */
	protected function normalizeKnowledgeBasePath( $raw_path ): array {
		if ( is_array( $raw_path ) ) {
			return array_values(
				array_filter(
					array_map( 'intval', $raw_path ),
					static fn( int $value ): bool => $value > 0
				)
			);
		}

		return array();
	}

	/**
	 * Resolve the best-effort client IP for rate-limiting.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return string
	 */
	protected function resolveClientIp( WP_REST_Request $request ): string {
		$remote = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) wp_unslash( $_SERVER['REMOTE_ADDR'] ) : '';
		if ( '' === $remote ) {
			$remote = (string) $request->get_header( 'X-Real-IP' );
		}
		return sanitize_text_field( $remote );
	}

	/**
	 * Generate a cryptographically random token for guest ticket access.
	 *
	 * @return string 64-character hex token.
	 */
	protected function generateGuestToken(): string {
		return bin2hex( random_bytes( 32 ) );
	}

	/**
	 * Hash a guest token before storage.
	 *
	 * @param string $guest_token Raw guest token.
	 * @return string
	 */
	protected function hashGuestToken( string $guest_token ): string {
		return hash( 'sha256', $guest_token );
	}

	/**
	 * POST /tickets/guest-reply – add a reply to a guest ticket using its token.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function submitGuestReply( WP_REST_Request $request ) {
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( empty( $nonce ) ) {
			$nonce = $request->get_param( '_wpnonce' );
		}
		if ( empty( $nonce ) || ! wp_verify_nonce( (string) $nonce, 'wp_rest' ) ) {
			return new WP_Error( 'hd_invalid_nonce', __( 'Invalid or missing nonce.', 'wp-helpdesk' ), array( 'status' => 403 ) );
		}

		$ticket_no   = sanitize_text_field( (string) $request->get_param( 'ticket_no' ) );
		$guest_token = sanitize_text_field( (string) $request->get_param( 'guest_token' ) );
		$message     = sanitize_textarea_field( (string) $request->get_param( 'message' ) );

		if ( '' === trim( $message ) ) {
			return new WP_Error( 'hd_missing_message', __( 'Message cannot be empty.', 'wp-helpdesk' ), array( 'status' => 422 ) );
		}

		$ticket = $this->findTicketByTokenAndNo( $ticket_no, $guest_token );
		if ( null === $ticket ) {
			return new WP_Error( 'hd_not_found', __( 'Ticket not found.', 'wp-helpdesk' ), array( 'status' => 404 ) );
		}
		$ticket['ticket_link'] = home_url( '/helpdesk/ticket/' . rawurlencode( $ticket_no ) . '/' . rawurlencode( $guest_token ) . '/' );
		$ticket['guest_token'] = $guest_token;

		global $wpdb;
		$msg_table = Schema::table( Constants::TABLE_TICKET_MESSAGES );
		$wpdb->insert(
			$msg_table,
			array(
				'ticket_id'      => (int) $ticket['id'],
				'author_user_id' => 0,
				'author_type'    => 'guest',
				'body'           => $message,
				'is_internal'    => 0,
				'created_at'     => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%s', '%d', '%s' )
		);

		$inserted_msg = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$msg_table} WHERE id = %d LIMIT 1",
				(int) $wpdb->insert_id
			),
			ARRAY_A
		);

		do_action( 'hd_ticket_replied', $ticket, $inserted_msg ?: array() );

		return new WP_REST_Response(
			array( 'message' => __( 'Your reply has been added.', 'wp-helpdesk' ) ),
			201
		);
	}

	/**
	 * Fetch a ticket row that matches both ticket_no and guest_token hash.
	 *
	 * @param string $ticket_no   Ticket number.
	 * @param string $guest_token Guest access token.
	 * @return array<string, mixed>|null
	 */
	public function findTicketByTokenAndNo( string $ticket_no, string $guest_token ): ?array {
		if ( '' === $ticket_no || '' === $guest_token ) {
			return null;
		}
		$guest_token_hash = $this->hashGuestToken( $guest_token );

		global $wpdb;
		$table = Schema::table( Constants::TABLE_TICKETS );
		$row   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE ticket_no = %s AND guest_token_hash = %s LIMIT 1",
				$ticket_no,
				$guest_token_hash
			),
			ARRAY_A
		);

		return $row ?: null;
	}
}
