<?php
/**
 * @package WP Helpdesk
 */

namespace WPHelpdesk\Domain\Privacy;

use WP_User;
use WPHelpdesk\Infrastructure\Database\Schema;
use WPHelpdesk\Support\Constants;

/**
 * Integrates with WordPress's built-in Personal Data tools.
 *
 * Hooks into the WP privacy exporters/erasers API so that helpdesk ticket
 * data is included in user data export requests and erased when a user
 * data-erasure request is processed.
 *
 * @see https://developer.wordpress.org/plugins/privacy/
 */
class GdprHandler {
	/**
	 * Register exporters and erasers with WP privacy framework.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'registerExporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'registerEraser' ) );
	}

	/**
	 * Add helpdesk exporter to the privacy exporter list.
	 *
	 * @param array<int, array<string, mixed>> $exporters Existing exporters.
	 * @return array<int, array<string, mixed>>
	 */
	public function registerExporter( array $exporters ): array {
		$exporters[] = array(
			'exporter_friendly_name' => __( 'WP Helpdesk Tickets', 'wp-helpdesk' ),
			'callback'               => array( $this, 'exportUserData' ),
		);

		return $exporters;
	}

	/**
	 * Add helpdesk eraser to the privacy eraser list.
	 *
	 * @param array<int, array<string, mixed>> $erasers Existing erasers.
	 * @return array<int, array<string, mixed>>
	 */
	public function registerEraser( array $erasers ): array {
		$erasers[] = array(
			'eraser_friendly_name' => __( 'WP Helpdesk Tickets', 'wp-helpdesk' ),
			'callback'             => array( $this, 'eraseUserData' ),
		);

		return $erasers;
	}

	/**
	 * Export helpdesk tickets for the given email address.
	 *
	 * @param string $email_address User email.
	 * @param int    $page          Page number (1-based, 100 items per page).
	 * @return array<string, mixed>
	 */
	public function exportUserData( string $email_address, int $page = 1 ): array {
		global $wpdb;

		$tickets_table  = Schema::table( Constants::TABLE_TICKETS );
		$messages_table = Schema::table( Constants::TABLE_TICKET_MESSAGES );
		$per_page       = 100;
		$offset         = ( $page - 1 ) * $per_page;

		$tickets = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$tickets_table} WHERE requester_email = %s LIMIT %d OFFSET %d",
				$email_address,
				$per_page,
				$offset
			),
			ARRAY_A
		);

		$data_groups = array();
		foreach ( $tickets as $ticket ) {
			$items = array(
				array(
					'name'  => __( 'Ticket Number', 'wp-helpdesk' ),
					'value' => (string) $ticket['ticket_no'],
				),
				array(
					'name'  => __( 'Subject', 'wp-helpdesk' ),
					'value' => (string) $ticket['subject'],
				),
				array(
					'name'  => __( 'Requester Name', 'wp-helpdesk' ),
					'value' => (string) $ticket['requester_name'],
				),
				array(
					'name'  => __( 'Requester Email', 'wp-helpdesk' ),
					'value' => (string) $ticket['requester_email'],
				),
				array(
					'name'  => __( 'Status', 'wp-helpdesk' ),
					'value' => (string) $ticket['status'],
				),
				array(
					'name'  => __( 'Created', 'wp-helpdesk' ),
					'value' => (string) $ticket['created_at'],
				),
			);

			$messages = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT body, created_at FROM {$messages_table} WHERE ticket_id = %d ORDER BY created_at ASC",
					(int) $ticket['id']
				),
				ARRAY_A
			);

			foreach ( $messages as $i => $message ) {
				$items[] = array(
					'name'  => sprintf( __( 'Message %d', 'wp-helpdesk' ), $i + 1 ),
					'value' => wp_strip_all_tags( (string) $message['body'] ) . ' (' . (string) $message['created_at'] . ')',
				);
			}

			$data_groups[] = array(
				'group_id'    => 'helpdesk-ticket-' . (int) $ticket['id'],
				'group_label' => sprintf( __( 'Helpdesk Ticket: %s', 'wp-helpdesk' ), (string) $ticket['ticket_no'] ),
				'item_id'     => 'ticket-' . (int) $ticket['id'],
				'data'        => $items,
			);
		}

		return array(
			'data' => $data_groups,
			'done' => count( $tickets ) < $per_page,
		);
	}

	/**
	 * Erase helpdesk ticket data for the given email address.
	 *
	 * Anonymises PII on ticket rows and removes message bodies rather than
	 * hard-deleting records so that ticket-number sequences stay intact.
	 *
	 * @param string $email_address User email.
	 * @param int    $page          Page number (1-based).
	 * @return array<string, mixed>
	 */
	public function eraseUserData( string $email_address, int $page = 1 ): array {
		global $wpdb;

		$tickets_table  = Schema::table( Constants::TABLE_TICKETS );
		$messages_table = Schema::table( Constants::TABLE_TICKET_MESSAGES );
		$per_page       = 100;
		$offset         = ( $page - 1 ) * $per_page;

		$ticket_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT id FROM {$tickets_table} WHERE requester_email = %s LIMIT %d OFFSET %d",
				$email_address,
				$per_page,
				$offset
			)
		);

		$items_removed = 0;

		foreach ( $ticket_ids as $ticket_id ) {
			// Anonymise the ticket row.
			$wpdb->update(
				$tickets_table,
				array(
					'requester_name'  => __( '[removed]', 'wp-helpdesk' ),
					'requester_email' => '',
					'requester_phone' => '',
					'subject'         => __( '[removed]', 'wp-helpdesk' ),
				),
				array( 'id' => (int) $ticket_id ),
				array( '%s', '%s', '%s', '%s' ),
				array( '%d' )
			);

			// Blank out message bodies.
			$wpdb->update(
				$messages_table,
				array( 'body' => __( '[removed]', 'wp-helpdesk' ) ),
				array( 'ticket_id' => (int) $ticket_id ),
				array( '%s' ),
				array( '%d' )
			);

			++$items_removed;
		}

		return array(
			'items_removed'  => $items_removed,
			'items_retained' => 0,
			'messages'       => array(),
			'done'           => count( $ticket_ids ) < $per_page,
		);
	}
}
