<?php
/**
 * @package WP Helpdesk
 */

namespace WPHelpdesk\Interfaces\Admin\Pages;

use WPHelpdesk\Domain\Ticket\TicketStatus;
use WPHelpdesk\Interfaces\Frontend\WooCommerceAccountHelpdesk;
use WPHelpdesk\Infrastructure\Database\Schema;
use WPHelpdesk\Support\Constants;
use WPHelpdesk\Support\Helpers;

class DashboardPage {
	protected WooCommerceAccountHelpdesk $woocommerce_account_helpdesk;

	public function __construct( ?WooCommerceAccountHelpdesk $woocommerce_account_helpdesk = null ) {
		$this->woocommerce_account_helpdesk = $woocommerce_account_helpdesk ?: new WooCommerceAccountHelpdesk();
	}

	/**
	 * Render the dashboard page with live ticket stats.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( 'hd_manage_tickets' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'wp-helpdesk' ) );
		}

		$stats = $this->getStats();
		?>
		<div class="wrap hd-admin-wrap">
			<h1><?php esc_html_e( 'WP Helpdesk Dashboard', 'wp-helpdesk' ); ?></h1>

			<div class="hd-card-grid">
				<div class="hd-card">
					<h2><?php esc_html_e( 'Ticket Summary', 'wp-helpdesk' ); ?></h2>
					<table class="widefat striped" style="max-width:400px;">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Status', 'wp-helpdesk' ); ?></th>
								<th><?php esc_html_e( 'Count', 'wp-helpdesk' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $stats['by_status'] as $status => $count ) : ?>
								<tr>
									<td><?php echo esc_html( TicketStatus::label( $status ) ); ?></td>
									<td><?php echo esc_html( (string) $count ); ?></td>
								</tr>
							<?php endforeach; ?>
							<?php if ( empty( $stats['by_status'] ) ) : ?>
								<tr><td colspan="2"><?php esc_html_e( 'No tickets yet.', 'wp-helpdesk' ); ?></td></tr>
							<?php endif; ?>
						</tbody>
					</table>
					<p style="margin-top:12px;">
						<strong><?php esc_html_e( 'Total:', 'wp-helpdesk' ); ?></strong>
						<?php echo esc_html( (string) $stats['total'] ); ?>
					</p>
					<?php if ( $stats['sla_breached'] > 0 ) : ?>
						<p style="color:#b32d2e;">
							<?php
							echo esc_html(
								sprintf(
									/* translators: %d: count of SLA-breached tickets */
									__( '%d ticket(s) have breached SLA.', 'wp-helpdesk' ),
									$stats['sla_breached']
								)
							);
							?>
						</p>
					<?php endif; ?>
				</div>

				<div class="hd-card">
					<h2><?php esc_html_e( 'Quick Links', 'wp-helpdesk' ); ?></h2>
					<ul>
						<li><a href="<?php echo esc_url( network_admin_url( 'admin.php?page=wp-helpdesk-tickets' ) ); ?>"><?php esc_html_e( 'Manage tickets', 'wp-helpdesk' ); ?></a></li>
						<li><a href="<?php echo esc_url( network_admin_url( 'admin.php?page=wp-helpdesk-tickets&status_filter=new' ) ); ?>"><?php esc_html_e( 'New tickets', 'wp-helpdesk' ); ?></a></li>
						<li><a href="<?php echo esc_url( network_admin_url( 'admin.php?page=wp-helpdesk-topics' ) ); ?>"><?php esc_html_e( 'Manage topics', 'wp-helpdesk' ); ?></a></li>
						<li><a href="<?php echo esc_url( network_admin_url( 'admin.php?page=wp-helpdesk-settings' ) ); ?>"><?php esc_html_e( 'Configure settings', 'wp-helpdesk' ); ?></a></li>
					</ul>
				</div>

				<div class="hd-card">
					<h2><?php esc_html_e( 'Front-end Interfaces', 'wp-helpdesk' ); ?></h2>
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Area', 'wp-helpdesk' ); ?></th>
								<th><?php esc_html_e( 'Page', 'wp-helpdesk' ); ?></th>
								<th><?php esc_html_e( 'Path', 'wp-helpdesk' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $this->getFrontendInterfaces() as $interface ) : ?>
								<tr>
									<td><?php echo esc_html( $interface['group'] ); ?></td>
									<td><a href="<?php echo esc_url( $interface['url'] ); ?>" target="_blank" rel="noreferrer"><?php echo esc_html( $interface['label'] ); ?></a></td>
									<td><code><?php echo esc_html( $interface['path'] ); ?></code></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Resolve supported front-end interface links.
	 *
	 * @return array<int, array{group:string,label:string,url:string,path:string}>
	 */
	private function getFrontendInterfaces(): array {
		return $this->woocommerce_account_helpdesk->getInterfaceLinks();
	}

	/**
	 * Gather ticket counts grouped by status plus SLA breach count.
	 *
	 * @return array{by_status: array<string, int>, total: int, sla_breached: int}
	 */
	private function getStats(): array {
		global $wpdb;

		$table      = Schema::table( Constants::TABLE_TICKETS );
		$network_id = Helpers::getNetworkId();

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT status, COUNT(*) AS cnt FROM {$table} WHERE network_id = %d GROUP BY status",
				$network_id
			),
			ARRAY_A
		);

		$by_status = array();
		$total     = 0;
		foreach ( $rows as $row ) {
			$canonical = TicketStatus::toCanonical( (string) $row['status'] );
			if ( ! isset( $by_status[ $canonical ] ) ) {
				$by_status[ $canonical ] = 0;
			}
			$by_status[ $canonical ] += (int) $row['cnt'];
			$total                   += (int) $row['cnt'];
		}

		// SLA breach count – column may not exist on older DB, so suppress errors.
		$sla_breached = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table}
				 WHERE network_id = %d
				   AND status NOT IN ('resolved','closed')
				   AND (sla_first_response_breached = 1 OR sla_resolution_breached = 1)",
				$network_id
			)
		);

		return array(
			'by_status'    => $by_status,
			'total'        => $total,
			'sla_breached' => (int) $sla_breached,
		);
	}
}
