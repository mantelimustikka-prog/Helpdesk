<?php
/**
 * @package WP Helpdesk
 */

namespace WPHelpdesk\Interfaces\Admin\Pages;

class TopicsPage {
	/**
	 * Render the topics scaffold.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( 'hd_manage_topics' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'wp-helpdesk' ) );
		}
		?>
		<div class="wrap hd-admin-wrap">
			<h1><?php esc_html_e( 'Topic Management', 'wp-helpdesk' ); ?></h1>
			<div class="hd-card">
				<p><?php esc_html_e( 'TODO: full implementation. This scaffold will manage topic trees, final-step topics, and conditional transitions.', 'wp-helpdesk' ); ?></p>
			</div>
		</div>
		<?php
	}
}
