<?php
/**
 * @package WP Helpdesk
 */

namespace WPHelpdesk\Interfaces\Rest;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WPHelpdesk\Infrastructure\Database\Schema;
use WPHelpdesk\Infrastructure\Security\RateLimiter;
use WPHelpdesk\Support\Constants;
use WPHelpdesk\Support\Helpers;

/**
 * Handles public (customer-facing) REST endpoints:
 *  GET  /helpdesk/v1/topics            – list active topics
 *  POST /helpdesk/v1/tickets/guest     – create ticket as non-logged-in user
 *  POST /helpdesk/v1/tickets/member    – create ticket as logged-in user
 */
class PublicTicketController {

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
		global $wpdb;

		$table      = Schema::table( Constants::TABLE_TOPICS );
		$network_id = Helpers::getNetworkId();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, slug, title, description, is_final FROM {$table}
				 WHERE is_active = 1 AND network_id = %d
				 ORDER BY sort_order ASC, title ASC",
				$network_id
			),
			ARRAY_A
		);

		return new WP_REST_Response( $rows ?: array(), 200 );
	}

	/**
	 * GET /topics/{id}/transitions - return active follow-up transitions.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response
	 */
	public function listTransitions( WP_REST_Request $request ): WP_REST_Response {
		global $wpdb;

		$transitions_table = Schema::table( Constants::TABLE_TOPIC_TRANSITIONS );
		$topics_table      = Schema::table( Constants::TABLE_TOPICS );
		$network_id        = Helpers::getNetworkId();
		$topic_id          = (int) $request['id'];

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT t.to_topic_id, t.label, tt.title AS to_topic_title, tt.description AS to_topic_description, tt.is_final AS to_topic_is_final
				 FROM {$transitions_table} t
				 INNER JOIN {$topics_table} tt ON tt.id = t.to_topic_id
				 WHERE t.network_id = %d
				   AND t.from_topic_id = %d
				   AND t.is_active = 1
				   AND tt.is_active = 1
				 ORDER BY t.sort_order ASC, t.id ASC",
				$network_id,
				$topic_id
			),
			ARRAY_A
		);

		$payload = array_map(
			static function ( array $row ): array {
				return array(
					'to_topic_id' => (int) $row['to_topic_id'],
					'label'       => (string) $row['label'],
					'to_topic'    => array(
						'title'       => (string) $row['to_topic_title'],
						'description' => (string) $row['to_topic_description'],
						'is_final'    => (int) $row['to_topic_is_final'],
					),
				);
			},
			$rows ?: array()
		);

		return new WP_REST_Response( $payload, 200 );
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
			)
		);
	}

	/**
	 * POST /tickets/member – create a ticket for a logged-in user.
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

		$user     = wp_get_current_user();
		$topic_id = (int) $request->get_param( 'topic_id' );
		$subject  = sanitize_text_field( (string) $request->get_param( 'subject' ) );
		$message  = sanitize_textarea_field( (string) $request->get_param( 'message' ) );
		$phone    = sanitize_text_field( (string) $request->get_param( 'requester_phone' ) );
		$name     = $user->display_name ?: trim( $user->first_name . ' ' . $user->last_name );
		if ( '' === $name ) {
			$name = $user->user_login;
		}
		$email    = $user->user_email;
		if ( '' === trim( $phone ) ) {
			$phone = (string) get_user_meta( $user->ID, 'phone', true );
		}
		if ( '' === trim( $phone ) ) {
			$phone = (string) get_user_meta( $user->ID, 'billing_phone', true );
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
				'user_id'         => $user->ID,
				'form_type'       => 'member',
				'topic_path'      => $this->normaliseTopicPath( $request->get_param( 'topic_path' ), $topic_id ),
				'session_token'   => sanitize_text_field( (string) $request->get_param( 'form_session_token' ) ),
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

		$ticket_no  = Helpers::generateTicketNo();
		$network_id = Helpers::getNetworkId();
		$site_id    = Helpers::getCurrentSiteId();
		$table      = Schema::table( Constants::TABLE_TICKETS );
		$msg_table  = Schema::table( Constants::TABLE_TICKET_MESSAGES );

		$inserted = $wpdb->insert(
			$table,
			array(
				'network_id'      => $network_id,
				'site_id'         => $site_id,
				'ticket_no'       => $ticket_no,
				'requester_name'  => $data['requester_name'],
				'requester_email' => $data['requester_email'],
				'requester_phone' => $data['requester_phone'],
				'user_id'         => $data['user_id'],
				'subject'         => $data['subject'],
				'topic_path_json' => wp_json_encode( $data['topic_path'] ?? array( (int) $data['topic_id'] ) ),
				'status'          => 'new',
				'created_at'      => current_time( 'mysql' ),
				'updated_at'      => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s' )
		);

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

		$ticket = array_merge( $data, array( 'id' => $ticket_id, 'ticket_no' => $ticket_no ) );

		do_action( 'hd_ticket_created', $ticket );

		if ( ! empty( $data['session_token'] ) ) {
			$this->deleteFormSession( (string) $data['session_token'] );
		}

		return new WP_REST_Response(
			array(
				'ticket_no' => $ticket_no,
				'message'   => __( 'Your support request has been submitted.', 'wp-helpdesk' ),
			),
			201
		);
	}

	/**
	 * Argument definitions for the guest ticket endpoint.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	protected function guestTicketArgs(): array {
		return array(
			'topic_id'       => array( 'required' => false, 'type' => 'integer', 'minimum' => 0 ),
			'requester_name' => array( 'required' => true, 'type' => 'string', 'minLength' => 1, 'maxLength' => 255 ),
			'requester_email'=> array( 'required' => true, 'type' => 'string', 'format' => 'email' ),
			'requester_phone'=> array( 'required' => true, 'type' => 'string', 'minLength' => 1, 'maxLength' => 50 ),
			'subject'        => array( 'required' => true, 'type' => 'string', 'minLength' => 1, 'maxLength' => 255 ),
			'message'        => array( 'required' => true, 'type' => 'string', 'minLength' => 1 ),
		);
	}

	/**
	 * Argument definitions for the member ticket endpoint.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	protected function memberTicketArgs(): array {
		return array(
			'topic_id' => array( 'required' => false, 'type' => 'integer', 'minimum' => 0 ),
			'requester_phone'=> array( 'required' => true, 'type' => 'string', 'minLength' => 1, 'maxLength' => 50 ),
			'subject'  => array( 'required' => true, 'type' => 'string', 'minLength' => 1, 'maxLength' => 255 ),
			'message'  => array( 'required' => true, 'type' => 'string', 'minLength' => 1 ),
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

		$exists = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE session_token = %s LIMIT 1",
				$token
			)
		);

		if ( $exists > 0 ) {
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
				array( 'id' => $exists ),
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
					'payload_json'     => $payload_json,
					'expires_at'       => $expires_at,
					'created_at'       => current_time( 'mysql' ),
					'updated_at'       => current_time( 'mysql' ),
				),
				array( '%d', '%d', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s' )
			);
		}

		return new WP_REST_Response( array( 'ok' => true ), 200 );
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
}
