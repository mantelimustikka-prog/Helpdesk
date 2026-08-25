<?php
/**
 * @package WP Helpdesk
 */

namespace WPHelpdesk\Interfaces\Rest;

use WP_REST_Request;
use WP_REST_Response;
use WPHelpdesk\Infrastructure\Database\Schema;
use WPHelpdesk\Support\Constants;

class NotificationController extends AdminApiController {
	/**
	 * Return new tickets and staff replies since a given Unix timestamp.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response
	 */
	public function since( WP_REST_Request $request ): WP_REST_Response {
		$since_raw = $request->get_param( 'since' );
		$since     = is_numeric( $since_raw ) && (int) $since_raw > 0 ? (int) $since_raw : 0;

		$new_tickets = array();
		$new_replies = array();

		if ( $since > 0 ) {
			$new_tickets = $this->fetchNewTickets( $since );
			$new_replies = $this->fetchNewReplies( $since );
		}

		return $this->success(
			array(
				'new_tickets' => $new_tickets,
				'new_replies' => $new_replies,
			)
		);
	}

	/**
	 * Fetch new tickets created after the given Unix timestamp.
	 *
	 * @param int $since Unix timestamp.
	 * @return array<int, array<string, mixed>>
	 */
	protected function fetchNewTickets( int $since ): array {
		global $wpdb;

		$table          = Schema::table( Constants::TABLE_TICKETS );
		$since_datetime = gmdate( 'Y-m-d H:i:s', $since );

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT id, ticket_no, subject, status, created_at
				 FROM {$table}
				 WHERE created_at > %s
				 ORDER BY created_at ASC
				 LIMIT 50",
				$since_datetime
			),
			ARRAY_A
		);

		if ( ! is_array( $rows ) ) {
			return array();
		}

		$result = array();
		foreach ( $rows as $row ) {
			$result[] = array(
				'id'         => (int) $row['id'],
				'ticket_no'  => (string) $row['ticket_no'],
				'subject'    => (string) $row['subject'],
				'status'     => (string) $row['status'],
				'created_at' => $this->toIso8601( (string) $row['created_at'] ),
			);
		}

		return $result;
	}

	/**
	 * Fetch new agent replies created after the given Unix timestamp.
	 * Only includes agent replies (author_type = 'agent').
	 *
	 * @param int $since Unix timestamp.
	 * @return array<int, array<string, mixed>>
	 */
	protected function fetchNewReplies( int $since ): array {
		global $wpdb;

		$tickets_table  = Schema::table( Constants::TABLE_TICKETS );
		$messages_table = Schema::table( Constants::TABLE_TICKET_MESSAGES );
		$since_datetime = gmdate( 'Y-m-d H:i:s', $since );

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT m.id, m.ticket_id, t.ticket_no, m.author_user_id, m.body, m.created_at
				 FROM {$messages_table} m
				 INNER JOIN {$tickets_table} t ON t.id = m.ticket_id
				 WHERE m.created_at > %s
				   AND m.author_type = 'agent'
				 ORDER BY m.created_at ASC
				 LIMIT 50",
				$since_datetime
			),
			ARRAY_A
		);

		if ( ! is_array( $rows ) ) {
			return array();
		}

		$result = array();
		foreach ( $rows as $row ) {
			$author  = $this->resolveAuthorName( (int) ( $row['author_user_id'] ?? 0 ) );
			$excerpt = mb_substr( wp_strip_all_tags( (string) $row['body'] ), 0, 100 );
			$result[] = array(
				'id'              => (int) $row['id'],
				'ticket_id'       => (int) $row['ticket_id'],
				'ticket_no'       => (string) $row['ticket_no'],
				'author'          => $author,
				'message_excerpt' => $excerpt,
				'created_at'      => $this->toIso8601( (string) $row['created_at'] ),
			);
		}

		return $result;
	}

	/**
	 * Resolve a WordPress user's display name.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return string Display name or fallback.
	 */
	protected function resolveAuthorName( int $user_id ): string {
		if ( $user_id > 0 ) {
			$user = get_userdata( $user_id );
			if ( $user ) {
				return $user->display_name;
			}
		}

		return __( 'Staff', 'wp-helpdesk' );
	}

	/**
	 * Convert a MySQL datetime string (UTC) to ISO 8601 format.
	 *
	 * @param string $mysql_datetime MySQL datetime string.
	 * @return string ISO 8601 datetime string.
	 */
	protected function toIso8601( string $mysql_datetime ): string {
		$ts = strtotime( $mysql_datetime );
		if ( false === $ts ) {
			return $mysql_datetime;
		}

		return gmdate( 'Y-m-d\TH:i:s\Z', $ts );
	}
}
