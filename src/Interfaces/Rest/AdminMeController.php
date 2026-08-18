<?php
/**
 * @package WP Helpdesk
 */

namespace WPHelpdesk\Interfaces\Rest;

use WP_REST_Request;
use WP_REST_Response;

class AdminMeController {
	/**
	 * Return the authenticated admin profile.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function getMe( WP_REST_Request $request ): WP_REST_Response {
		$user = wp_get_current_user();

		return new WP_REST_Response(
			array(
				'id'           => (int) $user->ID,
				'display_name' => $user->display_name,
				'email'        => $user->user_email,
				'capabilities' => array(
					'hd_manage_settings' => current_user_can( 'hd_manage_settings' ),
					'hd_manage_topics'   => current_user_can( 'hd_manage_topics' ),
					'hd_manage_tickets'  => current_user_can( 'hd_manage_tickets' ),
					'hd_reply_tickets'   => current_user_can( 'hd_reply_tickets' ),
					'hd_view_reports'    => current_user_can( 'hd_view_reports' ),
				),
			)
		);
	}
}
