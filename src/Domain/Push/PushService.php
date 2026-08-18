<?php
/**
 * @package WP Helpdesk
 */

namespace WPHelpdesk\Domain\Push;

use WPHelpdesk\Infrastructure\Database\Schema;
use WPHelpdesk\Support\Constants;

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
		$this->provider->send(
			$this->getAdminTokens(),
			'New ticket created',
			(string) ( $ticket['subject'] ?? '' ),
			array( 'ticket_id' => (int) ( $ticket['id'] ?? 0 ) )
		);
	}

	/**
	 * Notify active admins of a new reply.
	 *
	 * @param array<string, mixed> $ticket  Ticket data.
	 * @param array<string, mixed> $message Message data.
	 * @return void
	 */
	public function notifyNewReply( array $ticket, array $message ): void {
		$this->provider->send(
			$this->getAdminTokens(),
			'New ticket reply',
			wp_trim_words( wp_strip_all_tags( (string) ( $message['body'] ?? '' ) ), 20 ),
			array( 'ticket_id' => (int) ( $ticket['id'] ?? 0 ) )
		);
	}

	/**
	 * Notify active admins of a status update.
	 *
	 * @param array<string, mixed> $ticket     Ticket data.
	 * @param string               $new_status Updated status.
	 * @return void
	 */
	public function notifyStatusChanged( array $ticket, string $new_status ): void {
		$this->provider->send(
			$this->getAdminTokens(),
			'Ticket status changed',
			sprintf( 'Ticket %s is now %s.', (string) ( $ticket['ticket_no'] ?? '' ), $new_status ),
			array( 'ticket_id' => (int) ( $ticket['id'] ?? 0 ) )
		);
	}

	/**
	 * Notify an assigned agent.
	 *
	 * @param array<string, mixed> $ticket      Ticket data.
	 * @param int                  $assigned_to Assignee user ID.
	 * @return void
	 */
	public function notifyAssigned( array $ticket, int $assigned_to ): void {
		$this->provider->send(
			$this->getUserTokens( $assigned_to ),
			'Ticket assigned',
			sprintf( 'Ticket %s has been assigned to you.', (string) ( $ticket['ticket_no'] ?? '' ) ),
			array( 'ticket_id' => (int) ( $ticket['id'] ?? 0 ) )
		);
	}

	/**
	 * Fetch all active admin device tokens.
	 *
	 * @return array<int, string>
	 */
	private function getAdminTokens(): array {
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

		return array_values( array_unique( $tokens ) );
	}

	/**
	 * Fetch active device tokens for a user.
	 *
	 * @param int $user_id User ID.
	 * @return array<int, string>
	 */
	private function getUserTokens( int $user_id ): array {
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
}
