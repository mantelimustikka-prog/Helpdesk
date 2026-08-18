<?php
/**
 * @package WP Helpdesk
 */

namespace WPHelpdesk\Interfaces\Frontend;

/**
 * Renders the guest (non-logged-in) ticket submission form at /helpdesk/new/.
 *
 * Step flow:
 *  0 – Select topic
 *  1 – Fill in contact + message details
 *  2 – Confirmation
 */
class GuestTicketForm extends HelpdeskPage {

	/**
	 * Output the guest form page.
	 *
	 * @return void
	 */
	public function render(): void {
		$this->outputHeader( __( 'Submit a Support Request', 'wp-helpdesk' ) );
		?>
		<div class="hd-wrap">
			<h1 class="hd-title"><?php esc_html_e( 'Submit a Support Request', 'wp-helpdesk' ); ?></h1>
			<p class="hd-back-link">
				<a href="<?php echo esc_url( home_url( '/helpdesk/' ) ); ?>">
					&larr; <?php esc_html_e( 'Back to Support Centre', 'wp-helpdesk' ); ?>
				</a>
			</p>

			<?php $this->renderForm(); ?>
		</div>
		<?php
		$this->outputFooter();
	}

	/**
	 * Render the multi-step guest form markup.
	 * JavaScript drives step visibility; all steps are present in the DOM.
	 *
	 * @return void
	 */
	protected function renderForm(): void {
		?>
		<div class="hd-form-container" id="hd-guest-form" data-form-type="guest">

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
					<label for="hd-topic" class="hd-label">
						<?php esc_html_e( 'Topic', 'wp-helpdesk' ); ?>
						<span class="hd-required" aria-hidden="true">*</span>
					</label>
					<select id="hd-topic" name="topic_id" class="hd-select" required aria-required="true">
						<option value=""><?php esc_html_e( 'Select …', 'wp-helpdesk' ); ?></option>
					</select>
					<p class="hd-field-hint" id="hd-topic-description" aria-live="polite"></p>
				</div>
				<div class="hd-branch-container" data-role="topic-branch"></div>
				<p class="hd-error-message" id="hd-topic-error" aria-live="assertive" role="alert"></p>
				<div class="hd-form-actions">
					<button type="button" class="hd-btn hd-btn--primary" id="hd-step0-next" disabled>
						<?php esc_html_e( 'Continue', 'wp-helpdesk' ); ?>
					</button>
				</div>
			</div>

			<!-- Step 1: Contact details + message -->
			<div class="hd-form-step hd-form-step--hidden" data-step="1">
				<h2 class="hd-form-step__title"><?php esc_html_e( 'Your details', 'wp-helpdesk' ); ?></h2>
				<div class="hd-field">
					<label for="hd-name" class="hd-label">
						<?php esc_html_e( 'Your name', 'wp-helpdesk' ); ?>
						<span class="hd-required" aria-hidden="true">*</span>
					</label>
					<input
						type="text"
						id="hd-name"
						name="requester_name"
						class="hd-input"
						required
						aria-required="true"
						autocomplete="name"
					>
				</div>
				<div class="hd-field">
					<label for="hd-email" class="hd-label">
						<?php esc_html_e( 'Email address', 'wp-helpdesk' ); ?>
						<span class="hd-required" aria-hidden="true">*</span>
					</label>
					<input
						type="email"
						id="hd-email"
						name="requester_email"
						class="hd-input"
						required
						aria-required="true"
						autocomplete="email"
					>
				</div>
				<div class="hd-field">
					<label for="hd-phone" class="hd-label">
						<?php esc_html_e( 'Phone number', 'wp-helpdesk' ); ?>
						<span class="hd-required" aria-hidden="true">*</span>
					</label>
					<input
						type="tel"
						id="hd-phone"
						name="requester_phone"
						class="hd-input"
						required
						aria-required="true"
						autocomplete="tel"
					>
				</div>
				<div class="hd-field">
					<label for="hd-subject" class="hd-label">
						<?php esc_html_e( 'Subject', 'wp-helpdesk' ); ?>
						<span class="hd-required" aria-hidden="true">*</span>
					</label>
					<input
						type="text"
						id="hd-subject"
						name="subject"
						class="hd-input"
						required
						aria-required="true"
					>
				</div>
				<div class="hd-field">
					<label for="hd-message" class="hd-label">
						<?php esc_html_e( 'Message', 'wp-helpdesk' ); ?>
						<span class="hd-required" aria-hidden="true">*</span>
					</label>
					<textarea
						id="hd-message"
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
					<button type="button" class="hd-btn hd-btn--primary" id="hd-step1-submit">
						<?php esc_html_e( 'Submit request', 'wp-helpdesk' ); ?>
					</button>
				</div>
				<p class="hd-error-message" id="hd-form-error" aria-live="assertive" role="alert"></p>
			</div>

			<!-- Step 2: Confirmation -->
			<div class="hd-form-step hd-form-step--hidden" data-step="2">
				<div class="hd-confirmation">
					<div class="hd-confirmation__icon" aria-hidden="true">&#10003;</div>
					<h2 class="hd-confirmation__title"><?php esc_html_e( 'Request submitted!', 'wp-helpdesk' ); ?></h2>
					<p class="hd-confirmation__message" id="hd-confirm-msg">
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
