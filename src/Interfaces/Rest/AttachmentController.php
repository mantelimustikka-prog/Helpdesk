<?php
/**
 * @package WP Helpdesk
 */

namespace WPHelpdesk\Interfaces\Rest;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WPHelpdesk\Domain\Attachment\AttachmentService;

class AttachmentController {
	protected AttachmentService $attachment_service;

	public function __construct( AttachmentService $attachment_service ) {
		$this->attachment_service = $attachment_service;
	}

	/**
	 * Upload a ticket attachment.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function upload( WP_REST_Request $request ): WP_REST_Response {
		$ticket_id = (int) $request['id'];
		$message_id = $request->get_param( 'message_id' );
		$message_id = null !== $message_id ? (int) $message_id : null;

		if ( empty( $_FILES['file'] ) ) {
			return new WP_REST_Response( array( 'message' => 'No attachment uploaded.' ), 400 );
		}

		$result = $this->attachment_service->handleUpload(
			$_FILES['file'],
			$ticket_id,
			$message_id,
			get_current_user_id()
		);

		if ( $result instanceof WP_Error ) {
			return new WP_REST_Response(
				array(
					'message' => $result->get_error_message(),
					'code'    => $result->get_error_code(),
				),
				400
			);
		}

		return new WP_REST_Response( $result, 201 );
	}

	/**
	 * List ticket attachments.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function list( WP_REST_Request $request ): WP_REST_Response {
		return new WP_REST_Response(
			array(
				'items' => $this->attachment_service->getForTicket( (int) $request['id'] ),
			)
		);
	}
}
