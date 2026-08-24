<?php
/**
 * @package WP Helpdesk
 */

namespace WPHelpdesk\Domain\Push;

use WPHelpdesk\Support\Constants;
use WPHelpdesk\Support\HelpdeskLogger;

class FirebasePushProvider implements PushProviderInterface {

	protected string $mode;
	protected string $server_key;
	protected string $project_id;
	protected string $service_account_json;

	/** Cached OAuth2 access token and its expiry timestamp. */
	private ?string $access_token      = null;
	private int     $token_expires_at  = 0;

	public function __construct() {
		$this->mode                 = (string) get_site_option( Constants::OPTION_FCM_MODE, 'v1' );
		$this->server_key           = (string) get_site_option( Constants::OPTION_FCM_SERVER_KEY, '' );
		$this->project_id           = (string) get_site_option( Constants::OPTION_FCM_PROJECT_ID, '' );
		$this->service_account_json = (string) get_site_option( Constants::OPTION_FCM_SERVICE_ACCOUNT_JSON, '' );
	}

	/**
	 * Send a push notification through FCM.
	 *
	 * In legacy mode the deprecated FCM send endpoint is used (kept for backwards
	 * compatibility with existing installations).  In v1 mode every token is sent
	 * individually through the FCM HTTP v1 API authenticated with an OAuth2
	 * service-account ******
	 *
	 * @param array<int, string>   $device_tokens Device tokens.
	 * @param string               $title         Notification title.
	 * @param string               $body          Notification body.
	 * @param array<string, mixed> $data          Custom payload.
	 * @return bool True when all messages were accepted, false on any failure.
	 */
	public function send( array $device_tokens, string $title, string $body, array $data = array() ): bool {
		if ( empty( $device_tokens ) ) {
			return false;
		}

		if ( 'v1' === $this->mode ) {
			HelpdeskLogger::log( 'push.fcm_mode', array( 'mode' => 'v1', 'token_count' => count( $device_tokens ) ) );
			return $this->sendV1( $device_tokens, $title, $body, $data );
		}

		HelpdeskLogger::log( 'push.fcm_mode', array( 'mode' => 'legacy', 'token_count' => count( $device_tokens ) ) );
		return $this->sendLegacy( $device_tokens, $title, $body, $data );
	}

	// -------------------------------------------------------------------------
	// Legacy (deprecated) send path – kept for existing installations.
	// -------------------------------------------------------------------------

	private function sendLegacy( array $device_tokens, string $title, string $body, array $data ): bool {
		if ( '' === $this->server_key ) {
			HelpdeskLogger::log( 'push.fcm_error', array( 'error' => 'missing_server_key', 'mode' => 'legacy' ) );
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

		if ( is_wp_error( $response ) ) {
			$err_msg = 'WP Helpdesk Push: FCM legacy request error – ' . $response->get_error_message();
			error_log( $err_msg );
			HelpdeskLogger::log( 'push.fcm_error', array( 'error' => 'wp_error', 'mode' => 'legacy', 'message' => $response->get_error_message() ) );
			return false;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			$err_msg = sprintf( 'WP Helpdesk Push: FCM legacy returned HTTP %d', $code );
			error_log( $err_msg );
			HelpdeskLogger::log( 'push.fcm_error', array( 'error' => 'http_error', 'mode' => 'legacy', 'http_code' => $code ) );
			return false;
		}

		HelpdeskLogger::log( 'push.fcm_sent', array( 'mode' => 'legacy', 'token_count' => count( $device_tokens ) ) );
		return true;
	}

	// -------------------------------------------------------------------------
	// FCM HTTP v1 send path.
	// -------------------------------------------------------------------------

	/**
	 * Send each token individually via the FCM HTTP v1 API.
	 *
	 * @param array<int, string>   $device_tokens
	 * @param string               $title
	 * @param string               $body
	 * @param array<string, mixed> $data
	 * @return bool
	 */
	private function sendV1( array $device_tokens, string $title, string $body, array $data ): bool {
		$project_id = trim( $this->project_id );
		if ( '' === $project_id ) {
			$msg = 'WP Helpdesk Push: FCM v1 requires a project ID.';
			error_log( $msg );
			HelpdeskLogger::log( 'push.fcm_error', array( 'error' => 'missing_project_id' ) );
			return false;
		}

		$bearer = $this->getAccessToken();
		if ( null === $bearer ) {
			$msg = 'WP Helpdesk Push: Unable to obtain FCM OAuth2 access token.';
			error_log( $msg );
			HelpdeskLogger::log( 'push.fcm_error', array( 'error' => 'oauth2_token_unavailable' ) );
			return false;
		}

		$url     = sprintf( 'https://fcm.googleapis.com/v1/projects/%s/messages:send', rawurlencode( $project_id ) );
		$success = true;

		foreach ( $device_tokens as $token ) {
			$payload = array(
				'message' => array(
					'token'        => $token,
					'notification' => array(
						'title' => $title,
						'body'  => $body,
					),
					'data'         => array_map( 'strval', $data ),
				),
			);

			$response = wp_remote_post(
				$url,
				array(
					'headers' => array(
						'Authorization' => 'Bearer ' . $bearer,
						'Content-Type'  => 'application/json',
					),
					'body'    => wp_json_encode( $payload ),
					'timeout' => 15,
				)
			);

			if ( is_wp_error( $response ) ) {
				$err_msg = 'WP Helpdesk Push: FCM v1 request error – ' . $response->get_error_message();
				error_log( $err_msg );
				HelpdeskLogger::log( 'push.fcm_error', array( 'error' => 'wp_error', 'token_prefix' => substr( $token, 0, 8 ), 'message' => $response->get_error_message() ) );
				$success = false;
				continue;
			}

			$code = (int) wp_remote_retrieve_response_code( $response );
			if ( 200 !== $code ) {
				$err_msg = sprintf( 'WP Helpdesk Push: FCM v1 returned HTTP %d for token %s', $code, substr( $token, 0, 8 ) );
				error_log( $err_msg );
				HelpdeskLogger::log( 'push.fcm_error', array( 'error' => 'http_error', 'http_code' => $code, 'token_prefix' => substr( $token, 0, 8 ) ) );
				$success = false;
			} else {
				HelpdeskLogger::log( 'push.fcm_sent', array( 'mode' => 'v1', 'token_prefix' => substr( $token, 0, 8 ) ) );
			}
		}

		return $success;
	}

	// -------------------------------------------------------------------------
	// OAuth2 service-account token acquisition.
	// -------------------------------------------------------------------------

	/**
	 * Return a valid ****** token for FCM, refreshing it when necessary.
	 *
	 * @return string|null Access token, or null on failure.
	 */
	protected function getAccessToken(): ?string {
		if ( null !== $this->access_token && time() < $this->token_expires_at ) {
			return $this->access_token;
		}

		$sa = json_decode( $this->service_account_json, true );
		if ( ! is_array( $sa ) ) {
			return null;
		}

		$client_email = (string) ( $sa['client_email'] ?? '' );
		$private_key  = (string) ( $sa['private_key'] ?? '' );
		if ( '' === $client_email || '' === $private_key ) {
			return null;
		}

		$jwt = $this->buildJwt( $client_email, $private_key );
		if ( null === $jwt ) {
			return null;
		}

		$response = wp_remote_post(
			'https://oauth2.googleapis.com/token',
			array(
				'body'    => array(
					'grant_type' => 'urn:ietf:params:oauth2:grant-type:jwt-bearer',
					'assertion'  => $jwt,
				),
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			error_log( 'WP Helpdesk Push: OAuth2 token request error – ' . $response->get_error_message() );
			return null;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) || empty( $body['access_token'] ) ) {
			return null;
		}

		$this->access_token     = (string) $body['access_token'];
		$this->token_expires_at = time() + max( 0, (int) ( $body['expires_in'] ?? 3600 ) - 60 );

		return $this->access_token;
	}

	/**
	 * Build a signed JWT for the Google OAuth2 token endpoint.
	 *
	 * @param string $client_email Service account e-mail.
	 * @param string $private_key  PEM-encoded RSA private key.
	 * @return string|null Base64url-encoded signed JWT, or null on failure.
	 */
	private function buildJwt( string $client_email, string $private_key ): ?string {
		$now = time();

		$header = $this->base64UrlEncode(
			(string) wp_json_encode(
				array(
					'alg' => 'RS256',
					'typ' => 'JWT',
				)
			)
		);

		$claim = $this->base64UrlEncode(
			(string) wp_json_encode(
				array(
					'iss'   => $client_email,
					'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
					'aud'   => 'https://oauth2.googleapis.com/token',
					'iat'   => $now,
					'exp'   => $now + 3600,
				)
			)
		);

		$signing_input = $header . '.' . $claim;
		$signature     = '';

		if ( ! openssl_sign( $signing_input, $signature, $private_key, OPENSSL_ALGO_SHA256 ) ) {
			error_log( 'WP Helpdesk Push: Failed to sign JWT – check the private_key in the service account JSON.' );
			return null;
		}

		return $signing_input . '.' . $this->base64UrlEncode( $signature );
	}

	/**
	 * Base64url-encode a string (RFC 4648 §5, no padding).
	 *
	 * @param string $data Raw bytes.
	 * @return string
	 */
	private function base64UrlEncode( string $data ): string {
		return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
	}
}
