<?php
/**
 * @package WP Helpdesk
 */

namespace WPHelpdesk\Domain\Ticket;

use WPHelpdesk\Infrastructure\Database\Schema;
use WPHelpdesk\Support\Constants;
use WPHelpdesk\Support\Helpers;

class TicketLifecycleService {
	public const CRON_HOOK = 'hd_ticket_lifecycle_transition';

	/**
	 * Schedule lifecycle cron if missing.
	 *
	 * @return void
	 */
	public static function scheduleCron(): void {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time(), 'daily', self::CRON_HOOK );
		}
	}

	/**
	 * Unschedule lifecycle cron hook.
	 *
	 * @return void
	 */
	public static function unscheduleCron(): void {
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	/**
	 * Auto-transition stale statuses by configured day thresholds.
	 *
	 * @return void
	 */
	public function runAutoTransitions(): void {
		$auto_resolve_days = max( 1, (int) get_site_option( Constants::OPTION_GENERAL_AUTO_RESOLVE_DAYS, 7 ) );
		$auto_close_days   = max( 1, (int) get_site_option( Constants::OPTION_GENERAL_AUTO_CLOSE_DAYS, 7 ) );

		$this->transitionByAge(
			TicketStatus::CANONICAL_PENDING_CLIENT_REPLY,
			TicketStatus::CANONICAL_RESOLVED,
			$auto_resolve_days
		);
		$this->transitionByAge(
			TicketStatus::CANONICAL_RESOLVED,
			TicketStatus::CANONICAL_CLOSED,
			$auto_close_days
		);
	}

	/**
	 * Sync ticket status after a cross-party reply.
	 *
	 * @param array<string, mixed> $ticket Ticket data.
	 * @param array<string, mixed> $message Reply message.
	 * @return void
	 */
	public function syncStatusAfterReply( array $ticket, array $message ): void {
		$ticket_id = isset( $ticket['id'] ) ? (int) $ticket['id'] : 0;
		if ( $ticket_id <= 0 ) {
			return;
		}

		$current_status = TicketStatus::toCanonical( (string) ( $ticket['status'] ?? '' ) );
		if ( TicketStatus::CANONICAL_CLOSED === $current_status ) {
			return;
		}

		$reply_author = sanitize_key( (string) ( $message['author_type'] ?? '' ) );
		if ( '' === $reply_author ) {
			return;
		}

		$opener_author = $this->resolveOpenerAuthorType( $ticket_id, $ticket );
		if ( '' === $opener_author ) {
			return;
		}

		$reply_is_client  = $this->isClientAuthorType( $reply_author );
		$opener_is_client = $this->isClientAuthorType( $opener_author );
		if ( $reply_is_client === $opener_is_client ) {
			return;
		}

		$target_status = $reply_is_client
			? TicketStatus::CANONICAL_PENDING_AGENT_REPLY
			: TicketStatus::CANONICAL_PENDING_CLIENT_REPLY;
		if ( $current_status === $target_status ) {
			return;
		}

		$this->updateStatus( $ticket_id, $target_status );
	}

	/**
	 * @param int                  $ticket_id Ticket id.
	 * @param array<string, mixed> $ticket Ticket data.
	 * @return string
	 */
	protected function resolveOpenerAuthorType( int $ticket_id, array $ticket ): string {
		global $wpdb;
		$table  = Schema::table( Constants::TABLE_TICKET_MESSAGES );
		$author = (string) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT author_type FROM {$table} WHERE ticket_id = %d ORDER BY id ASC LIMIT 1",
				$ticket_id
			)
		);
		if ( '' !== $author ) {
			return sanitize_key( $author );
		}

		return ! empty( $ticket['user_id'] ) ? 'member' : 'guest';
	}

	/**
	 * @param string $author_type Author type.
	 * @return bool
	 */
	protected function isClientAuthorType( string $author_type ): bool {
		return in_array( $author_type, array( 'guest', 'member' ), true );
	}

	/**
	 * @param string $from_status Canonical from status.
	 * @param string $to_status   Canonical to status.
	 * @param int    $days        Age threshold in days.
	 * @return void
	 */
	protected function transitionByAge( string $from_status, string $to_status, int $days ): void {
		global $wpdb;
		$table      = Schema::table( Constants::TABLE_TICKETS );
		$network_id = Helpers::getNetworkId();
		$cutoff     = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );

		$from_storage = TicketStatus::storageValuesForCanonical( $from_status );
		$placeholders = implode( ',', array_fill( 0, count( $from_storage ), '%s' ) );
		$query        = "SELECT * FROM {$table} WHERE network_id = %d AND status IN ({$placeholders}) AND updated_at <= %s";
		$args         = array_merge( array( $network_id ), $from_storage, array( $cutoff ) );
		$tickets      = $wpdb->get_results( $wpdb->prepare( $query, ...$args ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		foreach ( $tickets ?: array() as $ticket ) {
			$ticket_id = (int) ( $ticket['id'] ?? 0 );
			if ( $ticket_id <= 0 ) {
				continue;
			}

			$updated = $this->updateStatus( $ticket_id, $to_status );
			if ( ! $updated ) {
				continue;
			}

			do_action(
				'hd_ticket_status_changed',
				$this->findTicket( $ticket_id ) ?: $ticket,
				TicketStatus::toCanonical( (string) ( $ticket['status'] ?? '' ) ),
				TicketStatus::toCanonical( $to_status )
			);
		}
	}

	/**
	 * @param int    $ticket_id Ticket id.
	 * @param string $status    Canonical status.
	 * @return bool
	 */
	protected function updateStatus( int $ticket_id, string $status ): bool {
		global $wpdb;
		$table = Schema::table( Constants::TABLE_TICKETS );
		$data  = array(
			'status'     => TicketStatus::toStorage( $status ),
			'updated_at' => current_time( 'mysql' ),
			'closed_at'  => TicketStatus::CANONICAL_CLOSED === TicketStatus::toCanonical( $status ) ? current_time( 'mysql' ) : null,
		);

		$result = $wpdb->update(
			$table,
			$data,
			array(
				'id'         => $ticket_id,
				'network_id' => Helpers::getNetworkId(),
			),
			array( '%s', '%s', '%s' ),
			array( '%d', '%d' )
		);

		return false !== $result;
	}

	/**
	 * @param int $ticket_id Ticket id.
	 * @return array<string, mixed>|null
	 */
	protected function findTicket( int $ticket_id ): ?array {
		global $wpdb;
		$table = Schema::table( Constants::TABLE_TICKETS );
		$row   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d AND network_id = %d LIMIT 1",
				$ticket_id,
				Helpers::getNetworkId()
			),
			ARRAY_A
		);

		return $row ?: null;
	}
}
