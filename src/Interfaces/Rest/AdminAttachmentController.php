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

class AdminAttachmentController extends AdminApiController {
	/**
	 * Get a single attachment by ID, scoped to the current network.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function getAttachment( WP_REST_Request $request ): WP_REST_Response {
		global $wpdb;

		$attachment_id = (int) $request['id'];
		$network_id    = Helpers::getNetworkId();
		$att_table     = Schema::table( Constants::TABLE_ATTACHMENTS );
		$ticket_table  = Schema::table( Constants::TABLE_TICKETS );

		$attachment = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT a.* FROM {$att_table} a
				 INNER JOIN {$ticket_table} t ON t.id = a.ticket_id
				 WHERE a.id = %d AND t.network_id = %d
				 LIMIT 1",
				$attachment_id,
				$network_id
			),
			ARRAY_A
		);

		if ( empty( $attachment ) ) {
			return $this->error( 'not_found', 'Attachment not found.', 404 );
		}

		return $this->success( $attachment );
	}
}
