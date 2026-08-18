<?php
/**
 * @package WP Helpdesk
 */

namespace WPHelpdesk\Interfaces\Frontend;

/**
 * Renders the member (logged-in) ticket submission form at /helpdesk/member/new/.
 *
 * Extends GuestTicketForm and prefills contact fields from the current user.
 * The email field is read-only because the account email is authoritative.
 */
class MemberTicketForm extends GuestTicketForm {

	/**
	 * Output the member form page.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( home_url( '/helpdesk/new/' ) );
			exit;
		}

		$this->outputHeader( __( 'Submit a Support Request', 'wp-helpdesk' ) );
		?>
		<div class="hd-wrap">
			<h1 class="hd-title"><?php esc_html_e( 'Submit a Support Request', 'wp-helpdesk' ); ?></h1>
			<p class="hd-back-link">
				<a href="<?php echo esc_url( home_url( '/helpdesk/' ) ); ?>">
					&larr; <?php esc_html_e( 'Back to Support Centre', 'wp-helpdesk' ); ?>
				</a>
			</p>

			<?php $this->renderMemberForm(); ?>
		</div>
		<?php
		$this->outputFooter();
	}

	/**
	 * Render the member-specific multi-step form.
	 * Contact fields are prefilled and the email is read-only.
	 *
	 * @return void
	 */
	protected function renderMemberForm(): void {
		$user       = wp_get_current_user();
		$user_name  = $user->display_name ?: ( $user->first_name . ' ' . $user->last_name );
		$user_email = $user->user_email;
		$user_phone = (string) get_user_meta( $user->ID, 'phone', true );
		if ( '' === trim( $user_phone ) ) {
			$user_phone = (string) get_user_meta( $user->ID, 'billing_phone', true );
		}
		if ( '' === trim( $user_phone ) ) {
			$user_phone = (string) get_site_option( 'hd_default_customer_phone', '' );
		}
		?>
		<div class="hd-form-container" id="hd-member-form" data-form-type="member">

			<!-- Step indicator -->
			<div class="hd-steps" aria-label="<?php esc_attr_e( 'Form steps', 'wp-helpdesk' ); ?>">
				<div class="hd-step hd-step--active" data-step="0">
					<span class="hd-step__number">1</span>
					<span class="hd-step__label"><?php esc_html_e( 'Topic', 'wp-helpdesk' ); ?></span>
				</div>
				<div class="hd-step" data-step="1">
					<span class="hd-step__number">2</span>
					<span class="hd-step__label"><?php esc_html_e( 'Details', 'wp-helpdesk' ); ?></span>
				</div>
				<div class="hd-step" data-step="2">
					<span class="hd-step__number">3</span>
					<span class="hd-step__label"><?php esc_html_e( 'Done', 'wp-helpdesk' ); ?></span>
				</div>
			</div>

			<!-- Step 0: Topic selection -->
			<div class="hd-form-step" data-step="0">
				<h2 class="hd-form-step__title"><?php esc_html_e( 'What can we help you with?', 'wp-helpdesk' ); ?></h2>
				<div class="hd-field">
					<label for="hd-topic-member" class="hd-label">
						<?php esc_html_e( 'Topic', 'wp-helpdesk' ); ?>
						<span class="hd-required" aria-hidden="true">*</span>
					</label>
					<select id="hd-topic-member" name="topic_id" class="hd-select" required aria-required="true">
						<option value=""><?php esc_html_e( 'Select …', 'wp-helpdesk' ); ?></option>
					</select>
					<p class="hd-field-hint" id="hd-member-topic-description" aria-live="polite"></p>
				</div>
				<div class="hd-branch-container" data-role="topic-branch"></div>
				<p class="hd-error-message" id="hd-member-topic-error" aria-live="assertive" role="alert"></p>
				<div class="hd-form-actions">
					<button type="button" class="hd-btn hd-btn--primary" id="hd-member-step0-next" disabled>
						<?php esc_html_e( 'Continue', 'wp-helpdesk' ); ?>
					</button>
				</div>
			</div>

			<!-- Step 1: Message (name/email prefilled, email read-only) -->
			<div class="hd-form-step hd-form-step--hidden" data-step="1">
				<h2 class="hd-form-step__title"><?php esc_html_e( 'Describe your request', 'wp-helpdesk' ); ?></h2>
				<div class="hd-field">
					<label for="hd-member-name" class="hd-label">
						<?php esc_html_e( 'Your name', 'wp-helpdesk' ); ?>
					</label>
					<input
						type="text"
						id="hd-member-name"
						name="requester_name"
						class="hd-input"
						value="<?php echo esc_attr( trim( $user_name ) ); ?>"
						required
						aria-required="true"
						autocomplete="name"
					>
				</div>
				<div class="hd-field">
					<label for="hd-member-email" class="hd-label">
						<?php esc_html_e( 'Email address', 'wp-helpdesk' ); ?>
					</label>
					<input
						type="email"
						id="hd-member-email"
						name="requester_email"
						class="hd-input"
						value="<?php echo esc_attr( $user_email ); ?>"
						readonly
						aria-readonly="true"
						required
						aria-required="true"
					>
				</div>
				<div class="hd-field">
					<label for="hd-member-phone" class="hd-label">
						<?php esc_html_e( 'Phone number', 'wp-helpdesk' ); ?>
						<span class="hd-required" aria-hidden="true">*</span>
					</label>
					<input
						type="tel"
						id="hd-member-phone"
						name="requester_phone"
						class="hd-input"
						value="<?php echo esc_attr( trim( $user_phone ) ); ?>"
						required
						aria-required="true"
						autocomplete="tel"
					>
				</div>
				<div class="hd-field">
					<label for="hd-member-subject" class="hd-label">
						<?php esc_html_e( 'Subject', 'wp-helpdesk' ); ?>
						<span class="hd-required" aria-hidden="true">*</span>
					</label>
					<input
						type="text"
						id="hd-member-subject"
						name="subject"
						class="hd-input"
						required
						aria-required="true"
					>
				</div>
				<div class="hd-field">
					<label for="hd-member-message" class="hd-label">
						<?php esc_html_e( 'Message', 'wp-helpdesk' ); ?>
						<span class="hd-required" aria-hidden="true">*</span>
					</label>
					<textarea
						id="hd-member-message"
						name="message"
						class="hd-textarea"
						rows="6"
						required
						aria-required="true"
					></textarea>
				</div>
				<div class="hd-form-actions hd-form-actions--split">
					<button type="button" class="hd-btn hd-btn--secondary" data-action="prev">
						&larr; <?php esc_html_e( 'Back', 'wp-helpdesk' ); ?>
					</button>
					<button type="button" class="hd-btn hd-btn--primary" id="hd-member-step1-submit">
						<?php esc_html_e( 'Submit request', 'wp-helpdesk' ); ?>
					</button>
				</div>
				<p class="hd-error-message" id="hd-member-form-error" aria-live="assertive" role="alert"></p>
			</div>

			<!-- Step 2: Confirmation -->
			<div class="hd-form-step hd-form-step--hidden" data-step="2">
				<div class="hd-confirmation">
					<div class="hd-confirmation__icon" aria-hidden="true">&#10003;</div>
					<h2 class="hd-confirmation__title"><?php esc_html_e( 'Request submitted!', 'wp-helpdesk' ); ?></h2>
					<p class="hd-confirmation__message" id="hd-member-confirm-msg">
						<?php esc_html_e( 'Thank you. We have received your request and will be in touch via email.', 'wp-helpdesk' ); ?>
					</p>
					<a href="<?php echo esc_url( home_url( '/helpdesk/' ) ); ?>" class="hd-btn hd-btn--secondary">
						<?php esc_html_e( 'Back to Support Centre', 'wp-helpdesk' ); ?>
					</a>
				</div>
			</div>

		</div><!-- .hd-form-container -->
		<?php
	}
}
