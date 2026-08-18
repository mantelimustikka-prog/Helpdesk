<?php
/**
 * @package WP Helpdesk
 */

namespace WPHelpdesk\Domain\Attachment;

use WP_Error;
use WPHelpdesk\Infrastructure\Database\Schema;
use WPHelpdesk\Support\Constants;

class AttachmentService {
	public const ALLOWED_MIME_TYPES = array(
		'image/jpeg',
		'image/png',
		'image/gif',
		'application/pdf',
		'text/plain',
		'application/zip',
	);

	public const MAX_FILE_SIZE = 10485760;

	/**
	 * Validate and store an uploaded attachment.
	 *
	 * @param array<string, mixed> $file       Uploaded file array.
	 * @param int                  $ticket_id  Ticket ID.
	 * @param int|null             $message_id Message ID.
	 * @param int                  $user_id    Uploading user ID.
	 * @return array<string, mixed>|WP_Error
	 */
	public function handleUpload( array $file, int $ticket_id, ?int $message_id, int $user_id ) {
		global $wpdb;

		if ( empty( $file['size'] ) || (int) $file['size'] > self::MAX_FILE_SIZE ) {
			return new WP_Error( 'hd_attachment_size', 'Attachment exceeds the 10MB upload limit.' );
		}

		$detected_type = wp_check_filetype_and_ext( (string) $file['tmp_name'], (string) $file['name'] );
		$mime_type     = (string) ( $detected_type['type'] ?? '' );

		if ( ! in_array( $mime_type, self::ALLOWED_MIME_TYPES, true ) ) {
			return new WP_Error( 'hd_attachment_type', 'Attachment type is not allowed.' );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$upload = wp_handle_upload(
			$file,
			array(
				'test_form' => false,
			)
		);

		if ( isset( $upload['error'] ) ) {
			return new WP_Error( 'hd_attachment_upload', (string) $upload['error'] );
		}

		$attachment_id = wp_insert_attachment(
			array(
				'post_mime_type' => $mime_type,
				'post_title'     => sanitize_file_name( (string) $file['name'] ),
				'post_content'   => '',
				'post_status'    => 'inherit',
			),
			(string) $upload['file']
		);

		if ( is_wp_error( $attachment_id ) ) {
			return $attachment_id;
		}

		wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, (string) $upload['file'] ) );

		$table = Schema::table( Constants::TABLE_ATTACHMENTS );
		$wpdb->insert(
			$table,
			array(
				'ticket_id'         => $ticket_id,
				'message_id'        => $message_id,
				'wp_attachment_id'  => $attachment_id,
				'uploaded_by'       => $user_id,
				'mime_type'         => $mime_type,
				'file_size'         => (int) $file['size'],
				'created_at'        => current_time( 'mysql', true ),
			),
			array( '%d', '%d', '%d', '%d', '%s', '%d', '%s' )
		);

		return array(
			'id'               => (int) $wpdb->insert_id,
			'ticket_id'        => $ticket_id,
			'message_id'       => $message_id,
			'wp_attachment_id' => (int) $attachment_id,
			'mime_type'        => $mime_type,
			'file_size'        => (int) $file['size'],
			'url'              => wp_get_attachment_url( $attachment_id ),
		);
	}

	/**
	 * Get attachments for a ticket.
	 *
	 * @param int $ticket_id Ticket ID.
	 * @return array<int, array<string, mixed>>
	 */
	public function getForTicket( int $ticket_id ): array {
		global $wpdb;

		$table = Schema::table( Constants::TABLE_ATTACHMENTS );
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE ticket_id = %d ORDER BY created_at DESC",
				$ticket_id
			),
			ARRAY_A
		);

		foreach ( $rows as &$row ) {
			$row['url'] = wp_get_attachment_url( (int) $row['wp_attachment_id'] );
		}

		return $rows;
	}

	/**
	 * Delete an attachment owned by a user or managed by an agent.
	 *
	 * @param int $attachment_id Attachment record ID.
	 * @param int $user_id       Current user ID.
	 * @return bool
	 */
	public function delete( int $attachment_id, int $user_id ): bool {
		global $wpdb;

		$table      = Schema::table( Constants::TABLE_ATTACHMENTS );
		$attachment = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d LIMIT 1",
				$attachment_id
			),
			ARRAY_A
		);

		if ( empty( $attachment ) ) {
			return false;
		}

		if ( (int) $attachment['uploaded_by'] !== $user_id && ! current_user_can( 'hd_manage_tickets' ) ) {
			return false;
		}

		wp_delete_attachment( (int) $attachment['wp_attachment_id'], true );
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE id = %d",
				$attachment_id
			)
		);

		return true;
	}
}
