<?php
/**
 * @package WP Helpdesk
 */

namespace WPHelpdesk\Interfaces\Rest;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WPHelpdesk\Infrastructure\Database\Schema;
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
				"SELECT id, slug, title, description FROM {$table}
				 WHERE is_active = 1 AND network_id = %d
				 ORDER BY sort_order ASC, title ASC",
				$network_id
			),
			ARRAY_A
		);

		return new WP_REST_Response( $rows ?: array(), 200 );
	}

	/**
	 * POST /tickets/guest – create a ticket for a non-authenticated user.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function submitGuestTicket( WP_REST_Request $request ) {
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( empty( $nonce ) ) {
			$nonce = $request->get_param( '_wpnonce' );
		}

		if ( empty( $nonce ) || ! wp_verify_nonce( (string) $nonce, 'wp_rest' ) ) {
			return new WP_Error( 'hd_invalid_nonce', __( 'Invalid or missing nonce.', 'wp-helpdesk' ), array( 'status' => 403 ) );
		}

		$topic_id = (int) $request->get_param( 'topic_id' );
		$name     = sanitize_text_field( (string) $request->get_param( 'requester_name' ) );
		$email    = sanitize_email( (string) $request->get_param( 'requester_email' ) );
		$subject  = sanitize_text_field( (string) $request->get_param( 'subject' ) );
		$message  = sanitize_textarea_field( (string) $request->get_param( 'message' ) );

		if ( ! is_email( $email ) ) {
			return new WP_Error( 'hd_invalid_email', __( 'Please provide a valid email address.', 'wp-helpdesk' ), array( 'status' => 422 ) );
		}

		return $this->createTicket(
			array(
				'topic_id'        => $topic_id,
				'requester_name'  => $name,
				'requester_email' => $email,
				'subject'         => $subject,
				'message'         => $message,
				'user_id'         => null,
				'form_type'       => 'guest',
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
		$name     = $user->display_name ?: trim( $user->first_name . ' ' . $user->last_name );
		if ( '' === $name ) {
			$name = $user->user_login;
		}
		$email    = $user->user_email;

		return $this->createTicket(
			array(
				'topic_id'        => $topic_id,
				'requester_name'  => $name,
				'requester_email' => $email,
				'subject'         => $subject,
				'message'         => $message,
				'user_id'         => $user->ID,
				'form_type'       => 'member',
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

		if ( empty( $data['topic_id'] ) || empty( $data['subject'] ) || empty( $data['message'] ) ) {
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
				'topic_id'        => $data['topic_id'],
				'requester_name'  => $data['requester_name'],
				'requester_email' => $data['requester_email'],
				'user_id'         => $data['user_id'],
				'subject'         => $data['subject'],
				'status'          => 'open',
				'form_type'       => $data['form_type'],
				'created_at'      => current_time( 'mysql' ),
				'updated_at'      => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( ! $inserted ) {
			return new WP_Error( 'hd_db_error', __( 'Could not save your request. Please try again.', 'wp-helpdesk' ), array( 'status' => 500 ) );
		}

		$ticket_id = (int) $wpdb->insert_id;

		$msg_inserted = $wpdb->insert(
			$msg_table,
			array(
				'ticket_id'  => $ticket_id,
				'author_id'  => $data['user_id'] ?? 0,
				'body'       => $data['message'],
				'is_private' => 0,
				'created_at' => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%d', '%s' )
		);

		if ( ! $msg_inserted ) {
			return new WP_Error( 'hd_db_error', __( 'Could not save your request. Please try again.', 'wp-helpdesk' ), array( 'status' => 500 ) );
		}

		$ticket = array_merge( $data, array( 'id' => $ticket_id, 'ticket_no' => $ticket_no ) );

		do_action( 'hd_ticket_created', $ticket );

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
			'topic_id'       => array( 'required' => true, 'type' => 'integer', 'minimum' => 1 ),
			'requester_name' => array( 'required' => true, 'type' => 'string', 'minLength' => 1, 'maxLength' => 255 ),
			'requester_email'=> array( 'required' => true, 'type' => 'string', 'format' => 'email' ),
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
			'topic_id' => array( 'required' => true, 'type' => 'integer', 'minimum' => 1 ),
			'subject'  => array( 'required' => true, 'type' => 'string', 'minLength' => 1, 'maxLength' => 255 ),
			'message'  => array( 'required' => true, 'type' => 'string', 'minLength' => 1 ),
		);
	}
}
