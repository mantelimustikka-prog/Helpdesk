<?php
/**
 * @package WP Helpdesk
 */

namespace WPHelpdesk\Interfaces\Rest;

use WP_Error;
use WP_REST_Request;
use WPHelpdesk\Domain\Attachment\AttachmentService;
use WPHelpdesk\Support\Helpers;

class Routes {
	protected AdminApiController $admin_api_controller;
	protected AdminAuthController $admin_auth_controller;
	protected AdminTicketController $admin_ticket_controller;
	protected AdminUserController $admin_user_controller;
	protected AdminAttachmentController $admin_attachment_controller;
	protected NotificationController $notification_controller;
	protected AttachmentController $attachment_controller;
	protected PublicTicketController $public_ticket_controller;
	protected AppPasswordResetController $app_password_reset_controller;
	protected FCMTokenController $fcm_token_controller;

	public function __construct() {
		$this->admin_api_controller          = new AdminApiController();
		$this->admin_auth_controller         = new AdminAuthController();
		$this->admin_ticket_controller       = new AdminTicketController();
		$this->admin_user_controller         = new AdminUserController();
		$this->admin_attachment_controller   = new AdminAttachmentController();
		$this->notification_controller       = new NotificationController();
		$this->attachment_controller         = new AttachmentController( new AttachmentService() );
		$this->public_ticket_controller      = new PublicTicketController();
		$this->app_password_reset_controller = new AppPasswordResetController();
		$this->fcm_token_controller          = new FCMTokenController();
	}

	/**
	 * Register all v1 REST routes.
	 *
	 * @return void
	 */
	public function register_rest_routes(): void {
		$namespace = Helpers::restNamespace() . '/admin';

		register_rest_route(
			$namespace,
			'/auth/check',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this->admin_auth_controller, 'check' ),
				'permission_callback' => array( $this->admin_api_controller, 'canAccess' ),
			)
		);

		register_rest_route(
			$namespace,
			'/tickets',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this->admin_ticket_controller, 'listTickets' ),
					'permission_callback' => array( $this->admin_api_controller, 'canAccess' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this->admin_ticket_controller, 'createTicket' ),
					'permission_callback' => array( $this->admin_api_controller, 'canAccess' ),
				),
			)
		);

		register_rest_route(
			$namespace,
			'/tickets/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this->admin_ticket_controller, 'getTicket' ),
				'permission_callback' => array( $this->admin_api_controller, 'canAccess' ),
			)
		);

		register_rest_route(
			$namespace,
			'/tickets/(?P<id>\d+)/messages',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this->admin_ticket_controller, 'getMessages' ),
				'permission_callback' => array( $this->admin_api_controller, 'canAccess' ),
			)
		);

		register_rest_route(
			$namespace,
			'/tickets/(?P<id>\d+)/reply',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this->admin_ticket_controller, 'reply' ),
				'permission_callback' => array( $this->admin_api_controller, 'canAccess' ),
			)
		);

		register_rest_route(
			$namespace,
			'/tickets/(?P<id>\d+)/status',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this->admin_ticket_controller, 'updateStatus' ),
				'permission_callback' => array( $this->admin_api_controller, 'canAccess' ),
			)
		);

		register_rest_route(
			$namespace,
			'/tickets/(?P<id>\d+)/note',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this->admin_ticket_controller, 'addNote' ),
				'permission_callback' => array( $this->admin_api_controller, 'canAccess' ),
			)
		);

		register_rest_route(
			$namespace,
			'/users',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this->admin_user_controller, 'listUsers' ),
				'permission_callback' => array( $this->admin_api_controller, 'canAccess' ),
			)
		);

		register_rest_route(
			$namespace,
			'/attachments/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this->admin_attachment_controller, 'getAttachment' ),
				'permission_callback' => array( $this->admin_api_controller, 'canAccess' ),
			)
		);

		register_rest_route(
			$namespace,
			'/notifications/since',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this->notification_controller, 'since' ),
				'permission_callback' => array( $this->admin_api_controller, 'canAccess' ),
			)
		);

		register_rest_route(
			$namespace,
			'/notifications/register-device',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this->fcm_token_controller, 'registerDevice' ),
				'permission_callback' => array( $this->admin_api_controller, 'canAccess' ),
			)
		);

		register_rest_route(
			$namespace,
			'/notifications/unregister-device',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this->fcm_token_controller, 'unregisterDevice' ),
				'permission_callback' => array( $this->admin_api_controller, 'canAccess' ),
			)
		);

		$this->public_ticket_controller->register( Helpers::restNamespace() );

		$public_ns = Helpers::restNamespace();

		register_rest_route(
			$public_ns,
			'/public/app/request-reset-code',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this->app_password_reset_controller, 'requestResetCode' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			$public_ns,
			'/public/app/verify-reset-code',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this->app_password_reset_controller, 'verifyResetCode' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			$public_ns,
			'/public/app/reset-password',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this->app_password_reset_controller, 'resetPassword' ),
				'permission_callback' => '__return_true',
			)
		);
	}
}
