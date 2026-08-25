<?php
/**
 * @package WP Helpdesk
 */

namespace WPHelpdesk\Services;

use WPHelpdesk\Infrastructure\Logger;

/**
 * Handles Firebase Cloud Messaging (FCM) push notification delivery and
 * device token management for the Android admin app.
 *
 * Push messages are sent via the FCM HTTP v1 API using short-lived OAuth 2.0
 * access tokens obtained from a service-account JSON key stored as a
 * WordPress network option.
 *
 * All device token storage uses the network option `hd_fcm_tokens` which holds
 * a JSON-encoded map of `user_id => string[]`.
 *
 * Setup required by the site administrator:
 * 1. Create a Firebase project and register the Android app.
 * 2. In Firebase console → Project settings → Service accounts, generate a new
 *    private key (JSON format).
 * 3. Paste the entire JSON content into Settings → WP Helpdesk → Integration.
 *    (Option key: `hd_fcm_service_account_json`)
 */
class FCMService {

	public const OPTION_FCM_SERVICE_ACCOUNT = 'hd_fcm_service_account_json';
	public const OPTION_FCM_TOKENS          = 'hd_fcm_tokens';

	/** Cached access token and its expiry time (Unix timestamp). */
	private string $access_token       = '';
	private int    $access_token_expiry = 0;

	private Logger $logger;

	public function __construct( ?Logger $logger = null ) {
		$this->logger = $logger ?: new Logger();
	}

	/**
	 * Send a data push notification to all registered devices for a user.
	 *
	 * @param int    $user_id WordPress user ID of the recipient.
	 * @param string $title   Notification title shown on the device.
	 * @param string $body    Notification body text.
	 * @param array<string, string> $data  Extra key/value data payload (merged with title/body).
	 * @return bool True if at least one push was dispatched successfully.
	 */
	public function sendPush( int $user_id, string $title, string $body, array $data = array() ): bool {
		$service_account = $this->getServiceAccount();
		if ( empty( $service_account ) ) {
			$this->logger->debug( 'FCM service account not configured — push skipped.' );
			return false;
		}

		$tokens = $this->getTokensForUser( $user_id );
		if ( empty( $tokens ) ) {
			$this->logger->debug( "No FCM tokens for user {$user_id} — push skipped." );
			return false;
		}

		$access_token = $this->getAccessToken( $service_account );
		if ( '' === $access_token ) {
			$this->logger->warning( 'FCM: failed to obtain access token — push skipped.' );
			return false;
		}

		$project_id = $service_account['project_id'] ?? '';
		$payload    = array_merge( $data, array( 'title' => $title, 'body' => $body ) );
		$success    = false;
		$stale      = array();

		foreach ( $tokens as $token ) {
			$response = $this->dispatch( $access_token, $project_id, $token, $payload );
			if ( true === $response ) {
				$success = true;
			} elseif ( 'invalid_token' === $response ) {
				$stale[] = $token;
			}
		}

		if ( ! empty( $stale ) ) {
			$this->removeStaleTokens( $user_id, $stale );
		}

		return $success;
	}

	/**
	 * Register (or refresh) a device FCM token for a user.
	 *
	 * A user may have multiple devices; tokens are stored as a deduped list
	 * capped at 10 to avoid unbounded growth.
	 *
	 * @param int    $user_id WordPress user ID.
	 * @param string $token   FCM registration token from the Android device.
	 * @return void
	 */
	public function registerToken( int $user_id, string $token ): void {
		if ( '' === trim( $token ) ) {
			return;
		}

		$all_tokens              = $this->loadAllTokens();
		$user_tokens             = $all_tokens[ $user_id ] ?? array();
		$user_tokens[]           = $token;
		$user_tokens             = array_values( array_unique( $user_tokens ) );
		if ( count( $user_tokens ) > 10 ) {
			$user_tokens = array_slice( $user_tokens, -10 );
		}
		$all_tokens[ $user_id ] = $user_tokens;
		$this->saveAllTokens( $all_tokens );
	}

	/**
	 * Remove a specific FCM token for a user (e.g. on logout or explicit unregister).
	 *
	 * @param int    $user_id WordPress user ID.
	 * @param string $token   FCM registration token to remove.
	 * @return void
	 */
	public function unregisterToken( int $user_id, string $token ): void {
		$all_tokens              = $this->loadAllTokens();
		$user_tokens             = $all_tokens[ $user_id ] ?? array();
		$user_tokens             = array_values( array_filter( $user_tokens, fn( $t ) => $t !== $token ) );
		$all_tokens[ $user_id ] = $user_tokens;
		$this->saveAllTokens( $all_tokens );
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Dispatch a single FCM push to one device token via the HTTP v1 API.
	 *
	 * @param string $access_token  Short-lived OAuth 2.0 bearer token.
	 * @param string $project_id    Firebase project ID (from service account JSON).
	 * @param string $token         Target device registration token.
	 * @param array<string, string> $data Data payload.
	 * @return true|string True on success; 'invalid_token' for unregistered tokens; error string otherwise.
	 */
	private function dispatch( string $access_token, string $project_id, string $token, array $data ): bool|string {
		$endpoint = "https://fcm.googleapis.com/v1/projects/{$project_id}/messages:send";

		$body = wp_json_encode(
			array(
				'message' => array(
					'token' => $token,
					'data'  => $data,
				),
			)
		);

		$response = wp_remote_post(
			$endpoint,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $access_token,
					'Content-Type'  => 'application/json',
				),
				'body'    => $body,
				'timeout' => 10,
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->logger->warning( 'FCM dispatch wp_error: ' . $response->get_error_message() );
			return $response->get_error_message();
		}

		$code         = (int) wp_remote_retrieve_response_code( $response );
		$response_raw = wp_remote_retrieve_body( $response );

		if ( 200 === $code ) {
			return true;
		}

		// FCM v1 reports unregistered/invalid tokens as HTTP 404 with a specific error code.
		$decoded = json_decode( $response_raw, true );
		$status  = $decoded['error']['status'] ?? '';
		if ( in_array( $status, array( 'UNREGISTERED', 'INVALID_ARGUMENT' ), true ) ) {
			return 'invalid_token';
		}

		$this->logger->warning( "FCM dispatch HTTP {$code}: {$response_raw}" );
		return "http_{$code}";
	}

	/**
	 * Obtain a valid OAuth 2.0 access token for the FCM scope.
	 *
	 * Tokens are cached in memory for the lifetime of the request. A new JWT
	 * is signed and exchanged for a bearer token when the cache is empty or
	 * within 60 seconds of expiry.
	 *
	 * @param array<string, mixed> $service_account Decoded service account JSON.
	 * @return string Access token, or empty string on failure.
	 */
	private function getAccessToken( array $service_account ): string {
		if ( '' !== $this->access_token && time() < ( $this->access_token_expiry - 60 ) ) {
			return $this->access_token;
		}

		$client_email = $service_account['client_email'] ?? '';
		$private_key  = $service_account['private_key'] ?? '';
		$token_uri    = $service_account['token_uri'] ?? 'https://oauth2.googleapis.com/token';

		if ( '' === $client_email || '' === $private_key ) {
			$this->logger->warning( 'FCM service account JSON is missing required fields.' );
			return '';
		}

		$jwt = $this->buildJwt( $client_email, $private_key );
		if ( '' === $jwt ) {
			return '';
		}

		$response = wp_remote_post(
			$token_uri,
			array(
				'body'    => array(
					'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
					'assertion'  => $jwt,
				),
				'timeout' => 10,
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->logger->warning( 'FCM token exchange wp_error: ' . $response->get_error_message() );
			return '';
		}

		$decoded = json_decode( wp_remote_retrieve_body( $response ), true );
		$token   = $decoded['access_token'] ?? '';
		$expires = (int) ( $decoded['expires_in'] ?? 3600 );

		if ( '' === $token ) {
			$this->logger->warning( 'FCM token exchange failed: ' . wp_remote_retrieve_body( $response ) );
			return '';
		}

		$this->access_token        = $token;
		$this->access_token_expiry = time() + $expires;

		return $token;
	}

	/**
	 * Build and sign a JWT for the service-account OAuth 2.0 flow.
	 *
	 * @param string $client_email Service account email address.
	 * @param string $private_key  PEM-encoded RSA private key.
	 * @return string Signed JWT, or empty string on failure.
	 */
	private function buildJwt( string $client_email, string $private_key ): string {
		$now = time();

		$header  = $this->base64UrlEncode( (string) wp_json_encode( array( 'alg' => 'RS256', 'typ' => 'JWT' ) ) );
		$payload = $this->base64UrlEncode(
			(string) wp_json_encode(
				array(
					'iss'   => $client_email,
					'sub'   => $client_email,
					'aud'   => 'https://oauth2.googleapis.com/token',
					'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
					'iat'   => $now,
					'exp'   => $now + 3600,
				)
			)
		);

		$signing_input = $header . '.' . $payload;
		$key           = openssl_pkey_get_private( $private_key );
		if ( false === $key ) {
			$this->logger->warning( 'FCM: failed to load private key from service account JSON.' );
			return '';
		}

		$signature = '';
		$signed    = openssl_sign( $signing_input, $signature, $key, OPENSSL_ALGO_SHA256 );

		if ( ! $signed ) {
			$this->logger->warning( 'FCM: failed to sign JWT.' );
			return '';
		}

		return $signing_input . '.' . $this->base64UrlEncode( $signature );
	}

	/**
	 * URL-safe base64 encoding without padding (as required by JWT spec).
	 *
	 * @param string $data Binary or text data to encode.
	 * @return string URL-safe base64 string without trailing `=` characters.
	 */
	private function base64UrlEncode( string $data ): string {
		return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
	}

	/**
	 * Remove stale tokens reported by FCM as invalid or unregistered.
	 *
	 * @param int      $user_id WordPress user ID.
	 * @param string[] $stale   Token strings to remove.
	 * @return void
	 */
	private function removeStaleTokens( int $user_id, array $stale ): void {
		$all_tokens              = $this->loadAllTokens();
		$user_tokens             = $all_tokens[ $user_id ] ?? array();
		$user_tokens             = array_values( array_filter( $user_tokens, fn( $t ) => ! in_array( $t, $stale, true ) ) );
		$all_tokens[ $user_id ] = $user_tokens;
		$this->saveAllTokens( $all_tokens );
	}

	/**
	 * Return all registered tokens for a user.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return string[] Token strings.
	 */
	private function getTokensForUser( int $user_id ): array {
		$all = $this->loadAllTokens();
		return $all[ $user_id ] ?? array();
	}

	/**
	 * Load the full token map from the network option.
	 *
	 * @return array<int, string[]>
	 */
	private function loadAllTokens(): array {
		$raw = get_site_option( self::OPTION_FCM_TOKENS, '{}' );
		$map = json_decode( (string) $raw, true );
		return is_array( $map ) ? $map : array();
	}

	/**
	 * Persist the full token map to the network option.
	 *
	 * @param array<int, string[]> $tokens Token map.
	 * @return void
	 */
	private function saveAllTokens( array $tokens ): void {
		update_site_option( self::OPTION_FCM_TOKENS, wp_json_encode( $tokens ) );
	}

	/**
	 * Return the decoded service-account JSON from the network option.
	 *
	 * @return array<string, mixed> Decoded JSON, or empty array if not configured.
	 */
	private function getServiceAccount(): array {
		$raw = (string) get_site_option( self::OPTION_FCM_SERVICE_ACCOUNT, '' );
		if ( '' === $raw ) {
			return array();
		}
		$decoded = json_decode( $raw, true );
		return is_array( $decoded ) ? $decoded : array();
	}
}
