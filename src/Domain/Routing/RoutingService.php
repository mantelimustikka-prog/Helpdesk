<?php
/**
 * @package WP Helpdesk
 */

namespace WPHelpdesk\Domain\Routing;

use WPHelpdesk\Infrastructure\Database\Schema;
use WPHelpdesk\Support\Constants;
use WPHelpdesk\Support\Helpers;

/**
 * Applies configurable topic->queue/email/priority routing rules to new tickets.
 */
class RoutingService {
	/**
	 * Match a ticket against stored routing rules and return merged overrides.
	 * Rules are evaluated in ascending sort_order; the first active match wins.
	 *
	 * Returned keys (all optional / may be empty string):
	 *   - priority         : e.g. 'high', 'normal', 'low'
	 *   - assigned_queue   : e.g. 'billing', 'technical'
	 *   - notification_email : extra address to CC on new-ticket emails
	 *
	 * @param array<string, mixed> $ticket Ticket data including topic_id.
	 * @return array<string, string>
	 */
	public function resolveForTicket( array $ticket ): array {
		$rules = $this->loadRules( (int) ( $ticket['topic_id'] ?? 0 ) );

		foreach ( $rules as $rule ) {
			if ( $this->matches( $rule, $ticket ) ) {
				return array(
					'priority'           => (string) $rule['priority'],
					'assigned_queue'     => (string) $rule['assigned_queue'],
					'notification_email' => (string) $rule['notification_email'],
				);
			}
		}

		return array(
			'priority'           => 'normal',
			'assigned_queue'     => '',
			'notification_email' => '',
		);
	}

	/**
	 * Fetch active rules for the current network, optionally filtered by topic.
	 *
	 * @param int $topic_id Topic ID (0 = wildcard / no filter).
	 * @return array<int, array<string, mixed>>
	 */
	private function loadRules( int $topic_id ): array {
		global $wpdb;

		$table      = Schema::table( Constants::TABLE_ROUTING_RULES );
		$network_id = Helpers::getNetworkId();

		if ( $topic_id > 0 ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table}
					 WHERE network_id = %d AND is_active = 1
					   AND (topic_id = %d OR topic_id IS NULL)
					 ORDER BY (topic_id IS NULL) ASC, sort_order ASC",
					$network_id,
					$topic_id
				),
				ARRAY_A
			);
		} else {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table}
					 WHERE network_id = %d AND is_active = 1
					 ORDER BY sort_order ASC",
					$network_id
				),
				ARRAY_A
			);
		}

		return $rows ?: array();
	}

	/**
	 * Decide whether a rule matches the given ticket.
	 * Currently matches on topic_id (NULL = wildcard).
	 *
	 * @param array<string, mixed> $rule   Routing rule row.
	 * @param array<string, mixed> $ticket Ticket data.
	 * @return bool
	 */
	private function matches( array $rule, array $ticket ): bool {
		if ( null !== $rule['topic_id'] && (int) $rule['topic_id'] !== (int) ( $ticket['topic_id'] ?? 0 ) ) {
			return false;
		}

		return true;
	}
}
