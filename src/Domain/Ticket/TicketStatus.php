<?php
/**
 * @package WP Helpdesk
 */

namespace WPHelpdesk\Domain\Ticket;

class TicketStatus {
	public const CANONICAL_NEW                  = 'new';
	public const CANONICAL_PENDING_AGENT_REPLY  = 'pending_agent_reply';
	public const CANONICAL_PENDING_CLIENT_REPLY = 'pending_client_reply';
	public const CANONICAL_RESOLVED             = 'resolved';
	public const CANONICAL_CLOSED               = 'closed';

	/**
	 * @return array<int, string>
	 */
	public static function canonicalValues(): array {
		return array(
			self::CANONICAL_NEW,
			self::CANONICAL_PENDING_AGENT_REPLY,
			self::CANONICAL_PENDING_CLIENT_REPLY,
			self::CANONICAL_RESOLVED,
			self::CANONICAL_CLOSED,
		);
	}

	/**
	 * @param string $status Raw status.
	 * @return bool
	 */
	public static function isCanonical( string $status ): bool {
		return in_array( $status, self::canonicalValues(), true );
	}

	/**
	 * Convert DB/storage status to canonical status.
	 *
	 * @param string $status Storage status.
	 * @return string
	 */
	public static function toCanonical( string $status ): string {
		$resolved = self::tryCanonical( $status );
		return null !== $resolved ? $resolved : self::CANONICAL_NEW;
	}

	/**
	 * Attempt to resolve canonical status from canonical/storage/legacy values.
	 *
	 * @param string $status Raw status.
	 * @return string|null
	 */
	public static function tryCanonical( string $status ): ?string {
		$status = sanitize_key( $status );

		if ( self::isCanonical( $status ) ) {
			return $status;
		}

		$map = array(
			'triaged'          => self::CANONICAL_PENDING_AGENT_REPLY,
			'in_progress'      => self::CANONICAL_PENDING_AGENT_REPLY,
			'pending'          => self::CANONICAL_PENDING_AGENT_REPLY,
			'waiting_customer' => self::CANONICAL_PENDING_CLIENT_REPLY,
			'open'             => self::CANONICAL_NEW,
		);

		return $map[ $status ] ?? null;
	}

	/**
	 * Convert canonical status to DB/storage status.
	 *
	 * @param string $status Canonical status.
	 * @return string
	 */
	public static function toStorage( string $status ): string {
		$status = self::toCanonical( $status );
		$map    = array(
			self::CANONICAL_NEW                  => 'new',
			self::CANONICAL_PENDING_AGENT_REPLY  => 'in_progress',
			self::CANONICAL_PENDING_CLIENT_REPLY => 'waiting_customer',
			self::CANONICAL_RESOLVED             => 'resolved',
			self::CANONICAL_CLOSED               => 'closed',
		);

		return $map[ $status ] ?? 'new';
	}

	/**
	 * @param string $canonical Canonical status.
	 * @return array<int, string>
	 */
	public static function storageValuesForCanonical( string $canonical ): array {
		$canonical = self::toCanonical( $canonical );
		if ( self::CANONICAL_PENDING_AGENT_REPLY === $canonical ) {
			return array( 'in_progress', 'triaged', 'pending' );
		}
		if ( self::CANONICAL_PENDING_CLIENT_REPLY === $canonical ) {
			return array( 'waiting_customer' );
		}

		return array( self::toStorage( $canonical ) );
	}

	/**
	 * @param string $status Canonical or storage status.
	 * @return string
	 */
	public static function label( string $status ): string {
		$canonical = self::toCanonical( $status );
		$labels    = array(
			self::CANONICAL_NEW                  => __( 'New', 'wp-helpdesk' ),
			self::CANONICAL_PENDING_AGENT_REPLY  => __( 'Pending Agent reply', 'wp-helpdesk' ),
			self::CANONICAL_PENDING_CLIENT_REPLY => __( 'Pending Client reply', 'wp-helpdesk' ),
			self::CANONICAL_RESOLVED             => __( 'Resolved', 'wp-helpdesk' ),
			self::CANONICAL_CLOSED               => __( 'Closed', 'wp-helpdesk' ),
		);

		return $labels[ $canonical ] ?? $labels[ self::CANONICAL_NEW ];
	}
}
