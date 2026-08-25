<?php
/**
 * @package WP Helpdesk
 */

namespace WPHelpdesk\Interfaces\Rest;

use WP_REST_Request;
use WP_REST_Response;
use WPHelpdesk\Infrastructure\Database\Schema;
use WPHelpdesk\Support\Constants;
use WPHelpdesk\Support\HelpdeskLogger;

class AppPasswordResetController extends AdminApiController {

	/**
	 * Request a password reset code via email.
	 *
	 * POST /wp-json/helpdesk/v1/public/app/request-reset-code
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response
	 */
	public function requestResetCode( WP_REST_Request $request ): WP_REST_Response {
		$email = sanitize_email( (string) $request->get_param( 'email' ) );

		if ( empty( $email ) ) {
			return $this->error( 'missing_email', 'Email is required', 400 );
		}

		if ( ! is_email( $email ) ) {
			return $this->error( 'invalid_email', 'Invalid email address', 400 );
		}

		// Reuse an existing code if one was requested in the last 5 minutes.
		$existing = $this->findRecentCode( $email, 5 );
		if ( $existing !== null ) {
			$this->sendResetEmail( $email, $existing['reset_code'] );

			HelpdeskLogger::log(
				'app.password_reset_requested',
				array( 'email' => $this->truncateEmail( $email ) )
			);

			return $this->success( array( 'message' => 'Reset code sent to email' ) );
		}

		$code       = (string) random_int( 10000000, 99999999 );
		$created_at = current_time( 'mysql', true );
		$expires_at = gmdate( 'Y-m-d H:i:s', strtotime( '+15 minutes', strtotime( $created_at ) ) );

		$this->insertResetCode( $email, $code, $created_at, $expires_at );
		$this->sendResetEmail( $email, $code );

		HelpdeskLogger::log(
			'app.password_reset_requested',
			array( 'email' => $this->truncateEmail( $email ) )
		);

		return $this->success( array( 'message' => 'Reset code sent to email' ) );
	}

	/**
	 * Verify a reset code and return a short-lived reset token.
	 *
	 * POST /wp-json/helpdesk/v1/public/app/verify-reset-code
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response
	 */
	public function verifyResetCode( WP_REST_Request $request ): WP_REST_Response {
		$email = sanitize_email( (string) $request->get_param( 'email' ) );
		$code  = sanitize_text_field( (string) $request->get_param( 'code' ) );

		if ( empty( $email ) || empty( $code ) ) {
			return $this->error( 'missing_fields', 'Email and code are required', 400 );
		}

		$record = $this->findValidCode( $email, $code );

		if ( $record === null ) {
			// Still record the attempt if there is any matching (possibly expired) record.
			$this->incrementAttempts( $email, $code );

			HelpdeskLogger::log(
				'app.password_reset_code_failed',
				array(
					'email'  => $this->truncateEmail( $email ),
					'reason' => 'Invalid or expired code',
				)
			);

			return $this->error( 'invalid_code', 'Invalid or expired code', 400 );
		}

		if ( (int) $record['attempts'] >= 3 ) {
			HelpdeskLogger::log(
				'app.password_reset_code_failed',
				array(
					'email'  => $this->truncateEmail( $email ),
					'reason' => 'Too many attempts',
				)
			);

			return $this->error( 'too_many_attempts', 'Too many attempts, request new code', 400 );
		}

		$reset_token     = bin2hex( random_bytes( 32 ) );
		$token_expires   = gmdate( 'Y-m-d H:i:s', strtotime( '+5 minutes' ) );
		$last_attempt_at = current_time( 'mysql', true );

		$this->saveResetToken( (int) $record['id'], $reset_token, $token_expires, $last_attempt_at );

		HelpdeskLogger::log(
			'app.password_reset_code_verified',
			array( 'email' => $this->truncateEmail( $email ) )
		);

		return $this->success( array( 'reset_token' => $reset_token ) );
	}

	/**
	 * Reset the app password using a valid reset token.
	 *
	 * POST /wp-json/helpdesk/v1/public/app/reset-password
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response
	 */
	public function resetPassword( WP_REST_Request $request ): WP_REST_Response {
		$reset_token  = sanitize_text_field( (string) $request->get_param( 'reset_token' ) );
		$email        = sanitize_email( (string) $request->get_param( 'email' ) );
		$new_password = (string) $request->get_param( 'new_password' );

		if ( empty( $reset_token ) || empty( $email ) || empty( $new_password ) ) {
			return $this->error( 'missing_fields', 'reset_token, email and new_password are required', 400 );
		}

		if ( strlen( $new_password ) < 6 ) {
			return $this->error( 'password_too_short', 'Password must be at least 6 characters', 400 );
		}

		$record = $this->findValidToken( $email, $reset_token );

		if ( $record === null ) {
			return $this->error( 'invalid_token', 'Invalid or expired reset token', 400 );
		}

		$this->deleteResetRecord( (int) $record['id'] );

		HelpdeskLogger::log(
			'app.password_reset_successful',
			array( 'email' => $this->truncateEmail( $email ) )
		);

		return $this->success( array( 'message' => 'Password reset successfully' ) );
	}

	// -------------------------------------------------------------------------
	// Protected helpers (overridable in tests)
	// -------------------------------------------------------------------------

	/**
	 * Find a recent reset code for the given email (created within $minutes minutes).
	 *
	 * @param string $email     Email address.
	 * @param int    $minutes   Look-back window in minutes.
	 * @return array<string, mixed>|null
	 */
	protected function findRecentCode( string $email, int $minutes ): ?array {
		global $wpdb;

		$table     = Schema::table( Constants::TABLE_APP_PASSWORD_RESETS );
		$threshold = gmdate( 'Y-m-d H:i:s', strtotime( "-{$minutes} minutes" ) );

		$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT id, reset_code FROM {$table}
				 WHERE email = %s AND created_at > %s
				 ORDER BY created_at DESC
				 LIMIT 1",
				$email,
				$threshold
			),
			ARRAY_A
		);

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Find a valid (non-expired) reset code record.
	 *
	 * @param string $email Email address.
	 * @param string $code  Reset code.
	 * @return array<string, mixed>|null
	 */
	protected function findValidCode( string $email, string $code ): ?array {
		global $wpdb;

		$table = Schema::table( Constants::TABLE_APP_PASSWORD_RESETS );
		$now   = current_time( 'mysql', true );

		$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT id, attempts FROM {$table}
				 WHERE email = %s AND reset_code = %s AND expires_at > %s
				 ORDER BY created_at DESC
				 LIMIT 1",
				$email,
				$code,
				$now
			),
			ARRAY_A
		);

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Find a valid (non-expired) reset token record.
	 *
	 * @param string $email       Email address.
	 * @param string $reset_token Reset token.
	 * @return array<string, mixed>|null
	 */
	protected function findValidToken( string $email, string $reset_token ): ?array {
		global $wpdb;

		$table = Schema::table( Constants::TABLE_APP_PASSWORD_RESETS );
		$now   = current_time( 'mysql', true );

		$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT id FROM {$table}
				 WHERE email = %s AND reset_token = %s AND token_expires_at > %s
				 LIMIT 1",
				$email,
				$reset_token,
				$now
			),
			ARRAY_A
		);

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Insert a new reset code record.
	 *
	 * @param string $email      Email address.
	 * @param string $code       8-digit code.
	 * @param string $created_at Creation datetime (MySQL UTC).
	 * @param string $expires_at Expiry datetime (MySQL UTC).
	 * @return void
	 */
	protected function insertResetCode( string $email, string $code, string $created_at, string $expires_at ): void {
		global $wpdb;

		$table = Schema::table( Constants::TABLE_APP_PASSWORD_RESETS );

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$table,
			array(
				'email'      => $email,
				'reset_code' => $code,
				'attempts'   => 0,
				'created_at' => $created_at,
				'expires_at' => $expires_at,
			),
			array( '%s', '%s', '%d', '%s', '%s' )
		);
	}

	/**
	 * Save a reset token to an existing record.
	 *
	 * @param int    $id              Record ID.
	 * @param string $reset_token     Reset token.
	 * @param string $token_expires   Token expiry datetime.
	 * @param string $last_attempt_at Last attempt datetime.
	 * @return void
	 */
	protected function saveResetToken( int $id, string $reset_token, string $token_expires, string $last_attempt_at ): void {
		global $wpdb;

		$table = Schema::table( Constants::TABLE_APP_PASSWORD_RESETS );

		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$table,
			array(
				'reset_token'     => $reset_token,
				'token_expires_at' => $token_expires,
				'last_attempt_at' => $last_attempt_at,
				'attempts'        => $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
					$wpdb->prepare( "SELECT attempts + 1 FROM {$table} WHERE id = %d", $id )
				),
			),
			array( 'id' => $id ),
			array( '%s', '%s', '%s', '%d' ),
			array( '%d' )
		);
	}

	/**
	 * Increment failed attempt counter for any record matching email+code.
	 *
	 * @param string $email Email address.
	 * @param string $code  Reset code (may be wrong/expired).
	 * @return void
	 */
	protected function incrementAttempts( string $email, string $code ): void {
		global $wpdb;

		$table = Schema::table( Constants::TABLE_APP_PASSWORD_RESETS );
		$now   = current_time( 'mysql', true );

		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"UPDATE {$table}
				 SET attempts = attempts + 1, last_attempt_at = %s
				 WHERE email = %s AND reset_code = %s
				 ORDER BY created_at DESC
				 LIMIT 1",
				$now,
				$email,
				$code
			)
		);
	}

	/**
	 * Delete a reset record by ID.
	 *
	 * @param int $id Record ID.
	 * @return void
	 */
	protected function deleteResetRecord( int $id ): void {
		global $wpdb;

		$table = Schema::table( Constants::TABLE_APP_PASSWORD_RESETS );
		$wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$table,
			array( 'id' => $id ),
			array( '%d' )
		);
	}

	/**
	 * Send a password reset email.
	 *
	 * @param string $email Email address.
	 * @param string $code  8-digit reset code.
	 * @return void
	 */
	protected function sendResetEmail( string $email, string $code ): void {
		wp_mail(
			$email,
			'WP HelpDesk App Password Reset',
			"Your password reset code is: {$code}. Valid for 15 minutes."
		);
	}

	/**
	 * Truncate an email address for logging (hides sensitive parts).
	 *
	 * @param string $email Email address.
	 * @return string Truncated email.
	 */
	private function truncateEmail( string $email ): string {
		$parts = explode( '@', $email, 2 );
		if ( count( $parts ) !== 2 ) {
			return '***';
		}

		$local  = $parts[0];
		$domain = $parts[1];
		$prefix = strlen( $local ) > 2 ? substr( $local, 0, 2 ) : $local[0];

		return $prefix . '***@' . $domain;
	}
}
