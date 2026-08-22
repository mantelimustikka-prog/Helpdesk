<?php
/**
 * @package WP Helpdesk
 */

namespace WPHelpdesk\Interfaces\Rest;

use WP_REST_Request;
use WP_REST_Response;

class AdminUserController extends AdminApiController {
	/**
	 * List WordPress users visible to the admin API.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function listUsers( WP_REST_Request $request ): WP_REST_Response {
		$page     = max( 1, (int) $request->get_param( 'page' ) );
		$per_page = max( 1, min( 100, (int) $request->get_param( 'per_page' ) ?: 20 ) );
		$search   = sanitize_text_field( (string) $request->get_param( 'search' ) );

		$args = array(
			'number'  => $per_page,
			'offset'  => ( $page - 1 ) * $per_page,
			'orderby' => 'display_name',
			'order'   => 'ASC',
			'fields'  => array( 'ID', 'display_name', 'user_email', 'user_login' ),
		);

		if ( '' !== $search ) {
			$args['search']         = '*' . $search . '*';
			$args['search_columns'] = array( 'user_login', 'user_email', 'display_name' );
		}

		$users = get_users( $args );
		$total = (int) ( new \WP_User_Query( array_merge( $args, array( 'count_total' => true, 'number' => -1, 'offset' => 0 ) ) ) )->get_total();

		$items = array_map(
			static function ( $user ): array {
				return array(
					'id'           => (int) $user->ID,
					'display_name' => (string) $user->display_name,
					'email'        => (string) $user->user_email,
					'login'        => (string) $user->user_login,
				);
			},
			$users
		);

		return $this->success(
			array(
				'items'    => $items,
				'total'    => $total,
				'page'     => $page,
				'per_page' => $per_page,
			)
		);
	}
}
