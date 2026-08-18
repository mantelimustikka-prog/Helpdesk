<?php
/**
 * @package WP Helpdesk
 */

namespace WPHelpdesk\Interfaces\Admin\Pages;

use WPHelpdesk\Support\Constants;
use WPHelpdesk\Support\Helpers;

class SettingsPage {

	// Sentinel value displayed when a secret already exists but should not be revealed.
	private const SECRET_PLACEHOLDER = '••••••••';

	// Allowed enum values for strict validation.
	private const VALID_STATUSES        = array( 'open', 'pending', 'resolved', 'closed' );
	private const VALID_PRIORITIES      = array( 'low', 'normal', 'high', 'urgent' );
	private const VALID_ASSIGN_MODES    = array( 'none', 'round_robin', 'least_open' );
	private const VALID_TIMEZONE_MODES  = array( 'network', 'site', 'utc' );
	private const VALID_DATE_FORMATS    = array( 'wp_default', 'iso8601' );
	private const VALID_FCM_MODES       = array( 'legacy', 'v1' );
	private const VALID_PUSH_EVENTS     = array( 'ticket_created', 'ticket_replied', 'status_changed' );

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

		$tab = isset( $_POST['hd_current_tab'] ) ? sanitize_key( wp_unslash( $_POST['hd_current_tab'] ) ) : 'general';

		if ( 'email-layout' === $tab ) {
			$this->saveEmailLayout();
		} elseif ( 'general' === $tab ) {
			$this->saveGeneral();
		} elseif ( 'integrations' === $tab ) {
			$this->saveIntegrations();
		}
	}

	// -------------------------------------------------------------------------
	// Save handlers
	// -------------------------------------------------------------------------

	/**
	 * Save Email Header & Footer layout tab.
	 *
	 * @return void
	 */
	private function saveEmailLayout(): void {
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

	/**
	 * Save General tab options.
	 *
	 * @return void
	 */
	private function saveGeneral(): void {
		$errors = array();

		// Ticket number start.
		$start = isset( $_POST['hd_general_ticket_number_start'] ) ? (int) $_POST['hd_general_ticket_number_start'] : 1000;
		if ( $start < 1 || $start > PHP_INT_MAX ) {
			$errors[] = __( 'Ticket number start must be at least 1.', 'wp-helpdesk' );
		}

		// Ticket number increment.
		$inc = isset( $_POST['hd_general_ticket_number_increment'] ) ? (int) $_POST['hd_general_ticket_number_increment'] : 1;
		if ( $inc < 1 || $inc > 10000 ) {
			$errors[] = __( 'Ticket number increment must be between 1 and 10,000.', 'wp-helpdesk' );
		}

		// Default status.
		$status_raw = isset( $_POST['hd_general_default_status'] ) ? sanitize_key( wp_unslash( $_POST['hd_general_default_status'] ) ) : 'open';
		if ( ! in_array( $status_raw, self::VALID_STATUSES, true ) ) {
			$errors[] = __( 'Invalid default status value.', 'wp-helpdesk' );
			$status_raw = 'open';
		}

		// Default priority.
		$priority_raw = isset( $_POST['hd_general_default_priority'] ) ? sanitize_key( wp_unslash( $_POST['hd_general_default_priority'] ) ) : 'normal';
		if ( ! in_array( $priority_raw, self::VALID_PRIORITIES, true ) ) {
			$errors[] = __( 'Invalid default priority value.', 'wp-helpdesk' );
			$priority_raw = 'normal';
		}

		// Booleans.
		$allow_guest   = isset( $_POST['hd_general_allow_guest_tickets'] ) ? 1 : 0;
		$require_topic = isset( $_POST['hd_general_require_topic'] ) ? 1 : 0;

		// Auto-assign mode.
		$assign_mode = isset( $_POST['hd_general_auto_assign_mode'] ) ? sanitize_key( wp_unslash( $_POST['hd_general_auto_assign_mode'] ) ) : 'none';
		if ( ! in_array( $assign_mode, self::VALID_ASSIGN_MODES, true ) ) {
			$errors[] = __( 'Invalid auto-assign mode.', 'wp-helpdesk' );
			$assign_mode = 'none';
		}

		// Timezone mode.
		$tz_mode = isset( $_POST['hd_general_timezone_mode'] ) ? sanitize_key( wp_unslash( $_POST['hd_general_timezone_mode'] ) ) : 'network';
		if ( ! in_array( $tz_mode, self::VALID_TIMEZONE_MODES, true ) ) {
			$errors[] = __( 'Invalid timezone mode.', 'wp-helpdesk' );
			$tz_mode = 'network';
		}

		// Date format.
		$date_fmt = isset( $_POST['hd_general_date_format'] ) ? sanitize_key( wp_unslash( $_POST['hd_general_date_format'] ) ) : 'wp_default';
		if ( ! in_array( $date_fmt, self::VALID_DATE_FORMATS, true ) ) {
			$errors[] = __( 'Invalid date format value.', 'wp-helpdesk' );
			$date_fmt = 'wp_default';
		}

		if ( ! empty( $errors ) ) {
			foreach ( $errors as $error ) {
				add_settings_error( 'wp_helpdesk_settings', 'wp_helpdesk_settings_error', $error, 'error' );
			}
			return;
		}

		update_site_option( Constants::OPTION_GENERAL_TICKET_NUMBER_START, $start );
		update_site_option( Constants::OPTION_GENERAL_TICKET_NUMBER_INC, $inc );
		update_site_option( Constants::OPTION_GENERAL_DEFAULT_STATUS, $status_raw );
		update_site_option( Constants::OPTION_GENERAL_DEFAULT_PRIORITY, $priority_raw );
		update_site_option( Constants::OPTION_GENERAL_ALLOW_GUEST, $allow_guest );
		update_site_option( Constants::OPTION_GENERAL_REQUIRE_TOPIC, $require_topic );
		update_site_option( Constants::OPTION_GENERAL_AUTO_ASSIGN_MODE, $assign_mode );
		update_site_option( Constants::OPTION_GENERAL_TIMEZONE_MODE, $tz_mode );
		update_site_option( Constants::OPTION_GENERAL_DATE_FORMAT, $date_fmt );

		add_settings_error(
			'wp_helpdesk_settings',
			'wp_helpdesk_settings_saved',
			__( 'General settings saved.', 'wp-helpdesk' ),
			'updated'
		);
	}

	/**
	 * Save Integrations tab options.
	 *
	 * @return void
	 */
	private function saveIntegrations(): void {
		$errors = array();

		// --- Email delivery --------------------------------------------------
		$from_name = isset( $_POST['hd_email_from_name'] ) ? sanitize_text_field( wp_unslash( $_POST['hd_email_from_name'] ) ) : '';
		$from_addr = isset( $_POST['hd_email_from_address'] ) ? sanitize_email( wp_unslash( $_POST['hd_email_from_address'] ) ) : '';
		$reply_to  = isset( $_POST['hd_email_reply_to_address'] ) ? sanitize_email( wp_unslash( $_POST['hd_email_reply_to_address'] ) ) : '';

		if ( '' !== $from_addr && ! is_email( $from_addr ) ) {
			$errors[] = __( 'From Address is not a valid email address.', 'wp-helpdesk' );
		}

		if ( '' !== $reply_to && ! is_email( $reply_to ) ) {
			$errors[] = __( 'Reply-To Address is not a valid email address.', 'wp-helpdesk' );
		}

		$header_enabled = isset( $_POST['hd_email_header_enabled'] ) ? 1 : 0;
		$footer_enabled = isset( $_POST['hd_email_footer_enabled'] ) ? 1 : 0;

		// --- Push / FCM ------------------------------------------------------
		$push_enabled = isset( $_POST['hd_push_enabled'] ) ? 1 : 0;

		$fcm_mode = isset( $_POST['hd_fcm_mode'] ) ? sanitize_key( wp_unslash( $_POST['hd_fcm_mode'] ) ) : 'legacy';
		if ( ! in_array( $fcm_mode, self::VALID_FCM_MODES, true ) ) {
			$errors[] = __( 'Invalid FCM mode.', 'wp-helpdesk' );
			$fcm_mode = 'legacy';
		}

		// FCM server key: only update when a non-placeholder value is submitted.
		$fcm_key_raw   = isset( $_POST['hd_fcm_server_key'] ) ? trim( wp_unslash( $_POST['hd_fcm_server_key'] ) ) : '';
		$fcm_key_store = null; // null means "do not update".
		if ( '' !== $fcm_key_raw && self::SECRET_PLACEHOLDER !== $fcm_key_raw ) {
			$fcm_key_store = sanitize_text_field( $fcm_key_raw );
		}

		$fcm_project_id = isset( $_POST['hd_fcm_project_id'] ) ? sanitize_text_field( wp_unslash( $_POST['hd_fcm_project_id'] ) ) : '';

		// Service account JSON: validate if provided.
		$sa_json_raw   = isset( $_POST['hd_fcm_service_account_json'] ) ? trim( wp_unslash( $_POST['hd_fcm_service_account_json'] ) ) : '';
		$sa_json_store = null;
		if ( '' !== $sa_json_raw && self::SECRET_PLACEHOLDER !== $sa_json_raw ) {
			if ( null === json_decode( $sa_json_raw ) && JSON_ERROR_NONE !== json_last_error() ) {
				$errors[] = __( 'FCM Service Account JSON is not valid JSON.', 'wp-helpdesk' );
			} else {
				$sa_json_store = $sa_json_raw; // Store raw (validated) JSON; not re-encoded.
			}
		}

		// Push events.
		$push_events_raw = isset( $_POST['hd_push_ticket_events'] ) && is_array( $_POST['hd_push_ticket_events'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
			? array_map( 'sanitize_key', wp_unslash( $_POST['hd_push_ticket_events'] ) )
			: array();
		$push_events = array_values( array_intersect( $push_events_raw, self::VALID_PUSH_EVENTS ) );

		// --- Android / API ---------------------------------------------------
		$api_enabled       = isset( $_POST['hd_api_enabled'] ) ? 1 : 0;
		$api_require_ap    = isset( $_POST['hd_api_require_application_passwords'] ) ? 1 : 0;

		$rate_limit = isset( $_POST['hd_api_rate_limit_per_minute'] ) ? (int) $_POST['hd_api_rate_limit_per_minute'] : 60;
		if ( $rate_limit < 1 || $rate_limit > 5000 ) {
			$errors[] = __( 'API rate limit must be between 1 and 5,000 requests per minute.', 'wp-helpdesk' );
		}

		$api_log = isset( $_POST['hd_api_log_requests'] ) ? 1 : 0;

		$origins_raw = isset( $_POST['hd_api_allowed_origins'] ) ? wp_unslash( $_POST['hd_api_allowed_origins'] ) : '';
		$origins_lines = array_filter( array_map( 'trim', explode( "\n", $origins_raw ) ) );
		$origins_clean = array_values( array_map( 'esc_url_raw', $origins_lines ) );
		$origins_store = implode( "\n", $origins_clean );

		// --- Bail on errors --------------------------------------------------
		if ( ! empty( $errors ) ) {
			foreach ( $errors as $error ) {
				add_settings_error( 'wp_helpdesk_settings', 'wp_helpdesk_settings_error', $error, 'error' );
			}
			return;
		}

		// --- Persist ---------------------------------------------------------
		update_site_option( Constants::OPTION_EMAIL_FROM_NAME, $from_name );
		update_site_option( Constants::OPTION_EMAIL_FROM_ADDRESS, $from_addr );
		update_site_option( Constants::OPTION_EMAIL_REPLY_TO, $reply_to );
		update_site_option( Constants::OPTION_EMAIL_HEADER_ENABLED, $header_enabled );
		update_site_option( Constants::OPTION_EMAIL_FOOTER_ENABLED, $footer_enabled );

		update_site_option( Constants::OPTION_PUSH_ENABLED, $push_enabled );
		update_site_option( Constants::OPTION_FCM_MODE, $fcm_mode );
		update_site_option( Constants::OPTION_FCM_PROJECT_ID, $fcm_project_id );
		update_site_option( Constants::OPTION_PUSH_TICKET_EVENTS, $push_events );

		if ( null !== $fcm_key_store ) {
			update_site_option( Constants::OPTION_FCM_SERVER_KEY, $fcm_key_store );
		}

		if ( null !== $sa_json_store ) {
			update_site_option( Constants::OPTION_FCM_SERVICE_ACCOUNT_JSON, $sa_json_store );
		}

		update_site_option( Constants::OPTION_API_ENABLED, $api_enabled );
		update_site_option( Constants::OPTION_API_REQUIRE_APP_PASSWORDS, $api_require_ap );
		update_site_option( Constants::OPTION_API_RATE_LIMIT, $rate_limit );
		update_site_option( Constants::OPTION_API_LOG_REQUESTS, $api_log );
		update_site_option( Constants::OPTION_API_ALLOWED_ORIGINS, $origins_store );

		add_settings_error(
			'wp_helpdesk_settings',
			'wp_helpdesk_settings_saved',
			__( 'Integration settings saved.', 'wp-helpdesk' ),
			'updated'
		);
	}

	// -------------------------------------------------------------------------
	// Render
	// -------------------------------------------------------------------------

	/**
	 * Render the settings page.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( 'hd_manage_settings' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'wp-helpdesk' ) );
		}

		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

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
				<?php $this->renderEmailLayout(); ?>
			<?php elseif ( 'integrations' === $tab ) : ?>
				<?php $this->renderIntegrations(); ?>
			<?php else : ?>
				<?php $this->renderGeneral(); ?>
			<?php endif; ?>
		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// Tab render helpers
	// -------------------------------------------------------------------------

	/**
	 * Render the General settings tab.
	 *
	 * @return void
	 */
	private function renderGeneral(): void {
		$start       = (int) get_site_option( Constants::OPTION_GENERAL_TICKET_NUMBER_START, 1000 );
		$inc         = (int) get_site_option( Constants::OPTION_GENERAL_TICKET_NUMBER_INC, 1 );
		$def_status  = (string) get_site_option( Constants::OPTION_GENERAL_DEFAULT_STATUS, 'open' );
		$def_prio    = (string) get_site_option( Constants::OPTION_GENERAL_DEFAULT_PRIORITY, 'normal' );
		$allow_guest = (int) get_site_option( Constants::OPTION_GENERAL_ALLOW_GUEST, 1 );
		$req_topic   = (int) get_site_option( Constants::OPTION_GENERAL_REQUIRE_TOPIC, 1 );
		$assign_mode = (string) get_site_option( Constants::OPTION_GENERAL_AUTO_ASSIGN_MODE, 'none' );
		$tz_mode     = (string) get_site_option( Constants::OPTION_GENERAL_TIMEZONE_MODE, 'network' );
		$date_fmt    = (string) get_site_option( Constants::OPTION_GENERAL_DATE_FORMAT, 'wp_default' );

		$counter     = (int) get_site_option( Constants::OPTION_TICKET_COUNTER, $start );
		$next_ticket = max( $counter, $start );
		?>
		<form method="post">
			<?php wp_nonce_field( 'hd_settings_save', 'hd_settings_nonce' ); ?>
			<input type="hidden" name="hd_current_tab" value="general">

			<h2><?php esc_html_e( 'Ticket Defaults', 'wp-helpdesk' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="hd_general_default_status"><?php esc_html_e( 'Default Status', 'wp-helpdesk' ); ?></label></th>
					<td>
						<select id="hd_general_default_status" name="hd_general_default_status">
							<?php foreach ( self::VALID_STATUSES as $s ) : ?>
								<option value="<?php echo esc_attr( $s ); ?>" <?php selected( $def_status, $s ); ?>><?php echo esc_html( ucfirst( $s ) ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="hd_general_default_priority"><?php esc_html_e( 'Default Priority', 'wp-helpdesk' ); ?></label></th>
					<td>
						<select id="hd_general_default_priority" name="hd_general_default_priority">
							<?php foreach ( self::VALID_PRIORITIES as $p ) : ?>
								<option value="<?php echo esc_attr( $p ); ?>" <?php selected( $def_prio, $p ); ?>><?php echo esc_html( ucfirst( $p ) ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Require Topic', 'wp-helpdesk' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="hd_general_require_topic" value="1" <?php checked( $req_topic, 1 ); ?>>
							<?php esc_html_e( 'Require submitters to select a topic', 'wp-helpdesk' ); ?>
						</label>
					</td>
				</tr>
			</table>

			<h2><?php esc_html_e( 'Ticket Numbering', 'wp-helpdesk' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="hd_general_ticket_number_start"><?php esc_html_e( 'Start Number', 'wp-helpdesk' ); ?></label></th>
					<td>
						<input type="number" id="hd_general_ticket_number_start" name="hd_general_ticket_number_start" value="<?php echo esc_attr( (string) $start ); ?>" min="1" class="small-text">
						<p class="description"><?php esc_html_e( 'Minimum ticket number (used when counter resets or for new installs).', 'wp-helpdesk' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="hd_general_ticket_number_increment"><?php esc_html_e( 'Increment Step', 'wp-helpdesk' ); ?></label></th>
					<td>
						<input type="number" id="hd_general_ticket_number_increment" name="hd_general_ticket_number_increment" value="<?php echo esc_attr( (string) $inc ); ?>" min="1" max="10000" class="small-text">
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Next Ticket Preview', 'wp-helpdesk' ); ?></th>
					<td>
						<span class="description"><?php echo esc_html( sprintf( __( 'Next ticket number: #%d', 'wp-helpdesk' ), $next_ticket ) ); ?></span>
					</td>
				</tr>
			</table>

			<h2><?php esc_html_e( 'Submission Rules', 'wp-helpdesk' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Guest Tickets', 'wp-helpdesk' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="hd_general_allow_guest_tickets" value="1" <?php checked( $allow_guest, 1 ); ?>>
							<?php esc_html_e( 'Allow non-logged-in users to submit tickets', 'wp-helpdesk' ); ?>
						</label>
					</td>
				</tr>
			</table>

			<h2><?php esc_html_e( 'Assignment', 'wp-helpdesk' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="hd_general_auto_assign_mode"><?php esc_html_e( 'Auto-Assign Mode', 'wp-helpdesk' ); ?></label></th>
					<td>
						<select id="hd_general_auto_assign_mode" name="hd_general_auto_assign_mode">
							<option value="none" <?php selected( $assign_mode, 'none' ); ?>><?php esc_html_e( 'None (manual assignment)', 'wp-helpdesk' ); ?></option>
							<option value="round_robin" <?php selected( $assign_mode, 'round_robin' ); ?>><?php esc_html_e( 'Round Robin', 'wp-helpdesk' ); ?></option>
							<option value="least_open" <?php selected( $assign_mode, 'least_open' ); ?>><?php esc_html_e( 'Least Open Tickets', 'wp-helpdesk' ); ?></option>
						</select>
						<?php if ( 'none' !== $assign_mode ) : ?>
							<p class="description"><?php esc_html_e( 'Note: Auto-assignment engine is reserved for a future release. This setting will be applied when the feature becomes active.', 'wp-helpdesk' ); ?></p>
						<?php endif; ?>
					</td>
				</tr>
			</table>

			<h2><?php esc_html_e( 'Regional / Display', 'wp-helpdesk' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="hd_general_timezone_mode"><?php esc_html_e( 'Timezone Mode', 'wp-helpdesk' ); ?></label></th>
					<td>
						<select id="hd_general_timezone_mode" name="hd_general_timezone_mode">
							<option value="network" <?php selected( $tz_mode, 'network' ); ?>><?php esc_html_e( 'Network timezone', 'wp-helpdesk' ); ?></option>
							<option value="site" <?php selected( $tz_mode, 'site' ); ?>><?php esc_html_e( 'Per-site timezone', 'wp-helpdesk' ); ?></option>
							<option value="utc" <?php selected( $tz_mode, 'utc' ); ?>><?php esc_html_e( 'UTC', 'wp-helpdesk' ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="hd_general_date_format"><?php esc_html_e( 'Date Format', 'wp-helpdesk' ); ?></label></th>
					<td>
						<select id="hd_general_date_format" name="hd_general_date_format">
							<option value="wp_default" <?php selected( $date_fmt, 'wp_default' ); ?>><?php esc_html_e( 'WordPress default', 'wp-helpdesk' ); ?></option>
							<option value="iso8601" <?php selected( $date_fmt, 'iso8601' ); ?>><?php esc_html_e( 'ISO 8601 (YYYY-MM-DD)', 'wp-helpdesk' ); ?></option>
						</select>
					</td>
				</tr>
			</table>

			<?php submit_button( __( 'Save Settings', 'wp-helpdesk' ) ); ?>
		</form>
		<?php
	}

	/**
	 * Render the Email Header & Footer tab.
	 *
	 * @return void
	 */
	private function renderEmailLayout(): void {
		$header = (string) get_site_option( Constants::OPTION_EMAIL_HEADER_HTML, '' );
		$footer = (string) get_site_option( Constants::OPTION_EMAIL_FOOTER_HTML, '' );
		?>
		<form method="post">
			<?php wp_nonce_field( 'hd_settings_save', 'hd_settings_nonce' ); ?>
			<input type="hidden" name="hd_current_tab" value="email-layout">
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="hd_email_header"><?php esc_html_e( 'Email Header', 'wp-helpdesk' ); ?></label></th>
					<td>
						<textarea id="hd_email_header" name="hd_email_header" rows="10" class="large-text code"><?php echo esc_textarea( $header ); ?></textarea>
						<p class="description"><?php esc_html_e( 'Optional HTML shown before all outbound notifications. Enable/disable on the Integrations tab.', 'wp-helpdesk' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="hd_email_footer"><?php esc_html_e( 'Email Footer', 'wp-helpdesk' ); ?></label></th>
					<td>
						<textarea id="hd_email_footer" name="hd_email_footer" rows="10" class="large-text code"><?php echo esc_textarea( $footer ); ?></textarea>
						<p class="description"><?php esc_html_e( 'Optional HTML shown after all outbound notifications. Enable/disable on the Integrations tab.', 'wp-helpdesk' ); ?></p>
					</td>
				</tr>
			</table>
			<?php submit_button( __( 'Save Settings', 'wp-helpdesk' ) ); ?>
		</form>
		<?php
	}

	/**
	 * Render the Integrations tab.
	 *
	 * @return void
	 */
	private function renderIntegrations(): void {
		$from_name      = (string) get_site_option( Constants::OPTION_EMAIL_FROM_NAME, '' );
		$from_addr      = (string) get_site_option( Constants::OPTION_EMAIL_FROM_ADDRESS, '' );
		$reply_to       = (string) get_site_option( Constants::OPTION_EMAIL_REPLY_TO, '' );
		$header_enabled = (int) get_site_option( Constants::OPTION_EMAIL_HEADER_ENABLED, 1 );
		$footer_enabled = (int) get_site_option( Constants::OPTION_EMAIL_FOOTER_ENABLED, 1 );

		$push_enabled  = (int) get_site_option( Constants::OPTION_PUSH_ENABLED, 0 );
		$fcm_mode      = (string) get_site_option( Constants::OPTION_FCM_MODE, 'legacy' );
		$fcm_key_set   = '' !== (string) get_site_option( Constants::OPTION_FCM_SERVER_KEY, '' );
		$fcm_project   = (string) get_site_option( Constants::OPTION_FCM_PROJECT_ID, '' );
		$sa_json_set   = '' !== (string) get_site_option( Constants::OPTION_FCM_SERVICE_ACCOUNT_JSON, '' );
		$push_events   = (array) get_site_option( Constants::OPTION_PUSH_TICKET_EVENTS, array() );

		$api_enabled    = (int) get_site_option( Constants::OPTION_API_ENABLED, 1 );
		$api_require_ap = (int) get_site_option( Constants::OPTION_API_REQUIRE_APP_PASSWORDS, 1 );
		$rate_limit     = (int) get_site_option( Constants::OPTION_API_RATE_LIMIT, 60 );
		$api_log        = (int) get_site_option( Constants::OPTION_API_LOG_REQUESTS, 0 );
		$origins        = (string) get_site_option( Constants::OPTION_API_ALLOWED_ORIGINS, '' );
		?>
		<form method="post">
			<?php wp_nonce_field( 'hd_settings_save', 'hd_settings_nonce' ); ?>
			<input type="hidden" name="hd_current_tab" value="integrations">

			<!-- Email Delivery -->
			<h2><?php esc_html_e( 'Email Delivery', 'wp-helpdesk' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="hd_email_from_name"><?php esc_html_e( 'From Name', 'wp-helpdesk' ); ?></label></th>
					<td>
						<input type="text" id="hd_email_from_name" name="hd_email_from_name" value="<?php echo esc_attr( $from_name ); ?>" class="regular-text">
						<p class="description"><?php esc_html_e( 'Sender name for outbound notifications.', 'wp-helpdesk' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="hd_email_from_address"><?php esc_html_e( 'From Address', 'wp-helpdesk' ); ?></label></th>
					<td>
						<input type="email" id="hd_email_from_address" name="hd_email_from_address" value="<?php echo esc_attr( $from_addr ); ?>" class="regular-text">
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="hd_email_reply_to_address"><?php esc_html_e( 'Reply-To Address', 'wp-helpdesk' ); ?></label></th>
					<td>
						<input type="email" id="hd_email_reply_to_address" name="hd_email_reply_to_address" value="<?php echo esc_attr( $reply_to ); ?>" class="regular-text">
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Email Header', 'wp-helpdesk' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="hd_email_header_enabled" value="1" <?php checked( $header_enabled, 1 ); ?>>
							<?php esc_html_e( 'Prepend email header to outbound notifications', 'wp-helpdesk' ); ?>
						</label>
						<p class="description">
							<a href="<?php echo esc_url( network_admin_url( 'admin.php?page=wp-helpdesk-settings&tab=email-layout' ) ); ?>"><?php esc_html_e( 'Edit email header HTML →', 'wp-helpdesk' ); ?></a>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Email Footer', 'wp-helpdesk' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="hd_email_footer_enabled" value="1" <?php checked( $footer_enabled, 1 ); ?>>
							<?php esc_html_e( 'Append email footer to outbound notifications', 'wp-helpdesk' ); ?>
						</label>
						<p class="description">
							<a href="<?php echo esc_url( network_admin_url( 'admin.php?page=wp-helpdesk-settings&tab=email-layout' ) ); ?>"><?php esc_html_e( 'Edit email footer HTML →', 'wp-helpdesk' ); ?></a>
						</p>
					</td>
				</tr>
			</table>

			<!-- Push / FCM -->
			<h2><?php esc_html_e( 'Push Notifications (FCM)', 'wp-helpdesk' ); ?></h2>
			<div class="notice notice-warning inline">
				<p><?php esc_html_e( 'Important: The legacy FCM HTTP API (v1 legacy key) was deprecated by Google in June 2024. Full FCM v1 (OAuth service account) support is planned. The "legacy" mode below will not deliver push notifications until updated.', 'wp-helpdesk' ); ?></p>
			</div>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Enable Push', 'wp-helpdesk' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="hd_push_enabled" value="1" <?php checked( $push_enabled, 1 ); ?>>
							<?php esc_html_e( 'Enable push notifications', 'wp-helpdesk' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="hd_fcm_mode"><?php esc_html_e( 'FCM Mode', 'wp-helpdesk' ); ?></label></th>
					<td>
						<select id="hd_fcm_mode" name="hd_fcm_mode">
							<option value="legacy" <?php selected( $fcm_mode, 'legacy' ); ?>><?php esc_html_e( 'Legacy (placeholder – not functional)', 'wp-helpdesk' ); ?></option>
							<option value="v1" <?php selected( $fcm_mode, 'v1' ); ?>><?php esc_html_e( 'FCM v1 (OAuth service account – future)', 'wp-helpdesk' ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="hd_fcm_server_key"><?php esc_html_e( 'FCM Server Key', 'wp-helpdesk' ); ?></label></th>
					<td>
						<input type="password" id="hd_fcm_server_key" name="hd_fcm_server_key"
							value="<?php echo $fcm_key_set ? esc_attr( self::SECRET_PLACEHOLDER ) : ''; ?>"
							class="regular-text" autocomplete="new-password">
						<p class="description">
							<?php
							if ( $fcm_key_set ) {
								esc_html_e( 'A key is stored. Enter a new value to replace it, or leave unchanged.', 'wp-helpdesk' );
							} else {
								esc_html_e( 'Enter your FCM server key (legacy mode).', 'wp-helpdesk' );
							}
							?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="hd_fcm_project_id"><?php esc_html_e( 'FCM Project ID', 'wp-helpdesk' ); ?></label></th>
					<td>
						<input type="text" id="hd_fcm_project_id" name="hd_fcm_project_id" value="<?php echo esc_attr( $fcm_project ); ?>" class="regular-text">
						<p class="description"><?php esc_html_e( 'Required for FCM v1 mode.', 'wp-helpdesk' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="hd_fcm_service_account_json"><?php esc_html_e( 'Service Account JSON', 'wp-helpdesk' ); ?></label></th>
					<td>
						<textarea id="hd_fcm_service_account_json" name="hd_fcm_service_account_json" rows="5" class="large-text code"><?php echo $sa_json_set ? esc_textarea( self::SECRET_PLACEHOLDER ) : ''; ?></textarea>
						<p class="description">
							<?php
							if ( $sa_json_set ) {
								esc_html_e( 'A service account JSON is stored. Paste a new JSON to replace it, or leave unchanged.', 'wp-helpdesk' );
							} else {
								esc_html_e( 'Paste the Firebase service account JSON for FCM v1 mode (optional).', 'wp-helpdesk' );
							}
							?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Trigger Events', 'wp-helpdesk' ); ?></th>
					<td>
						<?php
						$event_labels = array(
							'ticket_created'  => __( 'Ticket created', 'wp-helpdesk' ),
							'ticket_replied'  => __( 'Ticket replied', 'wp-helpdesk' ),
							'status_changed'  => __( 'Status changed', 'wp-helpdesk' ),
						);
						foreach ( $event_labels as $event_key => $event_label ) :
							?>
							<label style="display:block;margin-bottom:4px">
								<input type="checkbox" name="hd_push_ticket_events[]" value="<?php echo esc_attr( $event_key ); ?>" <?php checked( in_array( $event_key, $push_events, true ), true ); ?>>
								<?php echo esc_html( $event_label ); ?>
							</label>
						<?php endforeach; ?>
					</td>
				</tr>
			</table>

			<!-- Android / API -->
			<h2><?php esc_html_e( 'Android / REST API', 'wp-helpdesk' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'API Enabled', 'wp-helpdesk' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="hd_api_enabled" value="1" <?php checked( $api_enabled, 1 ); ?>>
							<?php esc_html_e( 'Enable the Helpdesk REST API for the Android admin app', 'wp-helpdesk' ); ?>
						</label>
						<p class="description">
							<?php
							echo wp_kses(
								sprintf(
									/* translators: %s: REST API base path */
									__( 'API base: <code>%s</code>', 'wp-helpdesk' ),
									esc_html( rest_url( 'helpdesk/v1/admin/' ) )
								),
								array( 'code' => array() )
							);
							?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Require Application Passwords', 'wp-helpdesk' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="hd_api_require_application_passwords" value="1" <?php checked( $api_require_ap, 1 ); ?>>
							<?php esc_html_e( 'Require WordPress Application Passwords for API authentication (recommended)', 'wp-helpdesk' ); ?>
						</label>
						<p class="description"><?php esc_html_e( 'Browser requests also need the X-WP-Nonce header.', 'wp-helpdesk' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="hd_api_rate_limit_per_minute"><?php esc_html_e( 'Rate Limit (per minute)', 'wp-helpdesk' ); ?></label></th>
					<td>
						<input type="number" id="hd_api_rate_limit_per_minute" name="hd_api_rate_limit_per_minute" value="<?php echo esc_attr( (string) $rate_limit ); ?>" min="1" max="5000" class="small-text">
						<p class="description"><?php esc_html_e( 'Maximum API requests per minute per credential (1–5000).', 'wp-helpdesk' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Log API Requests', 'wp-helpdesk' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="hd_api_log_requests" value="1" <?php checked( $api_log, 1 ); ?>>
							<?php esc_html_e( 'Log incoming API requests (debug use; sensitive data is never logged)', 'wp-helpdesk' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="hd_api_allowed_origins"><?php esc_html_e( 'Allowed Origins', 'wp-helpdesk' ); ?></label></th>
					<td>
						<textarea id="hd_api_allowed_origins" name="hd_api_allowed_origins" rows="4" class="large-text"><?php echo esc_textarea( $origins ); ?></textarea>
						<p class="description"><?php esc_html_e( 'One origin per line (e.g. https://app.example.com). Leave empty to allow all.', 'wp-helpdesk' ); ?></p>
					</td>
				</tr>
			</table>

			<?php submit_button( __( 'Save Settings', 'wp-helpdesk' ) ); ?>
		</form>
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
				'4.22.1',
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
				'4.22.1',
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
