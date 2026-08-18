<?php
/**
 * @package WP Helpdesk
 */

namespace WPHelpdesk\Infrastructure\Security;

use WPHelpdesk\Infrastructure\Database\Schema;
use WPHelpdesk\Support\Constants;

class RateLimiter {
	/**
	 * Increment a rate-limit bucket and determine whether it is still allowed.
	 *
	 * @param string $key            Unique key for the bucket.
	 * @param int    $max_hits       Maximum hits allowed.
	 * @param int    $window_seconds Window size in seconds.
	 * @return bool
	 */
	public function checkAndIncrement( string $key, int $max_hits, int $window_seconds ): bool {
		global $wpdb;

		$table      = Schema::table( Constants::TABLE_RATE_LIMITS );
		$key_hash   = hash( 'sha256', $key );
		$row        = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, window_start, hits FROM {$table} WHERE key_hash = %s LIMIT 1",
				$key_hash
			),
			ARRAY_A
		);
		$now        = current_time( 'timestamp', true );
		$window_gmt = gmdate( 'Y-m-d H:i:s', $now );

		if ( empty( $row ) ) {
			$wpdb->insert(
				$table,
				array(
					'key_hash'     => $key_hash,
					'window_start' => $window_gmt,
					'hits'         => 1,
				),
				array( '%s', '%s', '%d' )
			);

			return true;
		}

		$window_start = strtotime( (string) $row['window_start'] . ' UTC' );
		if ( false === $window_start || ( $window_start + $window_seconds ) <= $now ) {
			$wpdb->update(
				$table,
				array(
					'window_start' => $window_gmt,
					'hits'         => 1,
				),
				array( 'id' => (int) $row['id'] ),
				array( '%s', '%d' ),
				array( '%d' )
			);

			return true;
		}

		if ( (int) $row['hits'] >= $max_hits ) {
			return false;
		}

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET hits = hits + 1 WHERE id = %d",
				(int) $row['id']
			)
		);

		return true;
	}
}
