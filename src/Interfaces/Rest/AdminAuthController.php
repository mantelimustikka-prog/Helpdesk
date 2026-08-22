<?php
/**
 * @package WP Helpdesk
 */

namespace WPHelpdesk\Interfaces\Rest;

use WP_REST_Request;
use WP_REST_Response;

class AdminAuthController extends AdminApiController {
	public function check( WP_REST_Request $request ): WP_REST_Response {
		$user = wp_get_current_user();

		return $this->success(
			array(
				'user' => array(
					'id'    => (int) $user->ID,
					'name'  => (string) $user->display_name,
					'email' => (string) $user->user_email,
					'roles' => array_values( (array) $user->roles ),
				),
			)
		);
	}
}
