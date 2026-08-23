<?php
/**
 * @package WP Helpdesk
 */

namespace WPHelpdesk\Interfaces\Rest;

use WP_REST_Request;
use WP_REST_Response;
use WPHelpdesk\Domain\Ticket\TicketStatus;
use WPHelpdesk\Infrastructure\Database\Schema;
use WPHelpdesk\Support\Constants;
use WPHelpdesk\Support\Helpers;

class AdminTicketController {
	/**
	 * List tickets for the current network.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function listTickets( WP_REST_Request $request ): WP_REST_Response {
		global $wpdb;

		$table      = Schema::table( Constants::TABLE_TICKETS );
		$network_id = Helpers::getNetworkId();
		$page       = max( 1, (int) $request->get_param( 'page' ) );
		$per_page   = max( 1, min( 100, (int) $request->get_param( 'per_page' ) ?: 20 ) );
		$offset     = ( $page - 1 ) * $per_page;
		$status = TicketStatus::tryCanonical( sanitize_key( (string) $request->get_param( 'status' ) ) );

		if ( null !== $status ) {
			$statuses      = TicketStatus::storageValuesForCanonical( $status );
			$placeholders  = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
			$query         = "SELECT * FROM {$table} WHERE network_id = %d AND status IN ({$placeholders}) ORDER BY created_at DESC LIMIT %d OFFSET %d";
			$prepare_args  = array_merge( array( $network_id ), $statuses, array( $per_page, $offset ) );
			$sql           = $wpdb->prepare( $query, ...$prepare_args ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		} else {
			$sql = $wpdb->prepare(
				"SELECT * FROM {$table} WHERE network_id = %d ORDER BY created_at DESC LIMIT %d OFFSET %d",
				$network_id,
				$per_page,
				$offset
			);
		}

		$tickets = array_map( array( $this, 'normalizeTicketForResponse' ), $wpdb->get_results( $sql, ARRAY_A ) ?: array() );

		return new WP_REST_Response(
			array(
				'items'    => $tickets,
				'page'     => $page,
				'per_page' => $per_page,
			)
		);
	}

	/**
	 * Get a single ticket.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function getTicket( WP_REST_Request $request ): WP_REST_Response {
		$ticket = $this->findTicket( (int) $request['id'] );

		if ( empty( $ticket ) ) {
			return new WP_REST_Response( array( 'message' => 'Ticket not found.' ), 404 );
		}

		$messages = array_map(
			array( $this, 'normalizeMessageForResponse' ),
			$this->fetchMessagesForTicket( (int) $ticket['id'] )
		);

		$data             = $this->normalizeTicketForResponse( $ticket );
		$data['messages'] = $messages;

		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => $data,
			)
		);
	}

	/**
	 * Get ticket messages.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function getMessages( WP_REST_Request $request ): WP_REST_Response {
		$ticket = $this->findTicket( (int) $request['id'] );
		if ( empty( $ticket ) ) {
			return new WP_REST_Response( array( 'message' => 'Ticket not found.' ), 404 );
		}

		$messages = array_map(
			array( $this, 'normalizeMessageForResponse' ),
			$this->fetchMessagesForTicket( (int) $ticket['id'] )
		);

		return new WP_REST_Response( array( 'items' => $messages ) );
	}

	/**
	 * Add a reply to a ticket.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function reply( WP_REST_Request $request ): WP_REST_Response {
		global $wpdb;

		$ticket = $this->findTicket( (int) $request['id'] );
		if ( empty( $ticket ) ) {
			return new WP_REST_Response( array( 'message' => 'Ticket not found.' ), 404 );
		}

		$body = wp_kses_post( (string) $request->get_param( 'body' ) );
		if ( '' === trim( wp_strip_all_tags( $body ) ) ) {
			return new WP_REST_Response( array( 'message' => 'Reply body is required.' ), 400 );
		}

		$table = Schema::table( Constants::TABLE_TICKET_MESSAGES );
		$wpdb->insert(
			$table,
			array(
				'ticket_id'      => (int) $ticket['id'],
				'author_user_id' => get_current_user_id(),
				'author_type'    => 'agent',
				'body'           => $body,
				'is_internal'    => (int) $request->get_param( 'is_internal' ),
				'created_at'     => current_time( 'mysql', true ),
			),
			array( '%d', '%d', '%s', '%s', '%d', '%s' )
		);

		$message = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d LIMIT 1",
				(int) $wpdb->insert_id
			),
			ARRAY_A
		);

		/**
		 * Fires when a helpdesk reply is created.
		 */
		do_action( 'hd_ticket_replied', $ticket, $message );

		return new WP_REST_Response( $message, 201 );
	}

	/**
	 * Update a ticket status.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function updateStatus( WP_REST_Request $request ): WP_REST_Response {
		global $wpdb;

		$ticket = $this->findTicket( (int) $request['id'] );
		if ( empty( $ticket ) ) {
			return new WP_REST_Response( array( 'message' => 'Ticket not found.' ), 404 );
		}

		$new_status = TicketStatus::tryCanonical( sanitize_key( (string) $request->get_param( 'status' ) ) );
		if ( null === $new_status ) {
			return new WP_REST_Response( array( 'message' => 'Invalid status value.' ), 400 );
		}

		$table       = Schema::table( Constants::TABLE_TICKETS );
		$old_status  = TicketStatus::toCanonical( (string) $ticket['status'] );
		$storage_new = TicketStatus::toStorage( $new_status );
		$closed_at   = TicketStatus::CANONICAL_CLOSED === $new_status ? current_time( 'mysql', true ) : null;
		$update_data = array(
			'status'     => $storage_new,
			'updated_at' => current_time( 'mysql', true ),
			'closed_at'  => $closed_at,
		);

		$wpdb->update(
			$table,
			$update_data,
			array( 'id' => (int) $ticket['id'] ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);

		$updated = $this->findTicket( (int) $ticket['id'] );

		/**
		 * Fires when a helpdesk ticket status changes.
		 */
		do_action( 'hd_ticket_status_changed', $this->normalizeTicketForResponse( $updated ?: $ticket ), $old_status, $new_status );

		return new WP_REST_Response( $this->normalizeTicketForResponse( $updated ?: $ticket ) );
	}

	/**
	 * Create a new ticket.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function createTicket( WP_REST_Request $request ): WP_REST_Response {
		global $wpdb;

		$subject = sanitize_text_field( (string) $request->get_param( 'subject' ) );
		$body    = wp_kses_post( (string) $request->get_param( 'body' ) );

		if ( '' === trim( $subject ) ) {
			return new WP_REST_Response( array( 'message' => 'Subject is required.' ), 400 );
		}

		if ( '' === trim( wp_strip_all_tags( $body ) ) ) {
			return new WP_REST_Response( array( 'message' => 'Body is required.' ), 400 );
		}

		$table      = Schema::table( Constants::TABLE_TICKETS );
		$network_id = Helpers::getNetworkId();
		$now        = current_time( 'mysql', true );

		$wpdb->insert(
			$table,
			array(
				'network_id'  => $network_id,
				'subject'     => $subject,
				'body'        => $body,
				'status'      => TicketStatus::toStorage( TicketStatus::CANONICAL_OPEN ),
				'author_id'   => null !== $request->get_param( 'author_id' ) ? (int) $request->get_param( 'author_id' ) : get_current_user_id(),
				'assigned_to' => null !== $request->get_param( 'assigned_to' ) ? (int) $request->get_param( 'assigned_to' ) : null,
				'created_at'  => $now,
				'updated_at'  => $now,
			),
			array( '%d', '%s', '%s', '%s', '%d', '%d', '%s', '%s' )
		);

		$ticket = $this->findTicket( (int) $wpdb->insert_id );

		if ( empty( $ticket ) ) {
			return new WP_REST_Response( array( 'message' => 'Failed to create ticket.' ), 500 );
		}

		/**
		 * Fires when an admin creates a helpdesk ticket.
		 */
		do_action( 'hd_ticket_created', $ticket );

		return new WP_REST_Response( $this->normalizeTicketForResponse( $ticket ), 201 );
	}

	/**
	 * Add an internal note to a ticket.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function addNote( WP_REST_Request $request ): WP_REST_Response {
		global $wpdb;

		$ticket = $this->findTicket( (int) $request['id'] );
		if ( empty( $ticket ) ) {
			return new WP_REST_Response( array( 'message' => 'Ticket not found.' ), 404 );
		}

		$body = wp_kses_post( (string) $request->get_param( 'body' ) );
		if ( '' === trim( wp_strip_all_tags( $body ) ) ) {
			return new WP_REST_Response( array( 'message' => 'Note body is required.' ), 400 );
		}

		$table = Schema::table( Constants::TABLE_TICKET_MESSAGES );
		$wpdb->insert(
			$table,
			array(
				'ticket_id'      => (int) $ticket['id'],
				'author_user_id' => get_current_user_id(),
				'author_type'    => 'agent',
				'body'           => $body,
				'is_internal'    => 1,
				'created_at'     => current_time( 'mysql', true ),
			),
			array( '%d', '%d', '%s', '%s', '%d', '%s' )
		);

		$note = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d LIMIT 1",
				(int) $wpdb->insert_id
			),
			ARRAY_A
		);

		/**
		 * Fires when an internal note is added to a helpdesk ticket.
		 */
		do_action( 'hd_ticket_note_added', $ticket, $note );

		return new WP_REST_Response( $note, 201 );
	}

	/**
	 * Assign a ticket to a user.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function assignTicket( WP_REST_Request $request ): WP_REST_Response {
		global $wpdb;

		$ticket = $this->findTicket( (int) $request['id'] );
		if ( empty( $ticket ) ) {
			return new WP_REST_Response( array( 'message' => 'Ticket not found.' ), 404 );
		}

		$assigned_to = (int) $request->get_param( 'assigned_to' );

		$table = Schema::table( Constants::TABLE_TICKETS );
		$wpdb->update(
			$table,
			array(
				'assigned_to' => $assigned_to,
				'updated_at'  => current_time( 'mysql', true ),
			),
			array( 'id' => (int) $ticket['id'] ),
			array( '%d', '%s' ),
			array( '%d' )
		);

		$updated = $this->findTicket( (int) $ticket['id'] );

		/**
		 * Fires when a helpdesk ticket is assigned.
		 */
		do_action( 'hd_ticket_assigned', $updated, $assigned_to );

		return new WP_REST_Response( $updated );
	}

	/**
	 * Fetch a single ticket constrained to the current network.
	 *
	 * @param int $ticket_id Ticket ID.
	 * @return array<string, mixed>|null
	 */
	protected function findTicket( int $ticket_id ): ?array {
		global $wpdb;

		$table      = Schema::table( Constants::TABLE_TICKETS );
		$network_id = Helpers::getNetworkId();
		$ticket     = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d AND network_id = %d LIMIT 1",
				$ticket_id,
				$network_id
			),
			ARRAY_A
		);

		return $ticket ?: null;
	}

	/**
	 * Fetch raw message rows for a ticket, ordered chronologically.
	 *
	 * Extracted as a protected method so sub-classes (and unit tests) can
	 * override the database query without touching global state.
	 *
	 * @param int $ticket_id Ticket ID.
	 * @return array<int, array<string, mixed>>
	 */
	protected function fetchMessagesForTicket( int $ticket_id ): array {
		global $wpdb;

		$table = Schema::table( Constants::TABLE_TICKET_MESSAGES );

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE ticket_id = %d ORDER BY created_at ASC",
				$ticket_id
			),
			ARRAY_A
		) ?: array();
	}

	/**
	 * Normalize a single message row for REST responses.
	 *
	 * Adds an `author_name` field resolved from the WordPress user display name
	 * so the Android client can show a human-readable sender label.
	 * Integer DB columns are explicitly cast so json_encode emits JSON numbers
	 * rather than strings, keeping Gson deserialization straightforward.
	 *
	 * @param array<string, mixed> $message Raw message DB row.
	 * @return array<string, mixed>
	 */
	protected function normalizeMessageForResponse( array $message ): array {
		$message['id']             = (int) ( $message['id'] ?? 0 );
		$message['ticket_id']      = (int) ( $message['ticket_id'] ?? 0 );
		$message['author_user_id'] = isset( $message['author_user_id'] ) ? (int) $message['author_user_id'] : null;
		$message['is_internal']    = (int) ( $message['is_internal'] ?? 0 );
		$message['author_name']    = $message['author_name'] ?? null;
		$user_id = (int) ( $message['author_user_id'] ?? 0 );
		if ( $user_id > 0 ) {
			$user = get_userdata( $user_id );
			if ( $user ) {
				$message['author_name'] = $user->display_name;
			}
		}
		return $message;
	}

	/**
	 * Normalize ticket status in REST payloads and cast numeric fields.
	 *
	 * @param array<string, mixed>|null $ticket Ticket row.
	 * @return array<string, mixed>
	 */
	protected function normalizeTicketForResponse( ?array $ticket ): array {
		if ( empty( $ticket ) ) {
			return array();
		}
		$ticket['id']     = (int) ( $ticket['id'] ?? 0 );
		$ticket['status'] = TicketStatus::toCanonical( (string) ( $ticket['status'] ?? '' ) );
		return $ticket;
	}
}
