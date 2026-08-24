<?php
/**
 * @package WP Helpdesk
 */

namespace WPHelpdesk\Domain\Push;

use WPHelpdesk\Infrastructure\Database\Schema;
use WPHelpdesk\Support\Constants;
use WPHelpdesk\Support\HelpdeskLogger;

class PushService {
	protected PushProviderInterface $provider;

	public function __construct( PushProviderInterface $provider ) {
		$this->provider = $provider;
	}

	/**
	 * Notify active admins of a new ticket.
	 *
	 * @param array<string, mixed> $ticket Ticket data.
	 * @return void
	 */
	public function notifyNewTicket( array $ticket ): void {
		$ticket_id = (int) ( $ticket['id'] ?? 0 );
		HelpdeskLogger::log( 'push.notify_new_ticket', array( 'ticket_id' => $ticket_id ) );

		if ( ! $this->shouldSendEvent( 'ticket_created' ) ) {
			return;
		}

		$tokens = $this->getAdminTokens();
		if ( empty( $tokens ) ) {
			HelpdeskLogger::log( 'push.no_tokens', array( 'event' => 'ticket_created', 'ticket_id' => $ticket_id ) );
			return;
		}

		$result = $this->provider->send(
			$tokens,
			'New ticket created',
			(string) ( $ticket['subject'] ?? '' ),
			array(
				'event_type'      => 'ticket_created',
				'ticket_id'       => $ticket_id,
				'deep_link'       => sprintf( 'wphelpd://ticket/%d', $ticket_id ),
				'notification_id' => sprintf( 'ticket_created:%d', $ticket_id ),
			)
		);
		HelpdeskLogger::log( 'push.send_result', array( 'event' => 'ticket_created', 'ticket_id' => $ticket_id, 'success' => $result ) );
	}

	/**
	 * Notify active admins of a new reply.
	 *
	 * @param array<string, mixed> $ticket  Ticket data.
	 * @param array<string, mixed> $message Message data.
	 * @return void
	 */
	public function notifyNewReply( array $ticket, array $message ): void {
		$ticket_id  = (int) ( $ticket['id'] ?? 0 );
		$message_id = (int) ( $message['id'] ?? 0 );
		HelpdeskLogger::log( 'push.notify_new_reply', array( 'ticket_id' => $ticket_id, 'message_id' => $message_id ) );

		if ( ! $this->shouldSendEvent( 'ticket_replied' ) ) {
			return;
		}

		$tokens = $this->getAdminTokens();
		if ( empty( $tokens ) ) {
			HelpdeskLogger::log( 'push.no_tokens', array( 'event' => 'ticket_replied', 'ticket_id' => $ticket_id ) );
			return;
		}

		$result = $this->provider->send(
			$tokens,
			'New ticket reply',
			wp_trim_words( wp_strip_all_tags( (string) ( $message['body'] ?? '' ) ), 20 ),
			array(
				'event_type'      => 'ticket_replied',
				'ticket_id'       => $ticket_id,
				'deep_link'       => sprintf( 'wphelpd://ticket/%d', $ticket_id ),
				'notification_id' => sprintf( 'ticket_replied:%d:%d', $ticket_id, $message_id ),
			)
		);
		HelpdeskLogger::log( 'push.send_result', array( 'event' => 'ticket_replied', 'ticket_id' => $ticket_id, 'success' => $result ) );
	}

	/**
	 * Notify active admins of a status update.
	 *
	 * @param array<string, mixed> $ticket     Ticket data.
	 * @param string               $new_status Updated status.
	 * @return void
	 */
	public function notifyStatusChanged( array $ticket, string $new_status ): void {
		$ticket_id = (int) ( $ticket['id'] ?? 0 );
		HelpdeskLogger::log( 'push.notify_status_changed', array( 'ticket_id' => $ticket_id, 'new_status' => $new_status ) );

		if ( ! $this->shouldSendEvent( 'status_changed' ) ) {
			return;
		}

		$tokens = $this->getAdminTokens();
		if ( empty( $tokens ) ) {
			HelpdeskLogger::log( 'push.no_tokens', array( 'event' => 'status_changed', 'ticket_id' => $ticket_id ) );
			return;
		}

		$result = $this->provider->send(
			$tokens,
			'Ticket status changed',
			sprintf( 'Ticket %s is now %s.', (string) ( $ticket['ticket_no'] ?? '' ), $new_status ),
			array(
				'event_type'      => 'status_changed',
				'ticket_id'       => $ticket_id,
				'deep_link'       => sprintf( 'wphelpd://ticket/%d', $ticket_id ),
				'notification_id' => sprintf( 'status_changed:%d:%s', $ticket_id, sanitize_key( $new_status ) ),
			)
		);
		HelpdeskLogger::log( 'push.send_result', array( 'event' => 'status_changed', 'ticket_id' => $ticket_id, 'success' => $result ) );
	}

	/**
	 * Notify an assigned agent.
	 *
	 * @param array<string, mixed> $ticket      Ticket data.
	 * @param int                  $assigned_to Assignee user ID.
	 * @return void
	 */
	public function notifyAssigned( array $ticket, int $assigned_to ): void {
		$ticket_id = (int) ( $ticket['id'] ?? 0 );
		HelpdeskLogger::log( 'push.notify_assigned', array( 'ticket_id' => $ticket_id, 'assigned_to' => $assigned_to ) );

		if ( ! $this->isPushEnabled() || ! $this->hasValidConfiguration() ) {
			return;
		}

		$tokens = $this->getUserTokens( $assigned_to );
		if ( empty( $tokens ) ) {
			HelpdeskLogger::log( 'push.no_tokens', array( 'event' => 'ticket_assigned', 'ticket_id' => $ticket_id, 'assigned_to' => $assigned_to ) );
			return;
		}

		$result = $this->provider->send(
			$tokens,
			'Ticket assigned',
			sprintf( 'Ticket %s has been assigned to you.', (string) ( $ticket['ticket_no'] ?? '' ) ),
			array(
				'event_type'      => 'ticket_assigned',
				'ticket_id'       => $ticket_id,
				'deep_link'       => sprintf( 'wphelpd://ticket/%d', $ticket_id ),
				'notification_id' => sprintf( 'ticket_assigned:%d:%d', $ticket_id, $assigned_to ),
			)
		);
		HelpdeskLogger::log( 'push.send_result', array( 'event' => 'ticket_assigned', 'ticket_id' => $ticket_id, 'success' => $result ) );
	}

	/**
	 * Fetch all active admin device tokens.
	 *
	 * @return array<int, string>
	 */
	protected function getAdminTokens(): array {
		$user_ids = get_users(
			array(
				'fields'         => 'ID',
				'capability__in' => array( 'hd_manage_tickets', 'hd_reply_tickets' ),
			)
		);

		$tokens = array();
		foreach ( $user_ids as $user_id ) {
			$tokens = array_merge( $tokens, $this->getUserTokens( (int) $user_id ) );
		}

		$tokens = array_values( array_unique( $tokens ) );
		HelpdeskLogger::log( 'push.admin_tokens', array( 'count' => count( $tokens ) ) );

		return $tokens;
	}

	/**
	 * Fetch active device tokens for a user.
	 *
	 * @param int $user_id User ID.
	 * @return array<int, string>
	 */
	protected function getUserTokens( int $user_id ): array {
		global $wpdb;

		$table = Schema::table( Constants::TABLE_DEVICE_TOKENS );

		return array_map(
			'strval',
			(array) $wpdb->get_col(
				$wpdb->prepare(
					"SELECT device_token FROM {$table} WHERE user_id = %d AND is_active = 1",
					$user_id
				)
			)
		);
	}

	/**
	 * Check whether a push event should be sent.
	 *
	 * @param string $event Event key.
	 * @return bool
	 */
	protected function shouldSendEvent( string $event ): bool {
		if ( ! $this->isPushEnabled() ) {
			HelpdeskLogger::log( 'push.blocked', array( 'reason' => 'push_disabled', 'event' => $event ) );
			return false;
		}

		if ( ! $this->hasValidConfiguration() ) {
			HelpdeskLogger::log( 'push.blocked', array( 'reason' => 'invalid_configuration', 'event' => $event ) );
			return false;
		}

		$events = (array) get_site_option( Constants::OPTION_PUSH_TICKET_EVENTS, array() );
		if ( ! in_array( $event, $events, true ) ) {
			HelpdeskLogger::log( 'push.blocked', array( 'reason' => 'event_not_in_allowlist', 'event' => $event ) );
			return false;
		}

		return true;
	}

	/**
	 * Check whether push delivery is enabled.
	 *
	 * @return bool
	 */
	protected function isPushEnabled(): bool {
		return 1 === (int) get_site_option( Constants::OPTION_PUSH_ENABLED, 0 );
	}

	/**
	 * Validate the currently saved push configuration.
	 *
	 * @return bool
	 */
	protected function hasValidConfiguration(): bool {
		$mode = (string) get_site_option( Constants::OPTION_FCM_MODE, 'v1' );

		if ( 'legacy' === $mode ) {
			return '' !== trim( (string) get_site_option( Constants::OPTION_FCM_SERVER_KEY, '' ) );
		}

		if ( 'v1' !== $mode ) {
			return false;
		}

		$project_id   = trim( (string) get_site_option( Constants::OPTION_FCM_PROJECT_ID, '' ) );
		$service_json = trim( (string) get_site_option( Constants::OPTION_FCM_SERVICE_ACCOUNT_JSON, '' ) );
		if ( '' === $project_id || '' === $service_json ) {
			return false;
		}

		json_decode( $service_json );
		return JSON_ERROR_NONE === json_last_error();
	}
}
