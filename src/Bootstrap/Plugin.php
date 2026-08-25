<?php
/**
 * @package WP Helpdesk
 */

namespace WPHelpdesk\Bootstrap;

use WPHelpdesk\Domain\Attachment\AttachmentService;
use WPHelpdesk\Domain\KnowledgeBase\KnowledgeBaseService;
use WPHelpdesk\Domain\Notification\EmailTemplateDefaults;
use WPHelpdesk\Domain\Notification\NotificationService;
use WPHelpdesk\Domain\Privacy\GdprHandler;
use WPHelpdesk\Domain\Privacy\RetentionService;
use WPHelpdesk\Domain\Routing\RoutingService;
use WPHelpdesk\Domain\SLA\SlaService;
use WPHelpdesk\Domain\Ticket\TicketLifecycleService;
use WPHelpdesk\Infrastructure\Logger;
use WPHelpdesk\Interfaces\Admin\NetworkMenu;
use WPHelpdesk\Interfaces\Frontend\FrontendRouter;
use WPHelpdesk\Interfaces\Frontend\WooCommerceAccountHelpdesk;
use WPHelpdesk\Interfaces\Rest\Routes;
use WPHelpdesk\Bootstrap\RewriteRuleManager;
use WPHelpdesk\Services\FCMService;

class Plugin {
	protected NetworkMenu $network_menu;
	protected Routes $routes;
	protected NotificationService $notification_service;
	protected AttachmentService $attachment_service;
	protected Logger $logger;
	protected FrontendRouter $frontend_router;
	protected WooCommerceAccountHelpdesk $woocommerce_account_helpdesk;
	protected SlaService $sla_service;
	protected RoutingService $routing_service;
	protected KnowledgeBaseService $kb_service;
	protected GdprHandler $gdpr_handler;
	protected RetentionService $retention_service;
	protected TicketLifecycleService $ticket_lifecycle_service;
	protected RewriteRuleManager $rewrite_manager;
	protected FCMService $fcm_service;

	public function __construct(
		?NetworkMenu $network_menu = null,
		?Routes $routes = null,
		?NotificationService $notification_service = null,
		?AttachmentService $attachment_service = null,
		?Logger $logger = null,
		?FrontendRouter $frontend_router = null,
		?WooCommerceAccountHelpdesk $woocommerce_account_helpdesk = null,
		?SlaService $sla_service = null,
		?RoutingService $routing_service = null,
		?KnowledgeBaseService $kb_service = null,
		?GdprHandler $gdpr_handler = null,
		?RetentionService $retention_service = null,
		?TicketLifecycleService $ticket_lifecycle_service = null,
		?RewriteRuleManager $rewrite_manager = null,
		?FCMService $fcm_service = null
	) {
		$this->network_menu         = $network_menu ?: new NetworkMenu();
		$this->routes               = $routes ?: new Routes();
		$this->notification_service = $notification_service ?: new NotificationService();
		$this->attachment_service   = $attachment_service ?: new AttachmentService();
		$this->logger               = $logger ?: new Logger();
		$this->frontend_router      = $frontend_router ?: new FrontendRouter();
		$this->woocommerce_account_helpdesk = $woocommerce_account_helpdesk ?: new WooCommerceAccountHelpdesk();
		$this->sla_service          = $sla_service ?: new SlaService();
		$this->routing_service      = $routing_service ?: new RoutingService();
		$this->kb_service           = $kb_service ?: new KnowledgeBaseService();
		$this->gdpr_handler         = $gdpr_handler ?: new GdprHandler();
		$this->retention_service    = $retention_service ?: new RetentionService();
		$this->ticket_lifecycle_service = $ticket_lifecycle_service ?: new TicketLifecycleService();
		$this->rewrite_manager      = $rewrite_manager ?: new RewriteRuleManager();
		$this->fcm_service          = $fcm_service ?: new FCMService();
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

		// Seed empty email template settings from built-in defaults on first admin load.
		add_action( 'admin_init', array( EmailTemplateDefaults::class, 'seedIfEmpty' ) );

		$this->frontend_router->register();
		// Register a wp_loaded heartbeat to detect and auto-fix missing rewrite
		// rules on any front-end request, covering sites in a multisite network
		// that may have been skipped during activation.
		add_action( 'wp_loaded', array( $this->rewrite_manager, 'ensureRulesExist' ) );
		// Defer WooCommerce My Account integration until `init` (priority 1) so
		// that WooCommerce is guaranteed to have finished its own plugins_loaded
		// callback before we call class_exists('WooCommerce') and
		// wc_get_page_permalink().  Calling register() synchronously at
		// plugins_loaded causes a silent bail-out when our plugin loads before
		// WooCommerce in the plugin load order.
		add_action( 'init', array( $this->woocommerce_account_helpdesk, 'register' ), 1 );

		// Ticket lifecycle hooks (notifications).
		add_action( 'hd_ticket_replied', array( $this, 'handleTicketReplied' ), 10, 2 );
		add_action( 'hd_ticket_replied', array( $this->ticket_lifecycle_service, 'syncStatusAfterReply' ), 20, 2 );
		add_action( 'hd_ticket_status_changed', array( $this, 'handleTicketStatusChanged' ), 10, 3 );
		add_action( 'hd_ticket_created', array( $this, 'handleTicketCreated' ), 10, 1 );

		// P3: SLA cron.
		add_filter( 'cron_schedules', array( $this, 'addCronSchedules' ) );
		add_action( 'hd_sla_breach_check', array( $this->sla_service, 'checkBreaches' ) );

		// P3: Retention cron.
		add_action( 'hd_retention_purge', array( $this->retention_service, 'purgeExpired' ) );
		add_action( TicketLifecycleService::CRON_HOOK, array( $this->ticket_lifecycle_service, 'runAutoTransitions' ) );
		TicketLifecycleService::scheduleCron();

		// P3: GDPR export/erase hooks.
		$this->gdpr_handler->register();
	}

	/**
	 * Register helpdesk custom cron schedules.
	 *
	 * @param array<string, array<string, mixed>> $schedules Existing schedules.
	 * @return array<string, array<string, mixed>>
	 */
	public function addCronSchedules( array $schedules ): array {
		if ( ! isset( $schedules['hd_every_15_minutes'] ) ) {
			$schedules['hd_every_15_minutes'] = array(
				'interval' => 15 * MINUTE_IN_SECONDS,
				'display'  => __( 'Every 15 minutes (WP Helpdesk)', 'wp-helpdesk' ),
			);
		}

		return $schedules;
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
		// P3: apply routing rules.
		$routing = $this->routing_service->resolveForTicket( $ticket );
		// If the matched rule specifies an extra notification_email (e.g. a queue
		// mailbox), send the ticket-created notification to that address in addition
		// to the requester and the network admin below.
		if ( ! empty( $routing['notification_email'] ) ) {
			$this->notification_service->sendTicketCreated( $ticket, $routing['notification_email'] );
		}

		if ( ! empty( $ticket['requester_email'] ) ) {
			$this->notification_service->sendTicketCreated( $ticket, (string) $ticket['requester_email'] );
		}

		$this->notification_service->sendTicketCreatedAdmin( $ticket );

		// FCM push to all admin app users so the notification arrives instantly
		// even when the Android app is in the background or Doze mode is active.
		$subject = isset( $ticket['subject'] ) ? (string) $ticket['subject'] : __( 'New ticket', 'wp-helpdesk' );
		foreach ( $this->getAdminUserIds() as $admin_id ) {
			$this->fcm_service->sendPush(
				$admin_id,
				__( 'New Helpdesk Ticket', 'wp-helpdesk' ),
				$subject,
				array(
					'new_tickets' => '1',
					'new_replies' => '0',
				)
			);
		}

		// P3: stamp SLA deadlines.
		$this->sla_service->stampDeadlines( $ticket );
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

		// FCM push: notify all admin app users of the new reply so it arrives instantly.
		$excerpt = isset( $message['body'] )
			? mb_substr( wp_strip_all_tags( (string) $message['body'] ), 0, 100 )
			: __( 'New reply', 'wp-helpdesk' );
		foreach ( $this->getAdminUserIds() as $admin_id ) {
			$this->fcm_service->sendPush(
				$admin_id,
				__( 'New Helpdesk Reply', 'wp-helpdesk' ),
				$excerpt,
				array(
					'new_tickets' => '0',
					'new_replies' => '1',
				)
			);
		}
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
	}

	/**
	 * Return the WordPress user IDs of all users with helpdesk management or
	 * reply capabilities (i.e. all potential Android admin-app users).
	 *
	 * @return int[]
	 */
	private function getAdminUserIds(): array {
		$limit = 200;
		$users = get_users(
			array(
				'capability' => array( 'hd_manage_tickets', 'hd_reply_tickets' ),
				'fields'     => 'ID',
				'number'     => $limit,
			)
		);

		if ( count( $users ) >= $limit ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'WP Helpdesk FCM: admin user query hit the limit of ' . $limit . '. Some users may not receive push notifications.' );
		}

		return array_map( 'intval', $users );
	}
}
