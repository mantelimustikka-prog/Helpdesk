<?php
/**
 * @package WP Helpdesk
 */

namespace WPHelpdesk\Interfaces\Rest;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

class AdminApiController {
	/**
	 * Check whether the current user can access admin API endpoints.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error
	 */
	public function canAccess( WP_REST_Request $request ): bool|WP_Error {
		if ( ! is_user_logged_in() ) {
			return new WP_Error(
				'rest_not_logged_in',
				__( 'Authentication required.', 'wp-helpdesk' ),
				array( 'status' => 401 )
			);
		}

		if ( ! current_user_can( 'hd_manage_tickets' ) && ! current_user_can( 'hd_reply_tickets' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to access the HelpD admin API.', 'wp-helpdesk' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Shared success response helper.
	 *
	 * @param array<string, mixed> $data Response payload.
	 * @param int $status HTTP status.
	 * @return WP_REST_Response
	 */
	protected function success( array $data, int $status = 200 ): WP_REST_Response {
		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => $data,
			),
			$status
		);
	}

	/**
	 * Shared error response helper.
	 *
	 * @param string $code Error code.
	 * @param string $message Error message.
	 * @param int $status HTTP status.
	 * @return WP_REST_Response
	 */
	protected function error( string $code, string $message, int $status ): WP_REST_Response {
		return new WP_REST_Response(
			array(
				'success' => false,
				'error'   => array(
					'code'    => $code,
					'message' => $message,
				),
			),
			$status
		);
	}
}
