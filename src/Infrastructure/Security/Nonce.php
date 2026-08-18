<?php
/**
 * @package WP Helpdesk
 */

namespace WPHelpdesk\Infrastructure\Security;

class Nonce {
	/**
	 * Create a nonce.
	 *
	 * @param string $action Nonce action.
	 * @return string
	 */
	public static function create( string $action ): string {
		return wp_create_nonce( $action );
	}

	/**
	 * Verify a nonce string.
	 *
	 * @param string $nonce  Raw nonce.
	 * @param string $action Expected action.
	 * @return bool
	 */
	public static function verify( string $nonce, string $action ): bool {
		return (bool) wp_verify_nonce( $nonce, $action );
	}
}
