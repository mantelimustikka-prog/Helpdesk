<?php
/**
 * @package WP Helpdesk
 */

namespace WPHelpdesk\Interfaces\Rest;

use WP_REST_Request;
use WP_REST_Response;
use WPHelpdesk\Infrastructure\Database\Schema;
use WPHelpdesk\Support\Constants;

class DeviceController {
	/**
	 * Register or update a device token.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function register( WP_REST_Request $request ): WP_REST_Response {
		global $wpdb;

		$device_token = sanitize_text_field( (string) $request->get_param( 'device_token' ) );
		if ( '' === $device_token ) {
			return new WP_REST_Response( array( 'message' => 'Device token is required.' ), 400 );
		}

		$table = Schema::table( Constants::TABLE_DEVICE_TOKENS );
		$sql   = $wpdb->prepare(
			"INSERT INTO {$table} (user_id, device_token, platform, app_version, is_active, last_seen_at, created_at)
			VALUES (%d, %s, %s, %s, 1, %s, %s)
			ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), platform = VALUES(platform), app_version = VALUES(app_version), is_active = 1, last_seen_at = VALUES(last_seen_at)",
			get_current_user_id(),
			$device_token,
			sanitize_key( (string) $request->get_param( 'platform' ) ?: 'android' ),
			sanitize_text_field( (string) $request->get_param( 'app_version' ) ),
			current_time( 'mysql', true ),
			current_time( 'mysql', true )
		);

		$wpdb->query( $sql );

		return new WP_REST_Response( array( 'registered' => true ), 201 );
	}

	/**
	 * Deactivate a device token.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function unregister( WP_REST_Request $request ): WP_REST_Response {
		global $wpdb;

		$device_token = sanitize_text_field( (string) $request->get_param( 'device_token' ) );
		if ( '' === $device_token ) {
			return new WP_REST_Response( array( 'message' => 'Device token is required.' ), 400 );
		}

		$table = Schema::table( Constants::TABLE_DEVICE_TOKENS );
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET is_active = 0, last_seen_at = %s WHERE user_id = %d AND device_token = %s",
				current_time( 'mysql', true ),
				get_current_user_id(),
				$device_token
			)
		);

		return new WP_REST_Response( array( 'registered' => false ) );
	}
}
