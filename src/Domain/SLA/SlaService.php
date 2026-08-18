<?php
/**
 * @package WP Helpdesk
 */

namespace WPHelpdesk\Domain\SLA;

use WPHelpdesk\Infrastructure\Database\Schema;
use WPHelpdesk\Support\Constants;
use WPHelpdesk\Support\Helpers;

/**
 * Calculates SLA deadlines and runs scheduled breach checks.
 */
class SlaService {
	/**
	 * Calculate and persist SLA deadlines for a newly created ticket.
	 *
	 * Reads the network-wide first-response and resolution hour targets from
	 * network options and stamps the ticket row with deadline columns.
	 *
	 * @param array<string, mixed> $ticket Ticket data including 'id' and 'created_at'.
	 * @return void
	 */
	public function stampDeadlines( array $ticket ): void {
		global $wpdb;

		$first_response_hours = (int) get_site_option( Constants::OPTION_SLA_FIRST_RESPONSE, 4 );
		$resolution_hours     = (int) get_site_option( Constants::OPTION_SLA_RESOLUTION, 48 );

		$created_ts = strtotime( (string) ( $ticket['created_at'] ?? 'now' ) );
		if ( ! $created_ts ) {
			$created_ts = time();
		}

		$first_response_due = gmdate( 'Y-m-d H:i:s', $created_ts + $first_response_hours * HOUR_IN_SECONDS );
		$resolution_due     = gmdate( 'Y-m-d H:i:s', $created_ts + $resolution_hours * HOUR_IN_SECONDS );

		$table = Schema::table( Constants::TABLE_TICKETS );
		$wpdb->update(
			$table,
			array(
				'sla_first_response_due' => $first_response_due,
				'sla_resolution_due'     => $resolution_due,
			),
			array( 'id' => (int) $ticket['id'] ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Scheduled cron callback: find breached tickets and fire action hooks.
	 *
	 * Intended to run every 15 minutes via a WordPress cron event.
	 *
	 * @return void
	 */
	public function checkBreaches(): void {
		global $wpdb;

		$table      = Schema::table( Constants::TABLE_TICKETS );
		$network_id = Helpers::getNetworkId();
		$now        = current_time( 'mysql', true );

		// First-response breaches: open tickets past first-response deadline with no agent reply.
		$first_response_breached = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT t.id, t.ticket_no, t.sla_first_response_due
				 FROM {$table} t
				 WHERE t.network_id = %d
				   AND t.status NOT IN ('resolved','closed')
				   AND t.sla_first_response_due IS NOT NULL
				   AND t.sla_first_response_due < %s
				   AND t.sla_first_response_breached = 0",
				$network_id,
				$now
			),
			ARRAY_A
		);

		foreach ( $first_response_breached as $ticket ) {
			$wpdb->update(
				$table,
				array( 'sla_first_response_breached' => 1 ),
				array( 'id' => (int) $ticket['id'] ),
				array( '%d' ),
				array( '%d' )
			);

			/**
			 * Fires when a ticket breaches its first-response SLA deadline.
			 *
			 * @param array<string, mixed> $ticket Ticket summary row.
			 */
			do_action( 'hd_sla_first_response_breached', $ticket );
		}

		// Resolution breaches: open tickets past resolution deadline.
		$resolution_breached = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT t.id, t.ticket_no, t.sla_resolution_due
				 FROM {$table} t
				 WHERE t.network_id = %d
				   AND t.status NOT IN ('resolved','closed')
				   AND t.sla_resolution_due IS NOT NULL
				   AND t.sla_resolution_due < %s
				   AND t.sla_resolution_breached = 0",
				$network_id,
				$now
			),
			ARRAY_A
		);

		foreach ( $resolution_breached as $ticket ) {
			$wpdb->update(
				$table,
				array( 'sla_resolution_breached' => 1 ),
				array( 'id' => (int) $ticket['id'] ),
				array( '%d' ),
				array( '%d' )
			);

			/**
			 * Fires when a ticket breaches its resolution SLA deadline.
			 *
			 * @param array<string, mixed> $ticket Ticket summary row.
			 */
			do_action( 'hd_sla_resolution_breached', $ticket );
		}
	}

	/**
	 * Schedule the SLA breach-check cron event on plugin activation.
	 *
	 * @return void
	 */
	public static function scheduleCron(): void {
		if ( ! wp_next_scheduled( 'hd_sla_breach_check' ) ) {
			wp_schedule_event( time(), 'hd_every_15_minutes', 'hd_sla_breach_check' );
		}
	}

	/**
	 * Remove the SLA cron event on plugin deactivation.
	 *
	 * @return void
	 */
	public static function clearCron(): void {
		$timestamp = wp_next_scheduled( 'hd_sla_breach_check' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'hd_sla_breach_check' );
		}
	}
}
