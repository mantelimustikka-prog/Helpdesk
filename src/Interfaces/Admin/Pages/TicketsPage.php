<?php
/**
 * @package WP Helpdesk
 */

namespace WPHelpdesk\Interfaces\Admin\Pages;

use WPHelpdesk\Infrastructure\Database\Schema;
use WPHelpdesk\Support\Constants;
use WPHelpdesk\Support\Helpers;

class TicketsPage {
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
		$status_filter      = isset( $_GET['status_filter'] ) ? sanitize_key( wp_unslash( $_GET['status_filter'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tickets            = $this->listTickets( 50, $status_filter );
		$selected_ticket    = $selected_ticket_id > 0 ? $this->findTicket( $selected_ticket_id ) : null;
		$messages           = $selected_ticket ? $this->getMessages( (int) $selected_ticket['id'] ) : array();
		$status_options     = array( 'new', 'triaged', 'waiting_customer', 'in_progress', 'resolved', 'closed' );
		?>
		<div class="wrap hd-admin-wrap">
			<h1><?php esc_html_e( 'Ticket Queue', 'wp-helpdesk' ); ?></h1>

			<form method="get" style="margin-bottom:16px;">
				<input type="hidden" name="page" value="wp-helpdesk-tickets">
				<label for="hd-status-filter"><strong><?php esc_html_e( 'Filter by status:', 'wp-helpdesk' ); ?></strong></label>
				<select id="hd-status-filter" name="status_filter">
					<option value=""><?php esc_html_e( 'All', 'wp-helpdesk' ); ?></option>
					<?php foreach ( $status_options as $status_opt ) : ?>
						<option value="<?php echo esc_attr( $status_opt ); ?>" <?php selected( $status_filter, $status_opt ); ?>>
							<?php echo esc_html( $status_opt ); ?>
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
						<input type="hidden" name="hd_ticket_action" value="bulk_delete">
						<?php if ( current_user_can( 'hd_manage_tickets' ) ) : ?>
							<div style="margin-bottom:8px;">
								<button type="submit" class="button button-secondary" onclick="return confirm('<?php esc_attr_e( 'Delete selected tickets and all their attachments?', 'wp-helpdesk' ); ?>');">
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
											<a href="<?php echo esc_url( network_admin_url( 'admin.php?page=wp-helpdesk-tickets&ticket_id=' . (int) $ticket['id'] ) ); ?>">
												<?php echo esc_html( (string) $ticket['ticket_no'] ); ?>
											</a>
										</td>
										<td><?php echo esc_html( (string) $ticket['subject'] ); ?></td>
										<td><?php echo esc_html( (string) $ticket['requester_name'] . ' (' . (string) $ticket['requester_email'] . ')' ); ?></td>
										<td><?php echo esc_html( (string) $ticket['status'] ); ?></td>
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

			<?php if ( $selected_ticket ) : ?>
				<div class="hd-card" style="margin-top: 20px;">
					<h2>
						<?php echo esc_html( sprintf( __( 'Ticket %s', 'wp-helpdesk' ), (string) $selected_ticket['ticket_no'] ) ); ?>
					</h2>
					<p><strong><?php esc_html_e( 'Subject:', 'wp-helpdesk' ); ?></strong> <?php echo esc_html( (string) $selected_ticket['subject'] ); ?></p>
					<p><strong><?php esc_html_e( 'Phone:', 'wp-helpdesk' ); ?></strong> <?php echo esc_html( (string) $selected_ticket['requester_phone'] ); ?></p>
					<p><strong><?php esc_html_e( 'Status:', 'wp-helpdesk' ); ?></strong> <?php echo esc_html( (string) $selected_ticket['status'] ); ?></p>
					<?php $this->renderOrderRelationRow( $selected_ticket ); ?>

					<h3><?php esc_html_e( 'Thread', 'wp-helpdesk' ); ?></h3>
					<?php if ( empty( $messages ) ) : ?>
						<p><?php esc_html_e( 'No messages yet.', 'wp-helpdesk' ); ?></p>
					<?php else : ?>
						<ul>
							<?php foreach ( $messages as $message ) : ?>
								<li style="margin-bottom: 12px;">
									<strong><?php echo esc_html( (string) $message['author_type'] ); ?></strong>
									(<?php echo esc_html( (string) $message['created_at'] ); ?>)
									<?php if ( ! empty( $message['is_internal'] ) ) : ?>
										<em><?php esc_html_e( 'Internal', 'wp-helpdesk' ); ?></em>
									<?php endif; ?>
									<div><?php echo wp_kses_post( wpautop( (string) $message['body'] ) ); ?></div>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>

					<?php if ( current_user_can( 'hd_reply_tickets' ) || current_user_can( 'hd_manage_tickets' ) ) : ?>
						<form method="post" style="margin-top: 16px;">
							<?php wp_nonce_field( 'hd_ticket_action', 'hd_ticket_nonce' ); ?>
							<input type="hidden" name="hd_ticket_id" value="<?php echo esc_attr( (string) $selected_ticket['id'] ); ?>">
							<input type="hidden" name="hd_ticket_action" value="reply">
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
							<?php submit_button( __( 'Add Reply', 'wp-helpdesk' ), 'secondary', 'submit', false ); ?>
						</form>
					<?php endif; ?>

					<?php if ( current_user_can( 'hd_manage_tickets' ) ) : ?>
						<form method="post" style="margin-top: 16px;">
							<?php wp_nonce_field( 'hd_ticket_action', 'hd_ticket_nonce' ); ?>
							<input type="hidden" name="hd_ticket_id" value="<?php echo esc_attr( (string) $selected_ticket['id'] ); ?>">
							<input type="hidden" name="hd_ticket_action" value="status">
							<p>
								<label for="hd-status-select"><strong><?php esc_html_e( 'Change status', 'wp-helpdesk' ); ?></strong></label><br>
								<select id="hd-status-select" name="hd_status" required>
									<?php foreach ( $status_options as $status_option ) : ?>
										<option value="<?php echo esc_attr( $status_option ); ?>" <?php selected( $selected_ticket['status'], $status_option ); ?>>
											<?php echo esc_html( $status_option ); ?>
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
			wp_safe_redirect( network_admin_url( 'admin.php?page=wp-helpdesk-tickets' ) );
			exit;
		}

		$ticket_service = $this->getTicketService();
		foreach ( $ticket_ids as $id ) {
			$ticket_service->deleteTicket( $id );
		}

		wp_safe_redirect( network_admin_url( 'admin.php?page=wp-helpdesk-tickets' ) );
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

		do_action( 'hd_ticket_replied', $ticket, $message ?: array() );

		wp_safe_redirect( network_admin_url( 'admin.php?page=wp-helpdesk-tickets&ticket_id=' . $ticket_id ) );
		exit;
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

		$new_status = isset( $_POST['hd_status'] ) ? sanitize_key( wp_unslash( $_POST['hd_status'] ) ) : '';
		$allowed = array( 'new', 'triaged', 'waiting_customer', 'in_progress', 'resolved', 'closed' );
		if ( ! in_array( $new_status, $allowed, true ) ) {
			return;
		}

		global $wpdb;
		$table = Schema::table( Constants::TABLE_TICKETS );
		$wpdb->update(
			$table,
			array(
				'status' => $new_status,
				'updated_at' => current_time( 'mysql' ),
				'closed_at' => 'closed' === $new_status ? current_time( 'mysql' ) : null,
			),
			array( 'id' => $ticket_id ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);

		$updated_ticket = $this->findTicket( $ticket_id );
		do_action( 'hd_ticket_status_changed', $updated_ticket ?: $ticket, (string) $ticket['status'], $new_status );

		wp_safe_redirect( network_admin_url( 'admin.php?page=wp-helpdesk-tickets&ticket_id=' . $ticket_id ) );
		exit;
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

		if ( 'not_order_related' === $order_rel ) {
			echo esc_html__( 'Not order related', 'wp-helpdesk' );
		} elseif ( ctype_digit( $order_rel ) && function_exists( 'wc_get_order' ) ) {
			$order = $this->getWooCommerceOrder( (int) $order_rel );
			if ( $order ) {
				$edit_url = $order->get_edit_order_url();
				echo '<a href="' . esc_url( $edit_url ) . '" target="_blank">#' . esc_html( (string) $order->get_order_number() ) . '</a>';
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
		$allowed    = array( 'new', 'triaged', 'waiting_customer', 'in_progress', 'resolved', 'closed' );

		$where  = 'WHERE network_id = %d';
		$params = array( $network_id );

		if ( '' !== $status_filter && in_array( $status_filter, $allowed, true ) ) {
			$where   .= ' AND status = %s';
			$params[] = $status_filter;
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
