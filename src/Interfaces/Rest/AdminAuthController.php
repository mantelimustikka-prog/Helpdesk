<?php
/**
 * @package WP Helpdesk
 */

namespace WPHelpdesk\Interfaces\Rest;

use WP_REST_Request;
use WP_REST_Response;
use WPHelpdesk\Support\Constants;

class AdminAuthController extends AdminApiController {
	public function check( WP_REST_Request $request ): WP_REST_Response {
		$user = wp_get_current_user();

		return $this->success(
			array(
				'user'       => array(
					'id'    => (int) $user->ID,
					'name'  => (string) $user->display_name,
					'email' => (string) $user->user_email,
					'roles' => array_values( (array) $user->roles ),
				),
				'appearance' => array(
					'admin_reply_color'          => (string) get_site_option( Constants::OPTION_APPEARANCE_ADMIN_REPLY_COLOR, '' ),
					'client_reply_color'         => (string) get_site_option( Constants::OPTION_APPEARANCE_CLIENT_REPLY_COLOR, '' ),
					'status_new_color'           => (string) get_site_option( Constants::OPTION_APPEARANCE_STATUS_NEW_COLOR, '' ),
					'status_pending_agent_color' => (string) get_site_option( Constants::OPTION_APPEARANCE_STATUS_PENDING_AGENT_COLOR, '' ),
					'status_pending_client_color' => (string) get_site_option( Constants::OPTION_APPEARANCE_STATUS_PENDING_CLIENT_COLOR, '' ),
					'status_resolved_color'      => (string) get_site_option( Constants::OPTION_APPEARANCE_STATUS_RESOLVED_COLOR, '' ),
					'status_closed_color'        => (string) get_site_option( Constants::OPTION_APPEARANCE_STATUS_CLOSED_COLOR, '' ),
				),
			)
		);
	}
}
