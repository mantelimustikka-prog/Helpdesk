<?php
/**
 * @package WP Helpdesk
 */

namespace WPHelpdesk\Domain\Ticket;

use WPHelpdesk\Support\Helpers;

class TicketService {

	protected TicketRepository $repository;
	protected int $network_id;

	public function __construct() {
		$this->repository = new TicketRepository();
		$this->network_id = Helpers::getNetworkId();
	}

	/**
	 * List tickets for the current network.
	 *
	 * @param array<string, mixed> $args Query args.
	 * @return array<int, array<string, mixed>>
	 */
	public function listTickets( array $args = [] ): array {
		return $this->repository->list( $this->network_id, $args );
	}

	/**
	 * Count tickets for the current network.
	 *
	 * @param array<string, mixed> $args Query args.
	 * @return int
	 */
	public function countTickets( array $args = [] ): int {
		return $this->repository->count( $this->network_id, $args );
	}

	/**
	 * Get a single ticket.
	 *
	 * @param int $id Ticket id.
	 * @return array<string, mixed>|null
	 */
	public function getTicket( int $id ): ?array {
		return $this->repository->find( $id, $this->network_id );
	}

	/**
	 * Create a new ticket.
	 *
	 * @param array<string, mixed> $data Ticket data.
	 * @return int Inserted id or 0 on failure.
	 */
	public function createTicket( array $data ): int {
		$subject = isset( $data['subject'] ) ? sanitize_text_field( trim( (string) $data['subject'] ) ) : '';
		if ( '' === $subject ) {
			return 0;
		}

		$email = isset( $data['requester_email'] ) ? sanitize_email( (string) $data['requester_email'] ) : '';
		if ( '' === $email || ! is_email( $email ) ) {
			return 0;
		}

		$ticket_no = $this->generateTicketNo();
		$now       = current_time( 'mysql' );

		return $this->repository->create(
			[
				'network_id'       => $this->network_id,
				'site_id'          => (int) ( $data['site_id'] ?? get_current_blog_id() ),
				'ticket_no'        => $ticket_no,
				'user_id'          => isset( $data['user_id'] ) ? (int) $data['user_id'] : null,
				'requester_name'   => sanitize_text_field( (string) ( $data['requester_name'] ?? '' ) ),
				'requester_email'  => $email,
				'requester_phone'  => sanitize_text_field( (string) ( $data['requester_phone'] ?? '' ) ),
				'subject'          => $subject,
				'topic_path_json'  => isset( $data['topic_path_json'] ) ? (string) $data['topic_path_json'] : null,
				'status'           => $this->sanitizeStatus( (string) ( $data['status'] ?? 'new' ) ),
				'priority'         => $this->sanitizePriority( (string) ( $data['priority'] ?? 'normal' ) ),
				'assigned_to'      => isset( $data['assigned_to'] ) ? (int) $data['assigned_to'] : null,
				'created_at'       => $now,
				'updated_at'       => $now,
			]
		);
	}

	/**
	 * Update a ticket.
	 *
	 * @param int                  $id   Ticket id.
	 * @param array<string, mixed> $data Ticket data.
	 * @return bool
	 */
	public function updateTicket( int $id, array $data ): bool {
		$existing = $this->repository->find( $id, $this->network_id );
		if ( ! $existing ) {
			return false;
		}

		$update = [ 'updated_at' => current_time( 'mysql' ) ];

		if ( isset( $data['subject'] ) ) {
			$subject = sanitize_text_field( trim( (string) $data['subject'] ) );
			if ( '' === $subject ) {
				return false;
			}

			$update['subject'] = $subject;
		}

		if ( isset( $data['status'] ) ) {
			$update['status'] = $this->sanitizeStatus( (string) $data['status'] );
		}

		if ( isset( $data['priority'] ) ) {
			$update['priority'] = $this->sanitizePriority( (string) $data['priority'] );
		}

		if ( array_key_exists( 'assigned_to', $data ) ) {
			$update['assigned_to'] = '' !== (string) $data['assigned_to'] ? (int) $data['assigned_to'] : null;
		}

		if ( isset( $data['topic_path_json'] ) ) {
			$update['topic_path_json'] = (string) $data['topic_path_json'];
		}

		return $this->repository->update( $id, $update, $this->network_id );
	}

	/**
	 * Delete a ticket.
	 *
	 * @param int $id Ticket id.
	 * @return bool
	 */
	public function deleteTicket( int $id ): bool {
		return $this->repository->delete( $id, $this->network_id );
	}

	/**
	 * Get status counts for reporting.
	 *
	 * @param array $date_range Optional ['from' => 'Y-m-d', 'to' => 'Y-m-d'].
	 * @return array<string, int>
	 */
	public function getStatusCounts( array $date_range = [] ): array {
		return $this->repository->countByStatus( $this->network_id, $date_range );
	}

	/**
	 * Get priority counts for reporting.
	 *
	 * @param array $date_range Optional ['from' => 'Y-m-d', 'to' => 'Y-m-d'].
	 * @return array<string, int>
	 */
	public function getPriorityCounts( array $date_range = [] ): array {
		return $this->repository->countByPriority( $this->network_id, $date_range );
	}

	/**
	 * Get daily ticket counts over a date range (trend reporting).
	 *
	 * @param string $from Start date Y-m-d.
	 * @param string $to   End date Y-m-d.
	 * @return array<string, int>
	 */
	public function getDailyMetrics( string $from, string $to ): array {
		return $this->repository->countByDay( $this->network_id, $from, $to );
	}

	/**
	 * Generate a unique ticket number.
	 *
	 * @return string
	 */
	protected function generateTicketNo(): string {
		$start   = (int) get_site_option( 'hd_general_ticket_number_start', 1000 );
		$counter = (int) get_site_option( 'hd_ticket_counter', $start );
		$next    = max( $start, $counter + 1 );
		update_site_option( 'hd_ticket_counter', $next );

		return 'HD-' . str_pad( (string) $next, 5, '0', STR_PAD_LEFT );
	}

	/**
	 * Sanitize a status value.
	 *
	 * @param string $status Raw status.
	 * @return string
	 */
	private function sanitizeStatus( string $status ): string {
		$allowed = [ 'new', 'triaged', 'waiting_customer', 'in_progress', 'resolved', 'closed' ];

		return in_array( $status, $allowed, true ) ? $status : 'new';
	}

	/**
	 * Sanitize a priority value.
	 *
	 * @param string $priority Raw priority.
	 * @return string
	 */
	private function sanitizePriority( string $priority ): string {
		$allowed = [ 'low', 'normal', 'high', 'urgent' ];

		return in_array( $priority, $allowed, true ) ? $priority : 'normal';
	}
}
