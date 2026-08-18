<?php
/**
 * @package WP Helpdesk
 */

namespace WPHelpdesk\Domain\Session;

use WPHelpdesk\Support\Helpers;

class SubmissionSessionService {

	/** Default session lifetime in minutes. */
	public const DEFAULT_TTL_MINUTES = 60;

	protected SubmissionSessionRepository $repository;
	protected int $network_id;

	public function __construct() {
		$this->repository = new SubmissionSessionRepository();
		$this->network_id = Helpers::getNetworkId();
	}

	/**
	 * Start a new submission session.
	 *
	 * @param string               $form_type       'guest' or 'member'.
	 * @param array<string, mixed> $initial_payload Initial payload data.
	 * @param int|null             $user_id         Authenticated user id, or null for guests.
	 * @param int                  $site_id         Site id.
	 * @param int                  $ttl_minutes     Session lifetime in minutes.
	 * @return array<string, mixed>|null Created session row, or null on failure.
	 */
	public function start(
		string $form_type = 'guest',
		array $initial_payload = [],
		?int $user_id = null,
		int $site_id = 1,
		int $ttl_minutes = self::DEFAULT_TTL_MINUTES
	): ?array {
		$token   = $this->generateToken();
		$now     = current_time( 'mysql' );
		$expires = date( 'Y-m-d H:i:s', strtotime( "+{$ttl_minutes} minutes", strtotime( $now ) ) );

		$id = $this->repository->create(
			[
				'network_id'       => $this->network_id,
				'site_id'          => $site_id,
				'session_token'    => $token,
				'user_id'          => $user_id,
				'form_type'        => in_array( $form_type, [ 'guest', 'member' ], true ) ? $form_type : 'guest',
				'current_topic_id' => $initial_payload['topic_id'] ?? null,
				'step_index'       => 0,
				'reset_counter'    => 0,
				'payload_json'     => wp_json_encode( $initial_payload ),
				'expires_at'       => $expires,
				'created_at'       => $now,
				'updated_at'       => $now,
			]
		);

		if ( 0 === $id ) {
			return null;
		}

		return $this->repository->find( $id );
	}

	/**
	 * Resume a session by token, returning null if missing or expired.
	 *
	 * @param string $token Session token.
	 * @return array<string, mixed>|null
	 */
	public function resume( string $token ): ?array {
		$session = $this->repository->findByToken( $token );

		if ( ! $session ) {
			return null;
		}

		if ( strtotime( (string) $session['expires_at'] ) < strtotime( current_time( 'mysql' ) ) ) {
			// Expired – clean it up.
			$this->repository->deleteByToken( $token );

			return null;
		}

		return $session;
	}

	/**
	 * Advance a session to the next step, updating payload and topic.
	 *
	 * When $reset_counter is provided, it must match the session's stored
	 * reset_counter; a mismatch means the client holds state from before the
	 * last reset and the write is rejected to prevent stale rehydration.
	 *
	 * @param string               $token         Session token.
	 * @param int                  $step          New step index.
	 * @param int|null             $topic_id      Current topic id.
	 * @param array<string, mixed> $payload       Merged payload data.
	 * @param int                  $ttl_minutes   Extend TTL on each save.
	 * @param int|null             $reset_counter Client-supplied reset counter for staleness check.
	 * @return bool
	 */
	public function advance(
		string $token,
		int $step,
		?int $topic_id,
		array $payload,
		int $ttl_minutes = self::DEFAULT_TTL_MINUTES,
		?int $reset_counter = null
	): bool {
		$session = $this->resume( $token );
		if ( ! $session ) {
			return false;
		}

		// Reject the write if the client's reset_counter is stale.
		if ( null !== $reset_counter && (int) ( $session['reset_counter'] ?? 0 ) !== $reset_counter ) {
			return false;
		}

		$existing_payload = [];
		if ( ! empty( $session['payload_json'] ) ) {
			$existing_payload = (array) json_decode( (string) $session['payload_json'], true );
		}

		$merged  = array_merge( $existing_payload, $payload );
		$now     = current_time( 'mysql' );
		$expires = date( 'Y-m-d H:i:s', strtotime( "+{$ttl_minutes} minutes", strtotime( $now ) ) );

		return $this->repository->updateByToken(
			$token,
			[
				'step_index'       => max( 0, $step ),
				'current_topic_id' => $topic_id,
				'payload_json'     => wp_json_encode( $merged ),
				'expires_at'       => $expires,
				'updated_at'       => $now,
			]
		);
	}

	/**
	 * Restart a session: reset to step 0 and clear all branch-dependent state.
	 *
	 * Increments reset_counter so any in-flight upsert with the old counter
	 * will be rejected, preventing stale state from writing back after reset.
	 *
	 * Preserves the session token, user_id, form_type, network_id, site_id, and
	 * expiry so the browser session remains valid, but wipes topic selection,
	 * step progress, and accumulated payload so the user can choose a fresh topic.
	 *
	 * @param string $token       Session token.
	 * @param int    $ttl_minutes Extend TTL on restart.
	 * @return int|false New reset_counter on success, false when the session does not exist or is already expired.
	 */
	public function restart( string $token, int $ttl_minutes = self::DEFAULT_TTL_MINUTES ): int|false {
		$session = $this->resume( $token );
		if ( ! $session ) {
			return false;
		}

		$new_counter = (int) ( $session['reset_counter'] ?? 0 ) + 1;
		$now         = current_time( 'mysql' );
		$expires     = date( 'Y-m-d H:i:s', strtotime( "+{$ttl_minutes} minutes", strtotime( $now ) ) );

		$ok = $this->repository->updateByToken(
			$token,
			[
				'step_index'       => 0,
				'reset_counter'    => $new_counter,
				'current_topic_id' => null,
				'payload_json'     => wp_json_encode( [] ),
				'expires_at'       => $expires,
				'updated_at'       => $now,
			]
		);

		return $ok ? $new_counter : false;
	}

	/**
	 * Destroy a session.
	 *
	 * @param string $token Session token.
	 * @return bool
	 */
	public function destroy( string $token ): bool {
		return $this->repository->deleteByToken( $token );
	}

	/**
	 * Decode the payload from a session row.
	 *
	 * @param array<string, mixed> $session Session row.
	 * @return array<string, mixed>
	 */
	public function getPayload( array $session ): array {
		if ( empty( $session['payload_json'] ) ) {
			return [];
		}

		$decoded = json_decode( (string) $session['payload_json'], true );

		return is_array( $decoded ) ? $decoded : [];
	}

	/**
	 * Run the cleanup job to delete all expired sessions.
	 *
	 * @return int Number of deleted sessions.
	 */
	public function cleanupExpired(): int {
		return $this->repository->deleteExpired();
	}

	/**
	 * Generate a cryptographically secure session token.
	 *
	 * @return string
	 */
	protected function generateToken(): string {
		return bin2hex( random_bytes( 32 ) );
	}
}
