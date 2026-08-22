<?php
/**
 * @package WP Helpdesk
 */

namespace WPHelpdesk\Interfaces\Frontend;

use WPHelpdesk\Support\Constants;

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
		$allow_guest = 1 === (int) get_site_option( Constants::OPTION_GENERAL_ALLOW_GUEST, 1 );
		$is_logged_in = is_user_logged_in();
		$cards        = $this->getCustomerActionCards( $is_logged_in, $allow_guest );
		?>
		<div class="hd-wrap">
			<h1 class="hd-title"><?php esc_html_e( 'How can we help you?', 'wp-helpdesk' ); ?></h1>
			<p class="hd-subtitle">
				<?php esc_html_e( 'Choose what you need help with and jump directly to the right support action.', 'wp-helpdesk' ); ?>
			</p>
			<?php $this->renderActionGrid( $cards ); ?>
		</div>
		<?php
		$this->outputFooter();
	}

	/**
	 * Build the customer action cards shown on the helpdesk hub page.
	 *
	 * @param bool $is_logged_in Whether the current visitor is logged in.
	 * @param bool $allow_guest  Whether guest submissions are enabled.
	 * @return array<int, array<string, string>>
	 */
	protected function getCustomerActionCards( bool $is_logged_in, bool $allow_guest ): array {
		$cards = array();

		if ( $is_logged_in ) {
			$cards[] = array(
				'title'       => __( 'Submit a request', 'wp-helpdesk' ),
				'description' => __( 'Open a new support request as a signed-in customer.', 'wp-helpdesk' ),
				'url'         => home_url( '/helpdesk/member/new/' ),
				'button'      => __( 'Start request', 'wp-helpdesk' ),
				'variant'     => 'primary',
			);
			$cards[] = array(
				'title'       => __( 'View or continue my requests', 'wp-helpdesk' ),
				'description' => __( 'See your requests, check status, and continue any open conversation.', 'wp-helpdesk' ),
				'url'         => home_url( '/helpdesk/requests/' ),
				'button'      => __( 'Open my requests', 'wp-helpdesk' ),
				'variant'     => 'primary',
			);
		} else {
			if ( $allow_guest ) {
				$cards[] = array(
					'title'       => __( 'Submit a request', 'wp-helpdesk' ),
					'description' => __( 'Send a support request without signing in.', 'wp-helpdesk' ),
					'url'         => home_url( '/helpdesk/new/' ),
					'button'      => __( 'Submit as guest', 'wp-helpdesk' ),
					'variant'     => 'primary',
				);
			}
			$cards[] = array(
				'title'       => __( 'Sign in / account access', 'wp-helpdesk' ),
				'description' => __( 'Sign in to submit requests and manage your support history.', 'wp-helpdesk' ),
				'url'         => wp_login_url( home_url( '/helpdesk/member/new/' ) ),
				'button'      => __( 'Sign in', 'wp-helpdesk' ),
				'variant'     => 'primary',
			);
			$cards[] = array(
				'title'       => __( 'Track an existing request', 'wp-helpdesk' ),
				'description' => __( 'Use your sign-in account or secure ticket link from email updates.', 'wp-helpdesk' ),
				'url'         => wp_login_url( home_url( '/helpdesk/requests/' ) ),
				'button'      => __( 'Track request', 'wp-helpdesk' ),
				'variant'     => 'secondary',
			);
		}

		$kb_url = trim( (string) apply_filters( 'wp_helpdesk_frontend_kb_url', '' ) );
		if ( $this->isHttpUrl( $kb_url ) ) {
			$cards[] = array(
				'title'       => __( 'Browse help articles', 'wp-helpdesk' ),
				'description' => __( 'Find answers quickly in the knowledge base.', 'wp-helpdesk' ),
				'url'         => $kb_url,
				'button'      => __( 'Browse articles', 'wp-helpdesk' ),
				'variant'     => 'secondary',
			);
		}

		if ( $is_logged_in ) {
			$account_url = $this->getWooAccountUrl();
			if ( '' !== $account_url ) {
				$cards[] = array(
					'title'       => __( 'WooCommerce account', 'wp-helpdesk' ),
					'description' => __( 'Open your account dashboard for orders and account details.', 'wp-helpdesk' ),
					'url'         => $account_url,
					'button'      => __( 'Open account', 'wp-helpdesk' ),
					'variant'     => 'secondary',
				);
			}
		}

		return $cards;
	}

	/**
	 * Render the action card grid.
	 *
	 * @param array<int, array<string, string>> $cards Action cards.
	 * @return void
	 */
	protected function renderActionGrid( array $cards ): void {
		?>
		<ul class="hd-action-grid" aria-label="<?php echo esc_attr( __( 'Helpdesk actions', 'wp-helpdesk' ) ); ?>">
			<?php foreach ( $cards as $card ) : ?>
				<li class="hd-action-card">
					<h2 class="hd-action-card__title"><?php echo esc_html( $card['title'] ); ?></h2>
					<p class="hd-action-card__description"><?php echo esc_html( $card['description'] ); ?></p>
					<a href="<?php echo esc_url( $card['url'] ); ?>" class="hd-btn <?php echo 'primary' === $card['variant'] ? 'hd-btn--primary' : 'hd-btn--secondary'; ?>">
						<?php echo esc_html( $card['button'] ); ?>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
		<?php
	}

	/**
	 * Return WooCommerce account URL when available.
	 *
	 * @return string
	 */
	protected function getWooAccountUrl(): string {
		if ( ! function_exists( 'wc_get_page_permalink' ) ) {
			return '';
		}

		$url = trim( (string) wc_get_page_permalink( 'myaccount' ) );
		return $this->isHttpUrl( $url ) ? $url : '';
	}

	/**
	 * Whether a URL is a safe HTTP(S) destination.
	 *
	 * @param string $url Candidate URL.
	 * @return bool
	 */
	protected function isHttpUrl( string $url ): bool {
		if ( '' === $url ) {
			return false;
		}

		if ( false === filter_var( $url, FILTER_VALIDATE_URL ) ) {
			return false;
		}

		$scheme = strtolower( (string) parse_url( $url, PHP_URL_SCHEME ) );
		return in_array( $scheme, array( 'http', 'https' ), true );
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
