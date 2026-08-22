<?php
/**
 * @package WP Helpdesk
 */

namespace WPHelpdesk\Interfaces\Admin\Pages;

use WPHelpdesk\Domain\Attachment\AttachmentService;
use WPHelpdesk\Domain\Ticket\TicketStatus;
use WPHelpdesk\Infrastructure\Database\Schema;
use WPHelpdesk\Support\Constants;
use WPHelpdesk\Support\Helpers;
use WPHelpdesk\Support\RendersAttachmentsTrait;

class TicketsPage {
	use RendersAttachmentsTrait;
	protected AttachmentService $attachment_service;

	public function __construct( ?AttachmentService $attachment_service = null ) {
		$this->attachment_service = $attachment_service ?: new AttachmentService();
	}

	/**
	 * Render the tickets queue and a basic ticket thread view.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( 'hd_manage_tickets' ) && ! current_user_can( 'hd_reply_tickets' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'wp-helpdesk' ) );
		}

		$this->handlePost();

		$selected_ticket_id = isset( $_GET['ticket_id'] ) ? (int) $_GET['ticket_id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$status_filter      = $this->currentStatusFilter();
		$tickets            = $this->listTickets( 50, $status_filter );
		$selected_ticket    = $selected_ticket_id > 0 ? $this->findTicket( $selected_ticket_id ) : null;
		$messages           = $selected_ticket ? $this->getMessages( (int) $selected_ticket['id'] ) : array();
		$status_options     = TicketStatus::canonicalValues();
		$navigation         = $selected_ticket ? $this->getTicketNavigation( $tickets, (int) $selected_ticket['id'] ) : array(
			'previous' => null,
			'next'     => null,
		);
		?>
		<div class="wrap hd-admin-wrap">
			<h1><?php esc_html_e( 'Ticket Queue', 'wp-helpdesk' ); ?></h1>

			<?php
			// Display attachment upload error passed via redirect URL.
			if ( ! empty( $_GET['hd_attach_error'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$error_msg = sanitize_text_field( rawurldecode( wp_unslash( (string) $_GET['hd_attach_error'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $error_msg ) . '</p></div>';
			}
			?>

			<?php if ( ! $selected_ticket ) : ?>
				<form method="get" style="margin-bottom:16px;">
					<input type="hidden" name="page" value="wp-helpdesk-tickets">
					<label for="hd-status-filter"><strong><?php esc_html_e( 'Filter by status:', 'wp-helpdesk' ); ?></strong></label>
					<select id="hd-status-filter" name="status_filter">
						<option value=""><?php esc_html_e( 'All', 'wp-helpdesk' ); ?></option>
						<?php foreach ( $status_options as $status_opt ) : ?>
							<option value="<?php echo esc_attr( $status_opt ); ?>" <?php selected( $status_filter, $status_opt ); ?>>
								<?php echo esc_html( TicketStatus::label( $status_opt ) ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<?php submit_button( __( 'Filter', 'wp-helpdesk' ), 'secondary small', 'filter_submit', false ); ?>
				</form>

				<div class="hd-card">
					<h2><?php esc_html_e( 'Queue', 'wp-helpdesk' ); ?></h2>
					<?php if ( empty( $tickets ) ) : ?>
						<p><?php esc_html_e( 'No tickets found yet.', 'wp-helpdesk' ); ?></p>
					<?php else : ?>
						<form method="post" id="hd-bulk-form">
							<?php wp_nonce_field( 'hd_ticket_action', 'hd_ticket_nonce' ); ?>
							<?php if ( '' !== $status_filter ) : ?>
								<input type="hidden" name="hd_status_filter" value="<?php echo esc_attr( $status_filter ); ?>">
							<?php endif; ?>
							<?php if ( current_user_can( 'hd_manage_tickets' ) ) : ?>
								<div style="margin-bottom:8px;">
									<select name="hd_bulk_status" style="margin-right:8px;">
										<option value=""><?php esc_html_e( 'Select status…', 'wp-helpdesk' ); ?></option>
										<?php foreach ( $status_options as $status_option ) : ?>
											<option value="<?php echo esc_attr( $status_option ); ?>"><?php echo esc_html( TicketStatus::label( $status_option ) ); ?></option>
										<?php endforeach; ?>
									</select>
									<button type="submit" class="button button-secondary" name="hd_ticket_action" value="bulk_status">
										<?php esc_html_e( 'Change Status', 'wp-helpdesk' ); ?>
									</button>
									<button type="submit" class="button button-secondary" name="hd_ticket_action" value="bulk_delete" onclick="return confirm('<?php esc_attr_e( 'Delete selected tickets and all their attachments?', 'wp-helpdesk' ); ?>');">
										<?php esc_html_e( 'Delete Selected', 'wp-helpdesk' ); ?>
									</button>
								</div>
							<?php endif; ?>
							<table class="widefat striped">
								<thead>
									<tr>
										<?php if ( current_user_can( 'hd_manage_tickets' ) ) : ?>
											<td class="check-column">
												<input type="checkbox" id="hd-select-all" title="<?php esc_attr_e( 'Select all', 'wp-helpdesk' ); ?>">
											</td>
										<?php endif; ?>
										<th><?php esc_html_e( 'Ticket', 'wp-helpdesk' ); ?></th>
										<th><?php esc_html_e( 'Subject', 'wp-helpdesk' ); ?></th>
										<th><?php esc_html_e( 'Requester', 'wp-helpdesk' ); ?></th>
										<th><?php esc_html_e( 'Status', 'wp-helpdesk' ); ?></th>
										<th><?php esc_html_e( 'Updated', 'wp-helpdesk' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ( $tickets as $ticket ) : ?>
										<tr>
											<?php if ( current_user_can( 'hd_manage_tickets' ) ) : ?>
												<td class="check-column">
													<input type="checkbox" name="hd_ticket_ids[]" value="<?php echo esc_attr( (string) $ticket['id'] ); ?>">
												</td>
											<?php endif; ?>
											<td>
												<a href="<?php echo esc_url( $this->getAdminPageUrl( array( 'ticket_id' => (int) $ticket['id'] ), $status_filter ) ); ?>">
													<?php echo esc_html( (string) $ticket['ticket_no'] ); ?>
												</a>
											</td>
											<td><?php echo esc_html( (string) $ticket['subject'] ); ?></td>
											<td><?php echo esc_html( (string) $ticket['requester_name'] . ' (' . (string) $ticket['requester_email'] . ')' ); ?></td>
											<td><?php echo esc_html( TicketStatus::label( (string) $ticket['status'] ) ); ?></td>
											<td><?php echo esc_html( (string) $ticket['updated_at'] ); ?></td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</form>
						<script>
							(function () {
								var selectAll = document.getElementById('hd-select-all');
								if (selectAll) {
									selectAll.addEventListener('change', function () {
										var checkboxes = document.querySelectorAll('#hd-bulk-form input[name="hd_ticket_ids[]"]');
										for (var i = 0; i < checkboxes.length; i++) {
											checkboxes[i].checked = selectAll.checked;
										}
									});
								}
							})();
						</script>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<?php if ( $selected_ticket ) : ?>
				<div class="hd-ticket-nav">
					<div class="hd-ticket-nav__actions">
						<a class="button button-secondary" href="<?php echo esc_url( $this->getAdminPageUrl( array(), $status_filter ) ); ?>">
							<?php esc_html_e( 'Back to Queue', 'wp-helpdesk' ); ?>
						</a>
						<?php if ( ! empty( $navigation['previous'] ) ) : ?>
							<a class="button button-secondary" href="<?php echo esc_url( $this->getAdminPageUrl( array( 'ticket_id' => (int) $navigation['previous']['id'] ), $status_filter ) ); ?>">
								<?php esc_html_e( 'Previous Ticket', 'wp-helpdesk' ); ?>
							</a>
						<?php else : ?>
							<button type="button" class="button button-secondary" disabled>
								<?php esc_html_e( 'Previous Ticket', 'wp-helpdesk' ); ?>
							</button>
						<?php endif; ?>
						<?php if ( ! empty( $navigation['next'] ) ) : ?>
							<a class="button button-secondary" href="<?php echo esc_url( $this->getAdminPageUrl( array( 'ticket_id' => (int) $navigation['next']['id'] ), $status_filter ) ); ?>">
								<?php esc_html_e( 'Next Ticket', 'wp-helpdesk' ); ?>
							</a>
						<?php else : ?>
							<button type="button" class="button button-secondary" disabled>
								<?php esc_html_e( 'Next Ticket', 'wp-helpdesk' ); ?>
							</button>
						<?php endif; ?>
					</div>
					<div class="hd-ticket-nav__summary">
						<div class="hd-ticket-nav__title">
							<strong><?php echo esc_html( sprintf( __( 'Ticket %s', 'wp-helpdesk' ), (string) $selected_ticket['ticket_no'] ) ); ?></strong>
							<span><?php echo esc_html( (string) $selected_ticket['subject'] ); ?></span>
						</div>
						<div class="hd-ticket-nav__meta">
							<span class="hd-status-badge"><?php echo esc_html( TicketStatus::label( (string) $selected_ticket['status'] ) ); ?></span>
							<?php if ( ! empty( $selected_ticket['requester_name'] ) ) : ?>
								<span><?php echo esc_html( (string) $selected_ticket['requester_name'] ); ?></span>
							<?php endif; ?>
							<?php if ( '' !== $status_filter ) : ?>
								<span><?php echo esc_html( sprintf( __( 'Filtered by %s', 'wp-helpdesk' ), TicketStatus::label( $status_filter ) ) ); ?></span>
							<?php endif; ?>
						</div>
					</div>
				</div>

				<div class="hd-card" style="margin-top: 20px;">
					<h2>
						<?php echo esc_html( sprintf( __( 'Ticket %s', 'wp-helpdesk' ), (string) $selected_ticket['ticket_no'] ) ); ?>
					</h2>
					<p><strong><?php esc_html_e( 'Subject:', 'wp-helpdesk' ); ?></strong> <?php echo esc_html( (string) $selected_ticket['subject'] ); ?></p>
					<p><strong><?php esc_html_e( 'Phone:', 'wp-helpdesk' ); ?></strong> <?php echo esc_html( (string) $selected_ticket['requester_phone'] ); ?></p>
					<p><strong><?php esc_html_e( 'Status:', 'wp-helpdesk' ); ?></strong> <?php echo esc_html( TicketStatus::label( (string) $selected_ticket['status'] ) ); ?></p>
					<?php $this->renderOrderRelationRow( $selected_ticket ); ?>

					<h3><?php esc_html_e( 'Thread', 'wp-helpdesk' ); ?></h3>
					<?php if ( empty( $messages ) ) : ?>
						<p><?php esc_html_e( 'No messages yet.', 'wp-helpdesk' ); ?></p>
					<?php else : ?>
						<ul>
							<?php foreach ( $messages as $message ) : ?>
								<li style="margin-bottom: 12px;">
									<strong><?php echo esc_html( $this->resolveAuthorLabel( $message, $selected_ticket ) ); ?></strong>
									(<?php echo esc_html( (string) $message['created_at'] ); ?>)
									<?php if ( ! empty( $message['is_internal'] ) ) : ?>
										<em><?php esc_html_e( 'Internal', 'wp-helpdesk' ); ?></em>
									<?php endif; ?>
									<div><?php echo wp_kses_post( wpautop( (string) $message['body'] ) ); ?></div>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>

					<?php
					$attachments = $this->attachment_service->getForTicket( (int) $selected_ticket['id'] );
					if ( ! empty( $attachments ) ) :
					?>
					<h3><?php esc_html_e( 'Attachments', 'wp-helpdesk' ); ?></h3>
					<?php $this->renderAttachments( $attachments ); ?>
					<?php endif; ?>

					<?php if ( current_user_can( 'hd_reply_tickets' ) || current_user_can( 'hd_manage_tickets' ) ) : ?>
						<form method="post" enctype="multipart/form-data" style="margin-top: 16px;">
							<?php wp_nonce_field( 'hd_ticket_action', 'hd_ticket_nonce' ); ?>
							<input type="hidden" name="hd_ticket_id" value="<?php echo esc_attr( (string) $selected_ticket['id'] ); ?>">
							<input type="hidden" name="hd_ticket_action" value="reply">
							<?php if ( '' !== $status_filter ) : ?>
								<input type="hidden" name="hd_status_filter" value="<?php echo esc_attr( $status_filter ); ?>">
							<?php endif; ?>
							<p>
								<label for="hd-reply-body"><strong><?php esc_html_e( 'Reply', 'wp-helpdesk' ); ?></strong></label><br>
								<textarea id="hd-reply-body" name="hd_reply_body" rows="5" class="large-text" required></textarea>
							</p>
							<p>
								<label>
									<input type="checkbox" name="hd_is_internal" value="1">
									<?php esc_html_e( 'Internal note', 'wp-helpdesk' ); ?>
								</label>
							</p>
							<p>
								<label for="hd-reply-attachment"><strong><?php esc_html_e( 'Attachment', 'wp-helpdesk' ); ?></strong></label><br>
								<input
									type="file"
									id="hd-reply-attachment"
									name="hd_attachment"
									accept="image/jpeg,image/png,image/gif,application/pdf,text/plain,application/zip"
								>
								<span class="description"><?php esc_html_e( 'Optional. JPEG, PNG, GIF, PDF, TXT, ZIP. Max 10 MB.', 'wp-helpdesk' ); ?></span>
							</p>
							<?php submit_button( __( 'Add Reply', 'wp-helpdesk' ), 'secondary', 'submit', false ); ?>
						</form>
					<?php endif; ?>

					<?php if ( current_user_can( 'hd_manage_tickets' ) ) : ?>
						<form method="post" style="margin-top: 16px;">
							<?php wp_nonce_field( 'hd_ticket_action', 'hd_ticket_nonce' ); ?>
							<input type="hidden" name="hd_ticket_id" value="<?php echo esc_attr( (string) $selected_ticket['id'] ); ?>">
							<input type="hidden" name="hd_ticket_action" value="status">
							<?php if ( '' !== $status_filter ) : ?>
								<input type="hidden" name="hd_status_filter" value="<?php echo esc_attr( $status_filter ); ?>">
							<?php endif; ?>
							<p>
								<label for="hd-status-select"><strong><?php esc_html_e( 'Change status', 'wp-helpdesk' ); ?></strong></label><br>
								<select id="hd-status-select" name="hd_status" required>
									<?php foreach ( $status_options as $status_option ) : ?>
										<option value="<?php echo esc_attr( $status_option ); ?>" <?php selected( TicketStatus::toCanonical( (string) $selected_ticket['status'] ), $status_option ); ?>>
											<?php echo esc_html( TicketStatus::label( $status_option ) ); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</p>
							<?php submit_button( __( 'Update Status', 'wp-helpdesk' ), 'primary', 'submit', false ); ?>
						</form>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</div>

		<!-- Lightbox modal for attachment image previews -->
		<div class="hd-lightbox" id="hd-lightbox" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Image viewer', 'wp-helpdesk' ); ?>" hidden style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.8);z-index:100000;align-items:center;justify-content:center;">
			<button class="hd-lightbox__close" id="hd-lightbox-close" aria-label="<?php esc_attr_e( 'Close', 'wp-helpdesk' ); ?>" style="position:absolute;top:16px;right:20px;background:none;border:none;color:#fff;font-size:2rem;cursor:pointer;line-height:1;">&times;</button>
			<img class="hd-lightbox__img" id="hd-lightbox-img" src="" alt="" style="max-width:90vw;max-height:90vh;object-fit:contain;border-radius:4px;">
		</div>
		<script>
		(function () {
			var lightbox = document.getElementById('hd-lightbox');
			var lightboxImg = document.getElementById('hd-lightbox-img');
			var lightboxClose = document.getElementById('hd-lightbox-close');
			if (!lightbox || !lightboxImg || !lightboxClose) { return; }
			document.addEventListener('click', function (e) {
				var btn = e.target.closest('.hd-attachment__thumb-btn');
				if (!btn) { return; }
				lightboxImg.src = btn.dataset.lightboxSrc || '';
				lightboxImg.alt = btn.dataset.lightboxAlt || '';
				lightbox.hidden = false;
				lightbox.style.display = 'flex';
				lightboxClose.focus();
			});
			lightboxClose.addEventListener('click', function () {
				lightbox.hidden = true;
				lightbox.style.display = 'none';
				lightboxImg.src = '';
			});
			lightbox.addEventListener('click', function (e) {
				if (e.target === lightbox) {
					lightbox.hidden = true;
					lightbox.style.display = 'none';
					lightboxImg.src = '';
				}
			});
			document.addEventListener('keydown', function (e) {
				if ('Escape' === e.key && !lightbox.hidden) {
					lightbox.hidden = true;
					lightbox.style.display = 'none';
					lightboxImg.src = '';
				}
			});
		})();
		</script>
		<?php
	}

	/**
	 * Process page form submissions.
	 *
	 * @return void
	 */
	public function handlePost(): void {
		if ( 'POST' !== strtoupper( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
			return;
		}
		if ( 'wp-helpdesk-tickets' !== ( $_GET['page'] ?? '' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$nonce = isset( $_POST['hd_ticket_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['hd_ticket_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'hd_ticket_action' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'wp-helpdesk' ) );
		}

		$action = isset( $_POST['hd_ticket_action'] ) ? sanitize_key( wp_unslash( $_POST['hd_ticket_action'] ) ) : '';
		$ticket_id = isset( $_POST['hd_ticket_id'] ) ? (int) $_POST['hd_ticket_id'] : 0;

		if ( 'bulk_delete' === $action ) {
			$this->handleBulkDelete();
			return;
		}
		if ( 'bulk_status' === $action ) {
			$this->handleBulkStatus();
			return;
		}

		if ( $ticket_id <= 0 ) {
			return;
		}

		if ( 'reply' === $action ) {
			$this->handleReplyPost( $ticket_id );
		}
		if ( 'status' === $action ) {
			$this->handleStatusPost( $ticket_id );
		}
	}

	/**
	 * Handle bulk deletion of selected tickets.
	 *
	 * @return void
	 */
	protected function handleBulkDelete(): void {
		if ( ! current_user_can( 'hd_manage_tickets' ) ) {
			wp_die( esc_html__( 'You do not have permission to delete tickets.', 'wp-helpdesk' ) );
		}

		$raw_ids = isset( $_POST['hd_ticket_ids'] ) && is_array( $_POST['hd_ticket_ids'] )
			? $_POST['hd_ticket_ids']
			: array();

		$ticket_ids = array_filter( array_map( 'intval', $raw_ids ) );
		if ( empty( $ticket_ids ) ) {
			$this->redirectTo( $this->getAdminPageUrl( array(), $this->currentStatusFilter( true ) ) );
			return;
		}

		$ticket_service = $this->getTicketService();
		foreach ( $ticket_ids as $id ) {
			$ticket_service->deleteTicket( $id );
		}

		$this->redirectTo( $this->getAdminPageUrl( array(), $this->currentStatusFilter( true ) ) );
	}

	/**
	 * Handle bulk status change of selected tickets.
	 *
	 * @return void
	 */
	protected function handleBulkStatus(): void {
		if ( ! current_user_can( 'hd_manage_tickets' ) ) {
			wp_die( esc_html__( 'You do not have permission to change ticket status.', 'wp-helpdesk' ) );
		}

		$raw_ids = isset( $_POST['hd_ticket_ids'] ) && is_array( $_POST['hd_ticket_ids'] )
			? $_POST['hd_ticket_ids']
			: array();
		$ticket_ids = array_filter( array_map( 'intval', $raw_ids ) );
		$status     = TicketStatus::tryCanonical( isset( $_POST['hd_bulk_status'] ) ? sanitize_key( wp_unslash( $_POST['hd_bulk_status'] ) ) : '' );

		if ( empty( $ticket_ids ) || null === $status ) {
			$this->redirectTo( $this->getAdminPageUrl( array(), $this->currentStatusFilter( true ) ) );
			return;
		}

		$ticket_service = $this->getTicketService();
		foreach ( $ticket_ids as $ticket_id ) {
			$ticket = $this->findTicket( $ticket_id );
			if ( ! $ticket ) {
				continue;
			}

			$ticket_service->updateTicket( $ticket_id, array( 'status' => $status ) );
			$updated_ticket           = $ticket;
			$updated_ticket['status'] = TicketStatus::toStorage( $status );
			do_action( 'hd_ticket_status_changed', $updated_ticket, TicketStatus::toCanonical( (string) $ticket['status'] ), $status );
		}

		$this->redirectTo( $this->getAdminPageUrl( array(), $this->currentStatusFilter( true ) ) );
	}

	/**
	 * Redirect to the given URL and exit. Overridable in tests.
	 *
	 * @param string $url Target URL.
	 * @return void
	 */
	protected function redirectTo( string $url ): void {
		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Get the TicketService instance (overridable in tests).
	 *
	 * @return \WPHelpdesk\Domain\Ticket\TicketService
	 */
	protected function getTicketService(): \WPHelpdesk\Domain\Ticket\TicketService {
		return new \WPHelpdesk\Domain\Ticket\TicketService();
	}

	/**
	 * Handle posting a reply in the thread.
	 *
	 * @param int $ticket_id Ticket id.
	 * @return void
	 */
	protected function handleReplyPost( int $ticket_id ): void {
		if ( ! current_user_can( 'hd_reply_tickets' ) && ! current_user_can( 'hd_manage_tickets' ) ) {
			wp_die( esc_html__( 'You do not have permission to reply to tickets.', 'wp-helpdesk' ) );
		}

		$ticket = $this->findTicket( $ticket_id );
		if ( ! $ticket ) {
			return;
		}

		$body = isset( $_POST['hd_reply_body'] ) ? wp_kses_post( wp_unslash( $_POST['hd_reply_body'] ) ) : '';
		if ( '' === trim( wp_strip_all_tags( $body ) ) ) {
			return;
		}

		global $wpdb;
		$table = Schema::table( Constants::TABLE_TICKET_MESSAGES );
		$wpdb->insert(
			$table,
			array(
				'ticket_id'      => $ticket_id,
				'author_user_id' => get_current_user_id(),
				'author_type'    => 'agent',
				'body'           => $body,
				'is_internal'    => isset( $_POST['hd_is_internal'] ) ? 1 : 0,
				'created_at'     => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%s', '%d', '%s' )
		);

		$message = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d LIMIT 1",
				(int) $wpdb->insert_id
			),
			ARRAY_A
		);

		$message_id = $message ? (int) $message['id'] : null;

		// Handle optional file attachment.
		$upload_error = $this->maybeUploadReplyAttachment( $ticket_id, $message_id );

		do_action( 'hd_ticket_replied', $ticket, $message ?: array() );

		$redirect = $this->getAdminPageUrl( array( 'ticket_id' => $ticket_id ), $this->currentStatusFilter( true ) );
		if ( null !== $upload_error ) {
			$redirect = add_query_arg( 'hd_attach_error', rawurlencode( $upload_error->get_error_message() ), $redirect );
		}
		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Upload a reply attachment from $_FILES if one was submitted.
	 *
	 * Extracted so it can be tested without triggering the redirect/exit path.
	 *
	 * @param int      $ticket_id  Ticket ID.
	 * @param int|null $message_id Message ID, or null.
	 * @return \WP_Error|null WP_Error on failure, null on success or when no file is present.
	 */
	protected function maybeUploadReplyAttachment( int $ticket_id, ?int $message_id ): ?\WP_Error {
		if ( ! empty( $_FILES['hd_attachment'] ) && ! empty( $_FILES['hd_attachment']['name'] ) ) {
			$result = $this->attachment_service->handleUpload(
				$_FILES['hd_attachment'],
				$ticket_id,
				$message_id,
				get_current_user_id()
			);
			if ( $result instanceof \WP_Error ) {
				return $result;
			}
		}
		return null;
	}

	/**
	 * Handle changing a ticket status.
	 *
	 * @param int $ticket_id Ticket id.
	 * @return void
	 */
	protected function handleStatusPost( int $ticket_id ): void {
		if ( ! current_user_can( 'hd_manage_tickets' ) ) {
			wp_die( esc_html__( 'You do not have permission to change ticket status.', 'wp-helpdesk' ) );
		}

		$ticket = $this->findTicket( $ticket_id );
		if ( ! $ticket ) {
			return;
		}

		$new_status = TicketStatus::tryCanonical( isset( $_POST['hd_status'] ) ? sanitize_key( wp_unslash( $_POST['hd_status'] ) ) : '' );
		if ( null === $new_status ) {
			return;
		}

		global $wpdb;
		$table = Schema::table( Constants::TABLE_TICKETS );
		$storage_status = TicketStatus::toStorage( $new_status );
		$wpdb->update(
			$table,
			array(
				'status' => $storage_status,
				'updated_at' => current_time( 'mysql' ),
				'closed_at' => TicketStatus::CANONICAL_CLOSED === $new_status ? current_time( 'mysql' ) : null,
			),
			array( 'id' => $ticket_id ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);

		$updated_ticket = $this->findTicket( $ticket_id );
		do_action( 'hd_ticket_status_changed', $updated_ticket ?: $ticket, TicketStatus::toCanonical( (string) $ticket['status'] ), $new_status );

		wp_safe_redirect( $this->getAdminPageUrl( array( 'ticket_id' => $ticket_id ), $this->currentStatusFilter( true ) ) );
		exit;
	}

	/**
	 * Get the active canonical status filter from the current request.
	 *
	 * @param bool $allow_post Whether to read the hidden POSTed filter value.
	 * @return string
	 */
	protected function currentStatusFilter( bool $allow_post = false ): string {
		$value = '';

		if ( $allow_post && isset( $_POST['hd_status_filter'] ) ) {
			$value = sanitize_key( wp_unslash( (string) $_POST['hd_status_filter'] ) );
		} elseif ( isset( $_GET['status_filter'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$value = sanitize_key( wp_unslash( (string) $_GET['status_filter'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		return in_array( $value, TicketStatus::canonicalValues(), true ) ? $value : '';
	}

	/**
	 * Resolve a human-readable author label for a message.
	 *
	 * Uses the ticket's requester_name for guest/member messages so the actual
	 * customer name is displayed instead of the generic role label.
	 *
	 * @param array<string, mixed> $message Message row.
	 * @param array<string, mixed> $ticket  Ticket row.
	 * @return string
	 */
	protected function resolveAuthorLabel( array $message, array $ticket ): string {
		$author_type = (string) ( $message['author_type'] ?? '' );

		if ( in_array( $author_type, array( 'member', 'guest' ), true ) ) {
			$name = trim( (string) ( $ticket['requester_name'] ?? '' ) );
			if ( '' !== $name ) {
				return $name;
			}
		}

		$map = array(
			'agent'  => __( 'Agent', 'wp-helpdesk' ),
			'system' => __( 'System', 'wp-helpdesk' ),
		);

		return $map[ $author_type ] ?? ucfirst( $author_type );
	}

	/**
	 * Build a ticket admin URL with optional queue filter context.
	 *
	 * @param array<string, scalar> $args          Additional query arguments.
	 * @param string                $status_filter Optional canonical status filter.
	 * @return string
	 */
	protected function getAdminPageUrl( array $args = array(), string $status_filter = '' ): string {
		$query_args = array_merge(
			array( 'page' => 'wp-helpdesk-tickets' ),
			$args
		);

		if ( '' !== $status_filter ) {
			$query_args['status_filter'] = $status_filter;
		}

		return add_query_arg( $query_args, network_admin_url( 'admin.php' ) );
	}

	/**
	 * Resolve previous/next tickets from the current queue context.
	 *
	 * @param array<int, array<string, mixed>> $tickets            Queue rows.
	 * @param int                              $selected_ticket_id Selected ticket id.
	 * @return array{previous: array<string, mixed>|null, next: array<string, mixed>|null}
	 */
	protected function getTicketNavigation( array $tickets, int $selected_ticket_id ): array {
		$navigation = array(
			'previous' => null,
			'next'     => null,
		);
		$tickets    = array_values( $tickets );

		foreach ( $tickets as $index => $ticket ) {
			if ( (int) ( $ticket['id'] ?? 0 ) !== $selected_ticket_id ) {
				continue;
			}

			$navigation['previous'] = $index > 0 ? ( $tickets[ $index - 1 ] ?? null ) : null;
			$navigation['next']     = $tickets[ $index + 1 ] ?? null;
			break;
		}

		return $navigation;
	}

	/**
	 * Fetch queue rows.
	 *
	 * @param int    $limit         Result limit.
	 * @param string $status_filter Optional status to filter by.
	 * @return array<int, array<string, mixed>>
	 */
	/**
	 * Retrieve a WooCommerce order by ID.
	 *
	 * Thin wrapper around wc_get_order() so tests can override it without
	 * defining or redefining a global function.
	 *
	 * @param int $order_id WC order ID.
	 * @return object|false
	 */
	protected function getWooCommerceOrder( int $order_id ) {
		if ( function_exists( 'wc_get_order' ) ) {
			return wc_get_order( $order_id );
		}
		return false;
	}

	/**
	 * Render the order relation row in the ticket detail view.
	 *
	 * When the ticket has a numeric order_relation (a WC order ID) and WooCommerce
	 * is active, a direct clickable link to that order is shown.
	 * In a multisite network, the order lookup and URL generation run in the
	 * context of the originating site (ticket's site_id).
	 *
	 * @param array<string, mixed> $ticket Ticket row.
	 * @return void
	 */
	protected function renderOrderRelationRow( array $ticket ): void {
		$order_rel = isset( $ticket['order_relation'] ) ? (string) $ticket['order_relation'] : '';
		if ( '' === $order_rel ) {
			return;
		}

		echo '<p><strong>' . esc_html__( 'Order:', 'wp-helpdesk' ) . '</strong> ';

		if ( 'not_any_existing_order_related' === $order_rel ) {
			echo esc_html__( 'Not any existing order related', 'wp-helpdesk' );
		} elseif ( ctype_digit( $order_rel ) && function_exists( 'wc_get_order' ) ) {
			$site_id       = isset( $ticket['site_id'] ) ? (int) $ticket['site_id'] : null;
			$should_switch = null !== $site_id
				&& function_exists( 'is_multisite' ) && is_multisite()
				&& function_exists( 'switch_to_blog' );

			if ( $should_switch ) {
				switch_to_blog( $site_id );
			}

			$order    = $this->getWooCommerceOrder( (int) $order_rel );
			$edit_url = $order ? $order->get_edit_order_url() : '';
			$order_no = $order ? (string) $order->get_order_number() : '';

			if ( $should_switch ) {
				restore_current_blog();
			}

			if ( $order ) {
				echo '<a href="' . esc_url( $edit_url ) . '" target="_blank">#' . esc_html( $order_no ) . '</a>';
			} else {
				echo '#' . esc_html( $order_rel );
			}
		} else {
			echo esc_html( $order_rel );
		}

		echo '</p>';
	}

	/**
	 * Fetch queue rows.
	 *
	 * @param int    $limit         Result limit.
	 * @param string $status_filter Optional status to filter by.
	 * @return array<int, array<string, mixed>>
	 */
	protected function listTickets( int $limit, string $status_filter = '' ): array {
		global $wpdb;
		$table      = Schema::table( Constants::TABLE_TICKETS );
		$network_id = Helpers::getNetworkId();
		$allowed    = TicketStatus::canonicalValues();

		$where  = 'WHERE network_id = %d';
		$params = array( $network_id );

		if ( '' !== $status_filter && in_array( $status_filter, $allowed, true ) ) {
			$storage_statuses = TicketStatus::storageValuesForCanonical( $status_filter );
			if ( 1 === count( $storage_statuses ) ) {
				$where   .= ' AND status = %s';
				$params[] = $storage_statuses[0];
			} else {
				$where   .= ' AND status IN (' . implode( ',', array_fill( 0, count( $storage_statuses ), '%s' ) ) . ')';
				$params   = array_merge( $params, $storage_statuses );
			}
		}

		$params[] = max( 1, $limit );

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
			$wpdb->prepare(
				"SELECT id, ticket_no, subject, requester_name, requester_email, requester_phone, status, updated_at
				 FROM {$table}
				 {$where}
				 ORDER BY created_at DESC
				 LIMIT %d",
				...$params
			),
			ARRAY_A
		);

		return $rows ?: array();
	}

	/**
	 * Find a single network-scoped ticket.
	 *
	 * @param int $ticket_id Ticket id.
	 * @return array<string, mixed>|null
	 */
	protected function findTicket( int $ticket_id ): ?array {
		global $wpdb;
		$table = Schema::table( Constants::TABLE_TICKETS );
		$network_id = Helpers::getNetworkId();
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d AND network_id = %d LIMIT 1",
				$ticket_id,
				$network_id
			),
			ARRAY_A
		);

		return $row ?: null;
	}

	/**
	 * Get ticket messages by ticket id.
	 *
	 * @param int $ticket_id Ticket id.
	 * @return array<int, array<string, mixed>>
	 */
	protected function getMessages( int $ticket_id ): array {
		global $wpdb;
		$table = Schema::table( Constants::TABLE_TICKET_MESSAGES );
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE ticket_id = %d ORDER BY created_at ASC",
				$ticket_id
			),
			ARRAY_A
		);

		return $rows ?: array();
	}
}
