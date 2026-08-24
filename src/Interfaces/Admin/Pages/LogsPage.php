<?php
/**
 * @package WP Helpdesk
 */

namespace WPHelpdesk\Interfaces\Admin\Pages;

use WPHelpdesk\Support\Constants;
use WPHelpdesk\Support\HelpdeskLogger;

/**
 * Admin log viewer page.
 *
 * Displays structured debug log entries written by HelpdeskLogger.
 * Access is restricted to users with hd_manage_tickets capability.
 * All displayed values are sanitized/escaped before output.
 */
class LogsPage {
	/**
	 * Handle POST actions (clear log, triggered before headers are sent).
	 *
	 * @return void
	 */
	public function handlePost(): void {
		if ( ! isset( $_POST['hd_logs_action'] ) ) {
			return;
		}

		// Only act when we're on the logs page.
		$page = isset( $_GET['page'] ) ? sanitize_key( (string) $_GET['page'] ) : '';
		if ( 'wp-helpdesk-logs' !== $page ) {
			return;
		}

		if ( ! current_user_can( 'hd_manage_tickets' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'wp-helpdesk' ) );
		}

		$action = sanitize_key( (string) $_POST['hd_logs_action'] );
		$nonce  = (string) ( $_POST['hd_logs_nonce'] ?? '' );

		if ( ! wp_verify_nonce( $nonce, 'hd_logs_action' ) ) {
			wp_die( esc_html__( 'Invalid security token.', 'wp-helpdesk' ) );
		}

		if ( 'clear' === $action ) {
			HelpdeskLogger::clearLog();
		}
	}

	/**
	 * Render the log viewer page.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( 'hd_manage_tickets' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'wp-helpdesk' ) );
		}

		$logging_enabled = HelpdeskLogger::isEnabled();
		$filter_ticket   = isset( $_GET['hd_filter_ticket'] ) ? (int) $_GET['hd_filter_ticket'] : 0;
		$filter_action   = isset( $_GET['hd_filter_action'] ) ? sanitize_key( (string) $_GET['hd_filter_action'] ) : '';

		$entries = HelpdeskLogger::readEntries( 500 );

		// Apply optional filters.
		if ( $filter_ticket > 0 ) {
			$entries = array_values(
				array_filter(
					$entries,
					static function ( array $e ) use ( $filter_ticket ): bool {
						return isset( $e['ticket_id'] ) && (int) $e['ticket_id'] === $filter_ticket;
					}
				)
			);
		}

		if ( '' !== $filter_action ) {
			$entries = array_values(
				array_filter(
					$entries,
					static function ( array $e ) use ( $filter_action ): bool {
						return isset( $e['action'] ) && false !== strpos( (string) $e['action'], $filter_action );
					}
				)
			);
		}

		$log_file    = HelpdeskLogger::logFile();
		$page_url    = admin_url( 'admin.php?page=wp-helpdesk-logs' );
		$nonce_field = wp_create_nonce( 'hd_logs_action' );
		?>
		<div class="wrap hd-admin-wrap">
			<h1><?php esc_html_e( 'Helpdesk Debug Logs', 'wp-helpdesk' ); ?></h1>

			<?php if ( ! $logging_enabled ) : ?>
				<div class="notice notice-warning">
					<p>
						<?php
						printf(
							/* translators: 1: settings page link open, 2: link close */
							esc_html__( 'Debug logging is currently disabled. Enable %1$s"Log API requests"%2$s in Helpdesk → Settings → API to start recording entries.', 'wp-helpdesk' ),
							'<a href="' . esc_url( admin_url( 'admin.php?page=wp-helpdesk-settings' ) ) . '">',
							'</a>'
						);
						?>
					</p>
				</div>
			<?php endif; ?>

			<div class="hd-card" style="margin-top:16px;">
				<h2><?php esc_html_e( 'Filters', 'wp-helpdesk' ); ?></h2>
				<form method="get" action="<?php echo esc_url( $page_url ); ?>">
					<input type="hidden" name="page" value="wp-helpdesk-logs" />
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="hd_filter_ticket"><?php esc_html_e( 'Ticket ID', 'wp-helpdesk' ); ?></label></th>
							<td>
								<input
									type="number"
									id="hd_filter_ticket"
									name="hd_filter_ticket"
									value="<?php echo esc_attr( $filter_ticket > 0 ? (string) $filter_ticket : '' ); ?>"
									min="1"
									class="small-text"
								/>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="hd_filter_action"><?php esc_html_e( 'Action / Route', 'wp-helpdesk' ); ?></label></th>
							<td>
								<input
									type="text"
									id="hd_filter_action"
									name="hd_filter_action"
									value="<?php echo esc_attr( $filter_action ); ?>"
									class="regular-text"
									placeholder="<?php esc_attr_e( 'e.g. getTicket or getMessages', 'wp-helpdesk' ); ?>"
								/>
							</td>
						</tr>
					</table>
					<?php submit_button( __( 'Filter', 'wp-helpdesk' ), 'secondary', 'hd_logs_filter', false ); ?>
					<?php if ( $filter_ticket > 0 || '' !== $filter_action ) : ?>
						&nbsp;<a href="<?php echo esc_url( $page_url ); ?>" class="button"><?php esc_html_e( 'Clear Filters', 'wp-helpdesk' ); ?></a>
					<?php endif; ?>
				</form>
			</div>

			<div class="hd-card" style="margin-top:16px;">
				<h2>
					<?php
					printf(
						/* translators: %d: entry count */
						esc_html__( 'Log Entries (%d)', 'wp-helpdesk' ),
						count( $entries )
					);
					?>
				</h2>

				<?php if ( empty( $entries ) ) : ?>
					<p><?php esc_html_e( 'No log entries found.', 'wp-helpdesk' ); ?></p>
				<?php else : ?>
					<table class="widefat striped" style="font-size:12px;">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Timestamp', 'wp-helpdesk' ); ?></th>
								<th><?php esc_html_e( 'Action', 'wp-helpdesk' ); ?></th>
								<th><?php esc_html_e( 'Ticket ID', 'wp-helpdesk' ); ?></th>
								<th><?php esc_html_e( 'User ID', 'wp-helpdesk' ); ?></th>
								<th><?php esc_html_e( 'Details', 'wp-helpdesk' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $entries as $entry ) : ?>
								<?php
								$ts        = (string) ( $entry['timestamp'] ?? '' );
								$act       = (string) ( $entry['action'] ?? '' );
								$tid       = isset( $entry['ticket_id'] ) ? (int) $entry['ticket_id'] : '—';
								$uid       = isset( $entry['user_id'] ) ? (int) $entry['user_id'] : '—';
								$extra     = $entry;
								unset( $extra['timestamp'], $extra['action'], $extra['ticket_id'], $extra['user_id'] );
								$extra_str = wp_json_encode( $extra, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
								?>
								<tr>
									<td><?php echo esc_html( $ts ); ?></td>
									<td><code><?php echo esc_html( $act ); ?></code></td>
									<td><?php echo esc_html( (string) $tid ); ?></td>
									<td><?php echo esc_html( (string) $uid ); ?></td>
									<td><code style="word-break:break-all;"><?php echo esc_html( (string) $extra_str ); ?></code></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>

			<div class="hd-card" style="margin-top:16px;">
				<h2><?php esc_html_e( 'Actions', 'wp-helpdesk' ); ?></h2>
				<p>
					<strong><?php esc_html_e( 'Log file:', 'wp-helpdesk' ); ?></strong>
					<code><?php echo esc_html( $log_file ); ?></code>
				</p>
				<form method="post" action="<?php echo esc_url( $page_url ); ?>">
					<input type="hidden" name="hd_logs_action" value="clear" />
					<input type="hidden" name="hd_logs_nonce" value="<?php echo esc_attr( $nonce_field ); ?>" />
					<?php submit_button( __( 'Clear Log', 'wp-helpdesk' ), 'delete', 'hd_logs_clear', false, array( 'onclick' => "return confirm('" . esc_js( __( 'Are you sure you want to delete all log entries?', 'wp-helpdesk' ) ) . "');" ) ); ?>
				</form>
				<?php if ( file_exists( $log_file ) && is_readable( $log_file ) ) : ?>
					<p style="margin-top:8px;">
						<a
							href="<?php echo esc_url( add_query_arg( array( 'page' => 'wp-helpdesk-logs', 'hd_logs_download' => '1', '_wpnonce' => wp_create_nonce( 'hd_logs_download' ) ), admin_url( 'admin.php' ) ) ); ?>"
							class="button"
						><?php esc_html_e( 'Download Log', 'wp-helpdesk' ); ?></a>
					</p>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Stream the log file as a download if the download action is requested.
	 *
	 * Should be called early (before headers are sent) when
	 * hd_logs_download=1 appears in the query string.
	 *
	 * @return void
	 */
	public function maybeServeDownload(): void {
		if ( ! isset( $_GET['hd_logs_download'] ) ) {
			return;
		}

		// Only act when we're on the logs page.
		$page = isset( $_GET['page'] ) ? sanitize_key( (string) $_GET['page'] ) : '';
		if ( 'wp-helpdesk-logs' !== $page ) {
			return;
		}

		if ( ! current_user_can( 'hd_manage_tickets' ) ) {
			wp_die( esc_html__( 'You do not have permission to download this file.', 'wp-helpdesk' ) );
		}

		$nonce = (string) ( $_GET['_wpnonce'] ?? '' );
		if ( ! wp_verify_nonce( $nonce, 'hd_logs_download' ) ) {
			wp_die( esc_html__( 'Invalid security token.', 'wp-helpdesk' ) );
		}

		$file = HelpdeskLogger::logFile();
		if ( ! file_exists( $file ) || ! is_readable( $file ) ) {
			wp_die( esc_html__( 'Log file not found.', 'wp-helpdesk' ) );
		}

		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="helpdesk-debug.log"' );
		header( 'Content-Length: ' . filesize( $file ) );
		readfile( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		exit;
	}
}
