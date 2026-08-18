<?php
/**
 * @package WP Helpdesk
 */

namespace WPHelpdesk\Domain\Privacy;

use WPHelpdesk\Infrastructure\Database\Schema;
use WPHelpdesk\Support\Constants;

/**
 * Purges ticket data that has exceeded the configured retention period.
 *
 * The default retention period is 365 days and can be overridden via the
 * 'hd_data_retention_days' network option.  Purging is additive: it removes
 * ticket messages first, then the ticket itself, then any linked attachments
 * via the WP attachment API so that media library entries are cleaned up too.
 *
 * Intended to be called from a scheduled WP cron event or a WP-CLI command.
 */
class RetentionService {
	public const DEFAULT_RETENTION_DAYS = 365;

	/**
	 * Delete tickets (and related records) older than the retention period.
	 *
	 * @param int|null $retention_days Override; null reads the network option.
	 * @return int Number of ticket records deleted.
	 */
	public function purgeExpired( ?int $retention_days = null ): int {
		global $wpdb;

		$days = $retention_days ?? (int) get_site_option( 'hd_data_retention_days', self::DEFAULT_RETENTION_DAYS );
		if ( $days <= 0 ) {
			return 0;
		}

		$cutoff = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days", time() ) );

		$tickets_table  = Schema::table( Constants::TABLE_TICKETS );
		$messages_table = Schema::table( Constants::TABLE_TICKET_MESSAGES );
		$attachments_table = Schema::table( Constants::TABLE_ATTACHMENTS );

		// Collect IDs of expired tickets.
		$ticket_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT id FROM {$tickets_table} WHERE closed_at < %s AND status = 'closed' AND network_id = %d",
				$cutoff,
				get_current_network_id()
			)
		);

		if ( empty( $ticket_ids ) ) {
			return 0;
		}

		$ids_placeholder = implode( ',', array_map( 'intval', $ticket_ids ) );

		// Remove linked WP media attachments before deleting DB rows.
		$wp_attachment_ids = $wpdb->get_col(
			"SELECT wp_attachment_id FROM {$attachments_table} WHERE ticket_id IN ({$ids_placeholder})" // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);
		foreach ( $wp_attachment_ids as $wp_id ) {
			wp_delete_attachment( (int) $wp_id, true );
		}

		// Remove helpdesk attachment records.
		$wpdb->query( "DELETE FROM {$attachments_table} WHERE ticket_id IN ({$ids_placeholder})" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		// Remove ticket messages.
		$wpdb->query( "DELETE FROM {$messages_table} WHERE ticket_id IN ({$ids_placeholder})" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		// Remove ticket rows.
		$deleted = $wpdb->query( "DELETE FROM {$tickets_table} WHERE id IN ({$ids_placeholder})" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return (int) $deleted;
	}

	/**
	 * Schedule the retention cron event.
	 *
	 * @return void
	 */
	public static function scheduleCron(): void {
		if ( ! wp_next_scheduled( 'hd_retention_purge' ) ) {
			wp_schedule_event( time(), 'daily', 'hd_retention_purge' );
		}
	}

	/**
	 * Remove the retention cron event.
	 *
	 * @return void
	 */
	public static function clearCron(): void {
		$timestamp = wp_next_scheduled( 'hd_retention_purge' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'hd_retention_purge' );
		}
	}
}
