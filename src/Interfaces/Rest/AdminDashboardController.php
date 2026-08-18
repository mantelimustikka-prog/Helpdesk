<?php
/**
 * @package WP Helpdesk
 */

namespace WPHelpdesk\Interfaces\Rest;

use WP_REST_Request;
use WP_REST_Response;
use WPHelpdesk\Infrastructure\Database\Schema;
use WPHelpdesk\Support\Constants;
use WPHelpdesk\Support\Helpers;

class AdminDashboardController {
	/**
	 * Summarize tickets by status.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function summary( WP_REST_Request $request ): WP_REST_Response {
		global $wpdb;

		$table      = Schema::table( Constants::TABLE_TICKETS );
		$network_id = Helpers::getNetworkId();
		$results    = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT status, COUNT(*) AS total FROM {$table} WHERE network_id = %d GROUP BY status",
				$network_id
			),
			ARRAY_A
		);

		$summary = array();
		foreach ( $results as $row ) {
			$summary[ $row['status'] ] = (int) $row['total'];
		}

		return new WP_REST_Response( $summary );
	}
}
