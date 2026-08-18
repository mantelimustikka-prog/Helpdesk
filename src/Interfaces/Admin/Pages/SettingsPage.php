<?php
/**
 * @package WP Helpdesk
 */

namespace WPHelpdesk\Interfaces\Admin\Pages;

use WPHelpdesk\Support\Constants;
use WPHelpdesk\Support\Helpers;

class SettingsPage {
	/**
	 * Handle settings form submissions.
	 *
	 * @return void
	 */
	public function handlePost(): void {
		if ( 'wp-helpdesk-settings' !== ( $_GET['page'] ?? '' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		if ( 'POST' !== strtoupper( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
			return;
		}

		if ( ! current_user_can( 'hd_manage_settings' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage helpdesk settings.', 'wp-helpdesk' ) );
		}

		$nonce = isset( $_POST['hd_settings_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['hd_settings_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'hd_settings_save' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'wp-helpdesk' ) );
		}

		// Only save email layout fields when on the email-layout tab.
		$tab = isset( $_POST['hd_current_tab'] ) ? sanitize_key( wp_unslash( $_POST['hd_current_tab'] ) ) : 'general';

		if ( 'email-layout' === $tab ) {
			$header = isset( $_POST['hd_email_header'] ) ? Helpers::sanitizeRichText( wp_unslash( $_POST['hd_email_header'] ) ) : '';
			$footer = isset( $_POST['hd_email_footer'] ) ? Helpers::sanitizeRichText( wp_unslash( $_POST['hd_email_footer'] ) ) : '';

			update_site_option( Constants::OPTION_EMAIL_HEADER_HTML, $header );
			update_site_option( Constants::OPTION_EMAIL_FOOTER_HTML, $footer );

			add_settings_error(
				'wp_helpdesk_settings',
				'wp_helpdesk_settings_saved',
				__( 'Email Header & Footer settings saved.', 'wp-helpdesk' ),
				'updated'
			);
		}
		// TODO: add save logic for General and Integrations tabs in a future iteration.
	}

	/**
	 * Render the settings page.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( 'hd_manage_settings' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'wp-helpdesk' ) );
		}

		$tab    = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general';
		$header = (string) get_site_option( Constants::OPTION_EMAIL_HEADER_HTML, '' );
		$footer = (string) get_site_option( Constants::OPTION_EMAIL_FOOTER_HTML, '' );

		if ( 'email-layout' === $tab ) {
			$this->enqueueEditor();
		}

		settings_errors( 'wp_helpdesk_settings' );
		?>
		<div class="wrap hd-admin-wrap">
			<h1><?php esc_html_e( 'WP Helpdesk Settings', 'wp-helpdesk' ); ?></h1>
			<nav class="nav-tab-wrapper">
				<a class="nav-tab <?php echo 'general' === $tab ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( network_admin_url( 'admin.php?page=wp-helpdesk-settings&tab=general' ) ); ?>"><?php esc_html_e( 'General', 'wp-helpdesk' ); ?></a>
				<a class="nav-tab <?php echo 'email-layout' === $tab ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( network_admin_url( 'admin.php?page=wp-helpdesk-settings&tab=email-layout' ) ); ?>"><?php esc_html_e( 'Email Header & Footer', 'wp-helpdesk' ); ?></a>
				<a class="nav-tab <?php echo 'integrations' === $tab ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( network_admin_url( 'admin.php?page=wp-helpdesk-settings&tab=integrations' ) ); ?>"><?php esc_html_e( 'Integrations', 'wp-helpdesk' ); ?></a>
			</nav>

			<?php if ( 'email-layout' === $tab ) : ?>
				<form method="post">
					<?php wp_nonce_field( 'hd_settings_save', 'hd_settings_nonce' ); ?>
					<input type="hidden" name="hd_current_tab" value="email-layout">
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="hd_email_header"><?php esc_html_e( 'Email Header', 'wp-helpdesk' ); ?></label></th>
							<td>
								<textarea id="hd_email_header" name="hd_email_header" rows="10" class="large-text code"><?php echo esc_textarea( $header ); ?></textarea>
								<p class="description"><?php esc_html_e( 'Optional HTML shown before all outbound notifications.', 'wp-helpdesk' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="hd_email_footer"><?php esc_html_e( 'Email Footer', 'wp-helpdesk' ); ?></label></th>
							<td>
								<textarea id="hd_email_footer" name="hd_email_footer" rows="10" class="large-text code"><?php echo esc_textarea( $footer ); ?></textarea>
								<p class="description"><?php esc_html_e( 'Optional HTML shown after all outbound notifications.', 'wp-helpdesk' ); ?></p>
							</td>
						</tr>
					</table>
					<?php submit_button( __( 'Save Settings', 'wp-helpdesk' ) ); ?>
				</form>
			<?php elseif ( 'integrations' === $tab ) : ?>
				<div class="hd-card">
					<h2><?php esc_html_e( 'Integrations', 'wp-helpdesk' ); ?></h2>
					<p><?php esc_html_e( 'Configure FCM keys, Android app access, and other network-wide integrations here in a future iteration.', 'wp-helpdesk' ); ?></p>
				</div>
			<?php else : ?>
				<div class="hd-card">
					<h2><?php esc_html_e( 'General Settings', 'wp-helpdesk' ); ?></h2>
					<p><?php esc_html_e( 'SLA defaults, pagination, and ticket numbering are stored as network options and can be expanded here.', 'wp-helpdesk' ); ?></p>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Enqueue bundled CKEditor for email layout editing.
	 *
	 * @return void
	 */
	protected function enqueueEditor(): void {
		$editor_path = Helpers::pluginPath( 'assets/vendor/ckeditor/ckeditor.js' );

		if ( file_exists( $editor_path ) ) {
			wp_enqueue_script(
				'wp-helpdesk-ckeditor',
				Helpers::pluginUrl( 'assets/vendor/ckeditor/ckeditor.js' ),
				array(),
				HD_VERSION,
				true
			);

			wp_add_inline_script(
				'wp-helpdesk-ckeditor',
				"document.addEventListener('DOMContentLoaded', function () { if (window.CKEDITOR) { CKEDITOR.replace('hd_email_header'); CKEDITOR.replace('hd_email_footer'); } });"
			);
		} else {
			add_settings_error(
				'wp_helpdesk_settings',
				'wp_helpdesk_ckeditor_missing',
				__( 'CKEditor is not bundled yet. Download CKEditor 4 and place it in assets/vendor/ckeditor/.', 'wp-helpdesk' ),
				'warning'
			);
		}
	}
}
