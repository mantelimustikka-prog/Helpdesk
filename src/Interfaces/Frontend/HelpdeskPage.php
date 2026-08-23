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
	protected const APP_ICON_URL = 'https://github.com/user-attachments/assets/993b5e47-3306-48b7-b67e-aaa4bedd38f7';

	/**
	 * Output the landing page.
	 *
	 * @return void
	 */
	public function render(): void {
		$this->outputHeader( __( 'Support Centre', 'wp-helpdesk' ) );
		$links = $this->getCustomerActionLinks();
		?>
		<div class="hd-wrap">
			<h1 class="hd-title"><?php esc_html_e( 'Help Desk', 'wp-helpdesk' ); ?></h1>
			<?php $this->renderNavMenu( $links ); ?>
		</div>
		<?php
		$this->outputFooter();
	}

	/**
	 * Build the customer navigation links shown on the helpdesk hub page.
	 *
	 * @return array<int, array<string, string>>
	 */
	protected function getCustomerActionLinks(): array {
		$links        = array();
		$is_logged_in  = is_user_logged_in();
		$allow_guest   = 1 === (int) get_site_option( Constants::OPTION_GENERAL_ALLOW_GUEST, 1 );

		if ( $is_logged_in ) {
			$links[] = array(
				'url'   => home_url( '/helpdesk/member/new/' ),
				'label' => __( 'New Request', 'wp-helpdesk' ),
			);
		} elseif ( $allow_guest ) {
			$links[] = array(
				'url'   => home_url( '/helpdesk/new/' ),
				'label' => __( 'New Request', 'wp-helpdesk' ),
			);
		}

		$links[] = array(
			'url'   => $is_logged_in ? home_url( '/helpdesk/requests/' ) : wp_login_url( home_url( '/helpdesk/requests/' ) ),
			'label' => __( 'My Requests', 'wp-helpdesk' ),
		);

		$account_url = $this->getWooAccountUrl();
		if ( '' === $account_url ) {
			$account_url = home_url( '/' );
		}
		$links[] = array(
			'url'   => $account_url,
			'label' => __( 'My Account', 'wp-helpdesk' ),
		);

		$links[] = array(
			'url'   => home_url( '/' ),
			'label' => __( 'Home', 'wp-helpdesk' ),
		);

		return $links;
	}

	/**
	 * Render the centered navigation menu.
	 *
	 * @param array<int, array<string, string>> $links Navigation links.
	 * @return void
	 */
	protected function renderNavMenu( array $links ): void {
		?>
		<nav class="hd-nav-menu" aria-label="<?php echo esc_attr( __( 'Helpdesk menu', 'wp-helpdesk' ) ); ?>">
			<?php foreach ( $links as $link ) : ?>
				<a href="<?php echo esc_url( $link['url'] ); ?>" class="hd-nav-menu__item">
					<?php echo esc_html( $link['label'] ); ?>
				</a>
			<?php endforeach; ?>
		</nav>
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
			$app_icon_url = esc_url( static::APP_ICON_URL );
			$body_class_filter = static function ( array $classes ): array {
				$classes[] = 'hd-page';
				return $classes;
			};
			add_filter( 'document_title_parts', static function ( array $parts ) use ( $title ): array {
				$parts['title'] = $title;
				return $parts;
			} );
			add_filter( 'body_class', $body_class_filter );
			$head_icon_filter = static function () use ( $app_icon_url ): void {
				echo '<link rel="icon" href="' . $app_icon_url . '" />' . "\n";
				echo '<link rel="apple-touch-icon" href="' . $app_icon_url . '" />' . "\n";
			};
			add_action( 'wp_head', $head_icon_filter, 1 );
			get_header();
			if ( function_exists( 'remove_filter' ) ) {
				remove_filter( 'body_class', $body_class_filter );
			}
			if ( function_exists( 'remove_action' ) ) {
				remove_action( 'wp_head', $head_icon_filter, 1 );
			}
			return;
		}

		// Fallback for headless / test contexts.
		?>
		<!DOCTYPE html>
		<html>
		<head>
			<meta charset="utf-8">
			<title><?php echo esc_html( $title ); ?></title>
			<link rel="icon" href="<?php echo esc_url( static::APP_ICON_URL ); ?>">
			<link rel="apple-touch-icon" href="<?php echo esc_url( static::APP_ICON_URL ); ?>">
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
