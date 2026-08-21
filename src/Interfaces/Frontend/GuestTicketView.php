<?php
/**
 * @package WP Helpdesk
 */

namespace WPHelpdesk\Interfaces\Frontend;

use WPHelpdesk\Domain\Attachment\AttachmentService;
use WPHelpdesk\Infrastructure\Database\Schema;
use WPHelpdesk\Support\Constants;
use WPHelpdesk\Support\RendersAttachmentsTrait;

/**
 * Renders the guest ticket view at /helpdesk/ticket/{ticket_no}/{guest_token}/.
 *
 * Allows guests to read their ticket thread, view attachments, and post replies
 * without needing to be logged in, as long as they hold the secure token that
 * was emailed to them when the ticket was created.
 */
class GuestTicketView extends HelpdeskPage {
	use RendersAttachmentsTrait;

	protected AttachmentService $attachment_service;

	public function __construct( ?AttachmentService $attachment_service = null ) {
		$this->attachment_service = $attachment_service ?: new AttachmentService();
	}

	/**
	 * Render the ticket view page, reading ticket_no and guest_token from
	 * the WordPress query vars set by FrontendRouter.
	 *
	 * @return void
	 */
	public function render(): void {
		$ticket_no   = sanitize_text_field( (string) get_query_var( 'hd_ticket_no', '' ) );
		$guest_token = sanitize_text_field( (string) get_query_var( 'hd_guest_token', '' ) );
		$this->renderForTicket( $ticket_no, $guest_token );
	}

	/**
	 * Render the ticket view page for the given ticket_no and guest_token.
	 * Separated from render() so tests can call this directly without
	 * relying on WordPress query vars.
	 *
	 * @param string $ticket_no   Ticket number from the URL.
	 * @param string $guest_token Guest access token from the URL.
	 * @return void
	 */
	public function renderForTicket( string $ticket_no, string $guest_token ): void {
		$ticket = $this->findTicket( $ticket_no, $guest_token );

		if ( null === $ticket ) {
			$this->outputHeader( __( 'Ticket Not Found', 'wp-helpdesk' ) );
			?>
			<div class="hd-wrap">
				<h1 class="hd-title"><?php esc_html_e( 'Ticket not found', 'wp-helpdesk' ); ?></h1>
				<p><?php esc_html_e( 'The requested ticket could not be found. Please check your link and try again.', 'wp-helpdesk' ); ?></p>
				<a href="<?php echo esc_url( home_url( '/helpdesk/' ) ); ?>" class="hd-btn hd-btn--secondary">
					<?php esc_html_e( 'Back to Support Centre', 'wp-helpdesk' ); ?>
				</a>
			</div>
			<?php
			$this->outputFooter();
			return;
		}

		$messages    = $this->getMessages( (int) $ticket['id'] );
		$attachments = $this->attachment_service->getForTicket( (int) $ticket['id'] );

		$this->outputHeader( sprintf( __( 'Ticket %s', 'wp-helpdesk' ), esc_html( (string) $ticket['ticket_no'] ) ) );
		?>
		<div class="hd-wrap">
			<p class="hd-back-link">
				<a href="<?php echo esc_url( home_url( '/helpdesk/' ) ); ?>">
					&larr; <?php esc_html_e( 'Back to Support Centre', 'wp-helpdesk' ); ?>
				</a>
			</p>

			<div class="hd-ticket-view">
				<h1 class="hd-title">
					<?php echo esc_html( sprintf( __( 'Ticket %s', 'wp-helpdesk' ), (string) $ticket['ticket_no'] ) ); ?>
				</h1>

				<div class="hd-ticket-meta">
					<p><strong><?php esc_html_e( 'Subject:', 'wp-helpdesk' ); ?></strong> <?php echo esc_html( (string) $ticket['subject'] ); ?></p>
					<p>
						<strong><?php esc_html_e( 'Status:', 'wp-helpdesk' ); ?></strong>
						<span class="hd-status-badge hd-status-badge--<?php echo esc_attr( (string) $ticket['status'] ); ?>">
							<?php echo esc_html( (string) $ticket['status'] ); ?>
						</span>
					</p>
				</div>

				<!-- Thread -->
				<h2 class="hd-section-title"><?php esc_html_e( 'Messages', 'wp-helpdesk' ); ?></h2>
				<div class="hd-thread">
					<?php if ( empty( $messages ) ) : ?>
						<p><?php esc_html_e( 'No messages yet.', 'wp-helpdesk' ); ?></p>
					<?php else : ?>
						<?php foreach ( $messages as $msg ) : ?>
							<div class="hd-thread__message hd-thread__message--<?php echo esc_attr( (string) $msg['author_type'] ); ?>">
								<div class="hd-thread__meta">
									<span class="hd-thread__author"><?php echo esc_html( (string) $msg['author_type'] ); ?></span>
									<span class="hd-thread__date"><?php echo esc_html( (string) $msg['created_at'] ); ?></span>
								</div>
								<div class="hd-thread__body"><?php echo wp_kses_post( wpautop( (string) $msg['body'] ) ); ?></div>
							</div>
						<?php endforeach; ?>
					<?php endif; ?>
				</div>

				<!-- Attachments -->
				<?php if ( ! empty( $attachments ) ) : ?>
					<h2 class="hd-section-title"><?php esc_html_e( 'Attachments', 'wp-helpdesk' ); ?></h2>
					<?php $this->renderAttachments( $attachments ); ?>
				<?php endif; ?>

				<!-- Reply form -->
				<h2 class="hd-section-title"><?php esc_html_e( 'Add a Reply', 'wp-helpdesk' ); ?></h2>
				<div class="hd-reply-form" id="hd-guest-reply-form"
					data-ticket-no="<?php echo esc_attr( (string) $ticket['ticket_no'] ); ?>"
					data-guest-token="<?php echo esc_attr( $guest_token ); ?>">
					<div class="hd-field">
						<label for="hd-guest-reply-body" class="hd-label">
							<?php esc_html_e( 'Message', 'wp-helpdesk' ); ?>
							<span class="hd-required" aria-hidden="true">*</span>
						</label>
						<textarea id="hd-guest-reply-body" class="hd-textarea" rows="5" required aria-required="true"></textarea>
					</div>
					<div class="hd-form-actions">
						<button type="button" class="hd-btn hd-btn--primary" id="hd-guest-reply-submit">
							<?php esc_html_e( 'Send reply', 'wp-helpdesk' ); ?>
						</button>
					</div>
					<p class="hd-error-message" id="hd-guest-reply-error" aria-live="assertive" role="alert"></p>
					<p class="hd-success-message hd-success-message--hidden" id="hd-guest-reply-success" aria-live="polite"></p>
				</div>
			</div>
		</div>

		<!-- Lightbox modal -->
		<div class="hd-lightbox" id="hd-lightbox" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Image viewer', 'wp-helpdesk' ); ?>" hidden>
			<button class="hd-lightbox__close" id="hd-lightbox-close" aria-label="<?php esc_attr_e( 'Close', 'wp-helpdesk' ); ?>">&times;</button>
			<img class="hd-lightbox__img" id="hd-lightbox-img" src="" alt="">
		</div>
		<?php
		$this->outputFooter();
	}

	/**
	 * Look up a ticket by ticket_no + guest_token hash.
	 *
	 * @param string $ticket_no   Ticket number.
	 * @param string $guest_token Guest access token.
	 * @return array<string, mixed>|null
	 */
	protected function findTicket( string $ticket_no, string $guest_token ): ?array {
		if ( '' === $ticket_no || '' === $guest_token ) {
			return null;
		}
		$guest_token_hash = hash( 'sha256', $guest_token );

		global $wpdb;
		$table = Schema::table( Constants::TABLE_TICKETS );
		$row   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE ticket_no = %s AND guest_token_hash = %s LIMIT 1",
				$ticket_no,
				$guest_token_hash
			),
			ARRAY_A
		);

		return $row ?: null;
	}

	/**
	 * Get public (non-internal) messages for a ticket, oldest first.
	 *
	 * @param int $ticket_id Ticket ID.
	 * @return array<int, array<string, mixed>>
	 */
	protected function getMessages( int $ticket_id ): array {
		global $wpdb;
		$table = Schema::table( Constants::TABLE_TICKET_MESSAGES );
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE ticket_id = %d AND is_internal = 0 ORDER BY created_at ASC",
				$ticket_id
			),
			ARRAY_A
		);

		return $rows ?: array();
	}
}
