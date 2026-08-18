<?php
/**
 * @package WP Helpdesk
 */

namespace WPHelpdesk\Bootstrap;

use WPHelpdesk\Domain\Attachment\AttachmentService;
use WPHelpdesk\Domain\Notification\NotificationService;
use WPHelpdesk\Domain\Push\FirebasePushProvider;
use WPHelpdesk\Domain\Push\PushService;
use WPHelpdesk\Infrastructure\Logger;
use WPHelpdesk\Interfaces\Admin\NetworkMenu;
use WPHelpdesk\Interfaces\Rest\Routes;

class Plugin {
	protected NetworkMenu $network_menu;
	protected Routes $routes;
	protected NotificationService $notification_service;
	protected PushService $push_service;
	protected AttachmentService $attachment_service;
	protected Logger $logger;

	public function __construct(
		?NetworkMenu $network_menu = null,
		?Routes $routes = null,
		?NotificationService $notification_service = null,
		?PushService $push_service = null,
		?AttachmentService $attachment_service = null,
		?Logger $logger = null
	) {
		$this->network_menu         = $network_menu ?: new NetworkMenu();
		$this->routes               = $routes ?: new Routes();
		$this->notification_service = $notification_service ?: new NotificationService();
		$this->push_service         = $push_service ?: new PushService( new FirebasePushProvider() );
		$this->attachment_service   = $attachment_service ?: new AttachmentService();
		$this->logger               = $logger ?: new Logger();
	}

	/**
	 * Boot the plugin services.
	 *
	 * @return void
	 */
	public function boot(): void {
		$this->loadTextDomain();

		add_action( 'network_admin_menu', array( $this->network_menu, 'register' ) );
		add_action( 'rest_api_init', array( $this->routes, 'register_rest_routes' ) );

		add_action( 'hd_ticket_replied', array( $this, 'handleTicketReplied' ), 10, 2 );
		add_action( 'hd_ticket_status_changed', array( $this, 'handleTicketStatusChanged' ), 10, 3 );
		add_action( 'hd_ticket_created', array( $this, 'handleTicketCreated' ), 10, 1 );
		add_action( 'hd_ticket_assigned', array( $this, 'handleTicketAssigned' ), 10, 2 );
	}

	/**
	 * Load translations.
	 *
	 * @return void
	 */
	public function loadTextDomain(): void {
		load_plugin_textdomain( 'wp-helpdesk', false, dirname( HD_BASENAME ) . '/languages' );
	}

	/**
	 * Trigger notifications for a new ticket.
	 *
	 * @param array<string, mixed> $ticket Ticket data.
	 * @return void
	 */
	public function handleTicketCreated( array $ticket ): void {
		if ( ! empty( $ticket['requester_email'] ) ) {
			$this->notification_service->sendTicketCreated( $ticket, (string) $ticket['requester_email'] );
		}

		$this->notification_service->sendTicketCreatedAdmin( $ticket );
		$this->push_service->notifyNewTicket( $ticket );
	}

	/**
	 * Trigger notifications for replies.
	 *
	 * @param array<string, mixed> $ticket  Ticket data.
	 * @param array<string, mixed> $message Message data.
	 * @return void
	 */
	public function handleTicketReplied( array $ticket, array $message ): void {
		if ( ! empty( $ticket['requester_email'] ) ) {
			$this->notification_service->sendTicketReply( $ticket, $message, (string) $ticket['requester_email'] );
		}

		$this->push_service->notifyNewReply( $ticket, $message );
	}

	/**
	 * Trigger notifications for status changes.
	 *
	 * @param array<string, mixed> $ticket      Ticket data.
	 * @param string               $old_status  Previous status.
	 * @param string               $new_status  New status.
	 * @return void
	 */
	public function handleTicketStatusChanged( array $ticket, string $old_status, string $new_status ): void {
		if ( ! empty( $ticket['requester_email'] ) ) {
			$this->notification_service->sendStatusChanged( $ticket, $old_status, $new_status, (string) $ticket['requester_email'] );
		}

		$this->push_service->notifyStatusChanged( $ticket, $new_status );
	}

	/**
	 * Trigger push notifications for assignments.
	 *
	 * @param array<string, mixed> $ticket      Ticket data.
	 * @param int                  $assigned_to Assignee user ID.
	 * @return void
	 */
	public function handleTicketAssigned( array $ticket, int $assigned_to ): void {
		if ( $assigned_to > 0 ) {
			$this->push_service->notifyAssigned( $ticket, $assigned_to );
		}
	}
}
