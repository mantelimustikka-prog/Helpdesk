<?php
/**
 * @package WP Helpdesk
 */

namespace WPHelpdesk\Interfaces\Admin\Pages;

class DashboardPage {
	/**
	 * Render the dashboard page.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( 'hd_manage_tickets' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'wp-helpdesk' ) );
		}
		?>
		<div class="wrap hd-admin-wrap">
			<h1><?php esc_html_e( 'WP Helpdesk Dashboard', 'wp-helpdesk' ); ?></h1>
			<div class="hd-card-grid">
				<div class="hd-card">
					<h2><?php esc_html_e( 'Network Overview', 'wp-helpdesk' ); ?></h2>
					<p><?php esc_html_e( 'Use the REST dashboard endpoint and future widgets to review ticket activity across the network.', 'wp-helpdesk' ); ?></p>
				</div>
				<div class="hd-card">
					<h2><?php esc_html_e( 'Quick Links', 'wp-helpdesk' ); ?></h2>
					<ul>
						<li><a href="<?php echo esc_url( network_admin_url( 'admin.php?page=wp-helpdesk-tickets' ) ); ?>"><?php esc_html_e( 'Manage tickets', 'wp-helpdesk' ); ?></a></li>
						<li><a href="<?php echo esc_url( network_admin_url( 'admin.php?page=wp-helpdesk-topics' ) ); ?>"><?php esc_html_e( 'Manage topics', 'wp-helpdesk' ); ?></a></li>
						<li><a href="<?php echo esc_url( network_admin_url( 'admin.php?page=wp-helpdesk-settings' ) ); ?>"><?php esc_html_e( 'Configure settings', 'wp-helpdesk' ); ?></a></li>
					</ul>
				</div>
			</div>
		</div>
		<?php
	}
}
