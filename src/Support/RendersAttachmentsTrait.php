<?php
/**
 * @package WP Helpdesk
 */

namespace WPHelpdesk\Support;

/**
 * Shared attachment gallery rendering logic.
 *
 * Used by both the admin TicketsPage and the customer-facing GuestTicketView
 * so that future UI changes only need to be made in one place.
 */
trait RendersAttachmentsTrait {

	/**
	 * Render the attachment gallery.
	 *
	 * Images are shown as clickable thumbnails that open a lightbox modal.
	 * Documents (PDF, Word, Excel, ZIP, …) show Open and Download action links.
	 *
	 * @param array<int, array<string, mixed>> $attachments Attachment rows from AttachmentService.
	 * @return void
	 */
	public function renderAttachments( array $attachments ): void {
		if ( empty( $attachments ) ) {
			return;
		}
		echo '<div class="hd-attachments">';
		foreach ( $attachments as $att ) {
			$mime     = (string) ( $att['mime_type'] ?? '' );
			$url      = (string) ( $att['url'] ?? '' );
			$name     = (string) ( $att['file_name'] ?? basename( $url ) );
			$size_raw = (int) ( $att['file_size'] ?? 0 );
			$size_fmt = $size_raw > 0 ? size_format( $size_raw ) : '';
			$date     = (string) ( $att['created_at'] ?? '' );
			$is_image = 0 === strpos( $mime, 'image/' );

			echo '<div class="hd-attachment ' . esc_attr( $is_image ? 'hd-attachment--image' : 'hd-attachment--document' ) . '">';

			if ( $is_image ) {
				echo '<button type="button" class="hd-attachment__thumb-btn"'
					. ' data-lightbox-src="' . esc_url( $url ) . '"'
					. ' data-lightbox-alt="' . esc_attr( $name ) . '"'
					. ' aria-label="' . esc_attr( sprintf( __( 'View image: %s', 'wp-helpdesk' ), $name ) ) . '">'
					. '<img class="hd-attachment__thumb" src="' . esc_url( $url ) . '" alt="' . esc_attr( $name ) . '" loading="lazy">'
					. '</button>';
			} else {
				echo '<div class="hd-attachment__icon" aria-hidden="true">' . esc_html( $this->mimeIcon( $mime ) ) . '</div>';
			}

			echo '<div class="hd-attachment__info">'
				. '<span class="hd-attachment__name" title="' . esc_attr( $name ) . '">' . esc_html( $name ) . '</span>';

			if ( '' !== $size_fmt ) {
				echo '<span class="hd-attachment__meta">' . esc_html( $size_fmt ) . '</span>';
			}
			if ( '' !== $date ) {
				echo '<span class="hd-attachment__meta">' . esc_html( $date ) . '</span>';
			}

			echo '<div class="hd-attachment__actions">';
			if ( '' !== $url ) {
				echo '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener" class="hd-btn hd-btn--xs hd-btn--secondary">' . esc_html__( 'Open', 'wp-helpdesk' ) . '</a>'
					. ' '
					. '<a href="' . esc_url( $url ) . '" download="' . esc_attr( $name ) . '" class="hd-btn hd-btn--xs hd-btn--secondary">' . esc_html__( 'Download', 'wp-helpdesk' ) . '</a>';
			}
			echo '</div>'
				. '</div>'
				. '</div>';
		}
		echo '</div>';
	}

	/**
	 * Return a simple text/emoji icon for a given MIME type.
	 *
	 * @param string $mime MIME type string.
	 * @return string
	 */
	protected function mimeIcon( string $mime ): string {
		if ( 'application/pdf' === $mime ) {
			return '📄';
		}
		if ( in_array( $mime, array( 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' ), true ) ) {
			return '📝';
		}
		if ( in_array( $mime, array( 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' ), true ) ) {
			return '📊';
		}
		if ( 'application/zip' === $mime ) {
			return '🗜️';
		}
		return '📎';
	}
}
