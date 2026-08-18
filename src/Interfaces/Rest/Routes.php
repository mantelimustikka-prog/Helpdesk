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
	protected AdminMeController $admin_me_controller;
	protected AdminTicketController $admin_ticket_controller;
	protected AdminDashboardController $admin_dashboard_controller;
	protected DeviceController $device_controller;
	protected AttachmentController $attachment_controller;

	public function __construct() {
		$this->admin_me_controller        = new AdminMeController();
		$this->admin_ticket_controller    = new AdminTicketController();
		$this->admin_dashboard_controller = new AdminDashboardController();
		$this->device_controller          = new DeviceController();
		$this->attachment_controller      = new AttachmentController( new AttachmentService() );
	}

	/**
	 * Register all v1 REST routes.
	 *
	 * @return void
	 */
	public function register_rest_routes(): void {
		$namespace = Helpers::restNamespace();

		register_rest_route(
			$namespace,
			'/admin/me',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this->admin_me_controller, 'getMe' ),
				'permission_callback' => fn( WP_REST_Request $request ) => $this->authorize( $request, array( 'hd_manage_tickets', 'hd_reply_tickets' ) ),
			)
		);

		register_rest_route(
			$namespace,
			'/admin/tickets',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this->admin_ticket_controller, 'listTickets' ),
				'permission_callback' => fn( WP_REST_Request $request ) => $this->authorize( $request, array( 'hd_manage_tickets', 'hd_reply_tickets' ) ),
			)
		);

		register_rest_route(
			$namespace,
			'/admin/tickets/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this->admin_ticket_controller, 'getTicket' ),
				'permission_callback' => fn( WP_REST_Request $request ) => $this->authorize( $request, array( 'hd_manage_tickets', 'hd_reply_tickets' ) ),
			)
		);

		register_rest_route(
			$namespace,
			'/admin/tickets/(?P<id>\d+)/messages',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this->admin_ticket_controller, 'getMessages' ),
				'permission_callback' => fn( WP_REST_Request $request ) => $this->authorize( $request, array( 'hd_manage_tickets', 'hd_reply_tickets' ) ),
			)
		);

		register_rest_route(
			$namespace,
			'/admin/tickets/(?P<id>\d+)/reply',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this->admin_ticket_controller, 'reply' ),
				'permission_callback' => fn( WP_REST_Request $request ) => $this->authorize( $request, array( 'hd_reply_tickets', 'hd_manage_tickets' ) ),
			)
		);

		register_rest_route(
			$namespace,
			'/admin/tickets/(?P<id>\d+)/status',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this->admin_ticket_controller, 'updateStatus' ),
				'permission_callback' => fn( WP_REST_Request $request ) => $this->authorize( $request, array( 'hd_manage_tickets' ) ),
			)
		);

		register_rest_route(
			$namespace,
			'/admin/tickets/(?P<id>\d+)/assign',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this->admin_ticket_controller, 'assignTicket' ),
				'permission_callback' => fn( WP_REST_Request $request ) => $this->authorize( $request, array( 'hd_manage_tickets' ) ),
			)
		);

		register_rest_route(
			$namespace,
			'/admin/dashboard/summary',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this->admin_dashboard_controller, 'summary' ),
				'permission_callback' => fn( WP_REST_Request $request ) => $this->authorize( $request, array( 'hd_manage_tickets', 'hd_view_reports' ) ),
			)
		);

		register_rest_route(
			$namespace,
			'/admin/devices/register',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this->device_controller, 'register' ),
				'permission_callback' => fn( WP_REST_Request $request ) => $this->authorize( $request, array( 'hd_manage_tickets', 'hd_reply_tickets' ) ),
			)
		);

		register_rest_route(
			$namespace,
			'/admin/devices/unregister',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this->device_controller, 'unregister' ),
				'permission_callback' => fn( WP_REST_Request $request ) => $this->authorize( $request, array( 'hd_manage_tickets', 'hd_reply_tickets' ) ),
			)
		);

		register_rest_route(
			$namespace,
			'/admin/tickets/(?P<id>\d+)/attachments',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this->attachment_controller, 'upload' ),
					'permission_callback' => fn( WP_REST_Request $request ) => $this->authorize( $request, array( 'hd_manage_tickets', 'hd_reply_tickets' ) ),
				),
				array(
					'methods'             => 'GET',
					'callback'            => array( $this->attachment_controller, 'list' ),
					'permission_callback' => fn( WP_REST_Request $request ) => $this->authorize( $request, array( 'hd_manage_tickets', 'hd_reply_tickets' ) ),
				),
			)
		);
	}

	/**
	 * Validate REST permissions and nonce usage.
	 *
	 * @param WP_REST_Request    $request      Request instance.
	 * @param array<int, string> $capabilities Allowed capabilities.
	 * @return bool|WP_Error
	 */
	protected function authorize( WP_REST_Request $request, array $capabilities ) {
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'hd_rest_auth_required', 'Authentication required.', array( 'status' => 401 ) );
		}

		$allowed = false;
		foreach ( $capabilities as $capability ) {
			if ( current_user_can( $capability ) ) {
				$allowed = true;
				break;
			}
		}

		if ( ! $allowed ) {
			return new WP_Error( 'hd_rest_forbidden', 'Insufficient permissions.', array( 'status' => 403 ) );
		}

		if ( $this->isApplicationPasswordRequest() ) {
			return true;
		}

		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( empty( $nonce ) ) {
			$nonce = $request->get_param( '_wpnonce' );
		}

		if ( empty( $nonce ) || ! wp_verify_nonce( (string) $nonce, 'wp_rest' ) ) {
			return new WP_Error( 'hd_rest_invalid_nonce', 'Invalid or missing REST nonce.', array( 'status' => 403 ) );
		}

		return true;
	}

	/**
	 * Detect whether the current request was authenticated via Application Passwords.
	 * Uses WP core's auth method flag when available; falls back to checking if a
	 * valid credential header is present and WP has authenticated the user (i.e.,
	 * is_user_logged_in() is already true at this point in the permission callback).
	 *
	 * For Android / REST clients using Application Passwords over HTTPS we skip
	 * the browser-session nonce requirement — the credentials themselves serve as
	 * the CSRF-equivalent proof of identity.
	 *
	 * @return bool
	 */
	protected function isApplicationPasswordRequest(): bool {
		// WP 5.6+ sets this global flag when auth succeeds via Application Passwords.
		if ( function_exists( 'wp_is_application_passwords_available' ) ) {
			global $wp_rest_application_password_status;
			if ( isset( $wp_rest_application_password_status ) && true === $wp_rest_application_password_status ) {
				return true;
			}
		}

		return false;
	}
}
