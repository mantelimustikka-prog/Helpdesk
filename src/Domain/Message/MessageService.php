<?php
/**
 * @package WP Helpdesk
 */

namespace WPHelpdesk\Domain\Message;

class MessageService {

	protected MessageRepository $repository;

	public function __construct() {
		$this->repository = new MessageRepository();
	}

	/**
	 * List messages for a ticket.
	 *
	 * @param int   $ticket_id Ticket id.
	 * @param array $args      Optional: page, per_page, author_type, is_internal.
	 * @return array<int, array<string, mixed>>
	 */
	public function listMessages( int $ticket_id, array $args = [] ): array {
		return $this->repository->list( $ticket_id, $args );
	}

	/**
	 * Count messages for a ticket.
	 *
	 * @param int   $ticket_id Ticket id.
	 * @param array $args      Optional filters.
	 * @return int
	 */
	public function countMessages( int $ticket_id, array $args = [] ): int {
		return $this->repository->count( $ticket_id, $args );
	}

	/**
	 * Post a new reply to a ticket thread.
	 *
	 * @param int                  $ticket_id      Ticket id.
	 * @param string               $body           Message body.
	 * @param string               $author_type    One of: guest, member, agent, system.
	 * @param int|null             $author_user_id Author WP user id (null for guests).
	 * @param bool                 $is_internal    Whether the message is internal (agent note).
	 * @return int Inserted message id, or 0 on failure.
	 */
	public function postReply(
		int $ticket_id,
		string $body,
		string $author_type = 'agent',
		?int $author_user_id = null,
		bool $is_internal = false
	): int {
		$body = trim( $body );
		if ( '' === $body || $ticket_id <= 0 ) {
			return 0;
		}

		if ( ! in_array( $author_type, MessageRepository::AUTHOR_TYPES, true ) ) {
			$author_type = 'agent';
		}

		$id = $this->repository->create(
			[
				'ticket_id'      => $ticket_id,
				'author_user_id' => $author_user_id,
				'author_type'    => $author_type,
				'body'           => wp_kses_post( $body ),
				'is_internal'    => $is_internal ? 1 : 0,
				'created_at'     => current_time( 'mysql' ),
			]
		);

		if ( $id > 0 && ! $is_internal ) {
			/**
			 * Fires after a new non-internal message is posted on a ticket thread.
			 *
			 * @param int   $id         Message id.
			 * @param int   $ticket_id  Ticket id.
			 * @param array $message    Message row data.
			 */
			do_action(
				'hd_ticket_reply_posted',
				$id,
				$ticket_id,
				[
					'id'             => $id,
					'ticket_id'      => $ticket_id,
					'author_user_id' => $author_user_id,
					'author_type'    => $author_type,
					'body'           => $body,
					'is_internal'    => 0,
				]
			);
		}

		return $id;
	}

	/**
	 * Get a single message.
	 *
	 * @param int $id Message id.
	 * @return array<string, mixed>|null
	 */
	public function getMessage( int $id ): ?array {
		return $this->repository->find( $id );
	}

	/**
	 * Delete a message.
	 *
	 * Agents with hd_manage_tickets can delete any message.
	 * Authors can only delete their own messages.
	 *
	 * @param int      $id      Message id.
	 * @param int|null $user_id Current user id.
	 * @return bool
	 */
	public function deleteMessage( int $id, ?int $user_id = null ): bool {
		$message = $this->repository->find( $id );
		if ( ! $message ) {
			return false;
		}

		$is_agent = function_exists( 'current_user_can' ) && current_user_can( 'hd_manage_tickets' );

		if ( ! $is_agent ) {
			if ( null === $user_id || (int) $message['author_user_id'] !== $user_id ) {
				return false;
			}
		}

		return $this->repository->delete( $id );
	}
}
