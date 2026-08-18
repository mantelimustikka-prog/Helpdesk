<?php
/**
 * @package WP Helpdesk
 */

namespace WPHelpdesk\Domain\Push;

use WPHelpdesk\Support\Constants;

class FirebasePushProvider implements PushProviderInterface {
	/**
	 * IMPORTANT: The legacy FCM HTTP API (https://fcm.googleapis.com/fcm/send + Authorization: key=)
	 * was deprecated by Google and became non-functional in June 2024.
	 * This skeleton is a structural placeholder only. Push delivery will NOT work
	 * until this class is updated to use the FCM v1 HTTP API with OAuth 2.0
	 * service account tokens. See: https://firebase.google.com/docs/cloud-messaging/migrate-v1
	 *
	 * TODO: Replace send() implementation with FCM v1 API + service-account OAuth flow.
	 */
	protected string $server_key;

	public function __construct() {
		$this->server_key = (string) get_site_option( Constants::OPTION_FCM_SERVER_KEY, '' );
	}

	/**
	 * Send a push notification through FCM.
	 *
	 * @param array<int, string>       $device_tokens Device tokens.
	 * @param string                   $title         Notification title.
	 * @param string                   $body          Notification body.
	 * @param array<string, mixed>     $data          Custom payload.
	 * @return bool
	 */
	public function send( array $device_tokens, string $title, string $body, array $data = array() ): bool {
		if ( '' === $this->server_key || empty( $device_tokens ) ) {
			return false;
		}

		$response = wp_remote_post(
			'https://fcm.googleapis.com/fcm/send',
			array(
				'headers' => array(
					'Authorization' => 'key=' . $this->server_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'registration_ids' => array_values( $device_tokens ),
						'notification'     => array(
							'title' => $title,
							'body'  => $body,
						),
						'data'             => $data,
					)
				),
				'timeout' => 15,
			)
		);

		// TODO: Implement full FCM v1 API support with OAuth service accounts.
		return ! is_wp_error( $response ) && 200 === (int) wp_remote_retrieve_response_code( $response );
	}
}
