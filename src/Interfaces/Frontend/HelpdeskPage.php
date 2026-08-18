<?php
/**
 * @package WP Helpdesk
 */

namespace WPHelpdesk\Interfaces\Frontend;

/**
 * Renders the main helpdesk landing page at /helpdesk/.
 */
class HelpdeskPage {

	/**
	 * Output the landing page.
	 *
	 * @return void
	 */
	public function render(): void {
		$this->outputHeader( __( 'Support Centre', 'wp-helpdesk' ) );
		?>
		<div class="hd-wrap">
			<h1 class="hd-title"><?php esc_html_e( 'How can we help you?', 'wp-helpdesk' ); ?></h1>
			<p class="hd-subtitle">
				<?php esc_html_e( 'Submit a support request and our team will get back to you as soon as possible.', 'wp-helpdesk' ); ?>
			</p>
			<div class="hd-actions">
				<?php if ( is_user_logged_in() ) : ?>
					<a href="<?php echo esc_url( home_url( '/helpdesk/member/new/' ) ); ?>" class="hd-btn hd-btn--primary">
						<?php esc_html_e( 'Submit a request', 'wp-helpdesk' ); ?>
					</a>
				<?php else : ?>
					<a href="<?php echo esc_url( home_url( '/helpdesk/new/' ) ); ?>" class="hd-btn hd-btn--primary">
						<?php esc_html_e( 'Submit a request', 'wp-helpdesk' ); ?>
					</a>
					<a href="<?php echo esc_url( wp_login_url( home_url( '/helpdesk/member/new/' ) ) ); ?>" class="hd-btn hd-btn--secondary">
						<?php esc_html_e( 'Sign in to submit', 'wp-helpdesk' ); ?>
					</a>
				<?php endif; ?>
			</div>
		</div>
		<?php
		$this->outputFooter();
	}

	/**
	 * Output a minimal HTML header, honouring the active theme when available.
	 *
	 * @param string $title Page title.
	 * @return void
	 */
	protected function outputHeader( string $title ): void {
		if ( function_exists( 'get_header' ) ) {
			add_filter( 'document_title_parts', static function ( array $parts ) use ( $title ): array {
				$parts['title'] = $title;
				return $parts;
			} );
			get_header();
			return;
		}

		// Fallback for headless / test contexts.
		?>
		<!DOCTYPE html>
		<html>
		<head>
			<meta charset="utf-8">
			<title><?php echo esc_html( $title ); ?></title>
		</head>
		<body class="hd-page">
		<?php
	}

	/**
	 * Output a minimal HTML footer.
	 *
	 * @return void
	 */
	protected function outputFooter(): void {
		if ( function_exists( 'get_footer' ) ) {
			get_footer();
			return;
		}

		?>
		</body>
		</html>
		<?php
	}
}
