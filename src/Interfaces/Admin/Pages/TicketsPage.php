<?php
/**
 * @package WP Helpdesk
 */

namespace WPHelpdesk\Interfaces\Admin\Pages;

class TicketsPage {
	/**
	 * Render the tickets scaffold.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( 'hd_manage_tickets' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'wp-helpdesk' ) );
		}
		?>
		<div class="wrap hd-admin-wrap">
			<h1><?php esc_html_e( 'Ticket Queue', 'wp-helpdesk' ); ?></h1>
			<div class="hd-card">
				<p><?php esc_html_e( 'TODO: full implementation. This scaffold will list tickets, filters, assignment actions, and reply tools for network agents.', 'wp-helpdesk' ); ?></p>
			</div>
		</div>
		<?php
	}
}
