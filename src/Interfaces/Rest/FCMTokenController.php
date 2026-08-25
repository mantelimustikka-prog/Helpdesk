<?php
/**
 * @package WP Helpdesk
 */

namespace WPHelpdesk\Interfaces\Rest;

use WP_REST_Request;
use WP_REST_Response;
use WPHelpdesk\Services\FCMService;

/**
 * REST controller for FCM device-token registration and removal.
 *
 * Endpoints (under the admin namespace):
 *   POST /wp-json/helpdesk/v1/admin/notifications/register-device
 *   POST /wp-json/helpdesk/v1/admin/notifications/unregister-device
 *
 * The Android app calls these endpoints after login (register) and on logout
 * (unregister) so the backend can target the correct device when pushing FCM
 * notifications.
 */
class FCMTokenController extends AdminApiController {

	private FCMService $fcm_service;

	public function __construct( ?FCMService $fcm_service = null ) {
		$this->fcm_service = $fcm_service ?: new FCMService();
	}

	/**
	 * Register a device FCM token for the currently authenticated user.
	 *
	 * Request body (JSON):
	 *   { "token": "<fcm_registration_token>" }
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response
	 */
	public function registerDevice( WP_REST_Request $request ): WP_REST_Response {
		$token = trim( (string) $request->get_param( 'token' ) );

		if ( '' === $token ) {
			return $this->error( 'missing_token', __( 'The token field is required.', 'wp-helpdesk' ), 400 );
		}

		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return $this->error( 'not_authenticated', __( 'Authentication required.', 'wp-helpdesk' ), 401 );
		}

		$this->fcm_service->registerToken( $user_id, $token );

		return $this->success( array( 'message' => __( 'Device registered.', 'wp-helpdesk' ) ) );
	}

	/**
	 * Unregister a device FCM token for the currently authenticated user.
	 *
	 * Request body (JSON):
	 *   { "token": "<fcm_registration_token>" }
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response
	 */
	public function unregisterDevice( WP_REST_Request $request ): WP_REST_Response {
		$token = trim( (string) $request->get_param( 'token' ) );

		if ( '' === $token ) {
			return $this->error( 'missing_token', __( 'The token field is required.', 'wp-helpdesk' ), 400 );
		}

		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return $this->error( 'not_authenticated', __( 'Authentication required.', 'wp-helpdesk' ), 401 );
		}

		$this->fcm_service->unregisterToken( $user_id, $token );

		return $this->success( array( 'message' => __( 'Device unregistered.', 'wp-helpdesk' ) ) );
	}
}
