<?php
/**
 * @package WP Helpdesk
 */

namespace WPHelpdesk\Interfaces\Frontend;

use WPHelpdesk\Support\Helpers;
use WPHelpdesk\Support\Constants;
use WPHelpdesk\Interfaces\Frontend\FormDefinitionFactory;

/**
 * Registers WordPress rewrite rules for the customer-facing helpdesk pages
 * and dispatches requests to the appropriate page controller.
 *
 * Routes:
 *  /helpdesk/                – main landing page
 *  /helpdesk/new/            – guest (non-logged-in) ticket submission form
 *  /helpdesk/member/new/     – member (logged-in) ticket submission form
 */
class FrontendRouter {

	protected HelpdeskPage $helpdesk_page;
	protected GuestTicketForm $guest_form;
	protected MemberTicketForm $member_form;

	public function __construct(
		?HelpdeskPage $helpdesk_page = null,
		?GuestTicketForm $guest_form = null,
		?MemberTicketForm $member_form = null
	) {
		$this->helpdesk_page = $helpdesk_page ?: new HelpdeskPage();
		$this->guest_form    = $guest_form    ?: new GuestTicketForm();
		$this->member_form   = $member_form   ?: new MemberTicketForm();
	}

	/**
	 * Register rewrite rules, query vars, and template redirect hook.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'addRewriteRules' ) );
		add_action( 'init', array( $this, 'maybeFlushRewrites' ) );
		add_filter( 'query_vars', array( $this, 'addQueryVars' ) );
		add_action( 'template_redirect', array( $this, 'dispatch' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueueAssets' ) );
	}

	/**
	 * Flush rewrite rules once when the stored rewrite-version option does not
	 * match the current constant.  Never flushes on ordinary requests.
	 *
	 * @return void
	 */
	public function maybeFlushRewrites(): void {
		$stored = (string) get_option( Constants::OPTION_REWRITE_VERSION, '' );
		if ( $stored !== Constants::REWRITE_VERSION ) {
			flush_rewrite_rules( false );
			update_option( Constants::OPTION_REWRITE_VERSION, Constants::REWRITE_VERSION );
		}
	}

	/**
	 * Add rewrite rules for the three frontend routes.
	 *
	 * @return void
	 */
	public function addRewriteRules(): void {
		add_rewrite_rule( '^helpdesk/member/new/?$', 'index.php?hd_page=member_new', 'top' );
		add_rewrite_rule( '^helpdesk/new/?$', 'index.php?hd_page=new', 'top' );
		add_rewrite_rule( '^helpdesk/?$', 'index.php?hd_page=index', 'top' );
	}

	/**
	 * Expose the hd_page query variable to WordPress.
	 *
	 * @param array<int, string> $vars Existing query vars.
	 * @return array<int, string>
	 */
	public function addQueryVars( array $vars ): array {
		$vars[] = 'hd_page';
		return $vars;
	}

	/**
	 * Dispatch to the correct page controller based on the hd_page query var,
	 * with a path-based fallback so routes resolve even when rewrite rules have
	 * not been flushed yet or when the mapped page is missing.
	 *
	 * @return void
	 */
	public function dispatch(): void {
		$hd_page = sanitize_key( (string) get_query_var( 'hd_page', '' ) );

		// Path-based fallback when the query var is not set.
		if ( '' === $hd_page ) {
			$hd_page = $this->resolveFromPath();
		}

		if ( '' === $hd_page ) {
			return;
		}

		switch ( $hd_page ) {
			case 'index':
				$this->helpdesk_page->render();
				exit;

			case 'new':
				if ( 1 !== (int) get_site_option( Constants::OPTION_GENERAL_ALLOW_GUEST, 1 ) ) {
					wp_safe_redirect( home_url( '/helpdesk/' ) );
					exit;
				}
				$this->guest_form->render();
				exit;

			case 'member_new':
				if ( ! is_user_logged_in() ) {
					wp_safe_redirect( home_url( '/helpdesk/new/' ) );
					exit;
				}
				$this->member_form->render();
				exit;
		}
	}

	/**
	 * Derive the hd_page value from the current request path when the rewrite
	 * query var is absent (e.g. rule not yet flushed, or mapped-page context).
	 *
	 * @return string One of 'index'|'new'|'member_new', or '' when not matched.
	 */
	protected function resolveFromPath(): string {
		// Only run on front-end singular/page contexts or raw path checks.
		$request_uri = isset( $_SERVER['REQUEST_URI'] )
			? sanitize_text_field( wp_unslash( (string) $_SERVER['REQUEST_URI'] ) )
			: '';

		// Strip query string.
		$path = (string) parse_url( $request_uri, PHP_URL_PATH );
		$path = rtrim( $path, '/' ) . '/';

		// Normalise the home URL base so path comparison is site-relative.
		$home_path = rtrim( (string) parse_url( home_url(), PHP_URL_PATH ), '/' );
		if ( '' !== $home_path && 0 === strpos( $path, $home_path ) ) {
			$path = substr( $path, strlen( $home_path ) );
		}

		if ( '/helpdesk/member/new/' === $path ) {
			return 'member_new';
		}

		if ( '/helpdesk/new/' === $path ) {
			return 'new';
		}

		if ( '/helpdesk/' === $path ) {
			return 'index';
		}

		return '';
	}

	/**
	 * Enqueue customer-facing assets on helpdesk pages.
	 *
	 * @return void
	 */
	public function enqueueAssets(): void {
		if ( '' === get_query_var( 'hd_page', '' ) ) {
			return;
		}

		wp_enqueue_style(
			'wp-helpdesk-frontend',
			Helpers::pluginUrl( 'assets/css/helpdesk-frontend.css' ),
			array(),
			HD_VERSION
		);

		wp_enqueue_script(
			'wp-helpdesk-frontend',
			Helpers::pluginUrl( 'assets/js/helpdesk-frontend.js' ),
			array(),
			HD_VERSION,
			true
		);

		wp_localize_script(
			'wp-helpdesk-frontend',
			'WPHelpdesk',
			array(
				'restBase'  => esc_url_raw( rest_url( Constants::REST_NAMESPACE . '/' ) ),
				'restNonce' => wp_create_nonce( 'wp_rest' ),
				'newUrl'    => esc_url( home_url( '/helpdesk/new/' ) ),
				'memberUrl' => esc_url( home_url( '/helpdesk/member/new/' ) ),
				'indexUrl'  => esc_url( home_url( '/helpdesk/' ) ),
				'isLoggedIn' => is_user_logged_in() ? '1' : '0',
				'formDefinitions' => ( new FormDefinitionFactory() )->getDefinitions(),
				'i18n' => array(
					'followupTopicLabel' => __( 'Follow-up topic', 'wp-helpdesk' ),
					'selectPlaceholder'  => __( 'Select …', 'wp-helpdesk' ),
					'errorSelectTopic'   => __( 'Please select a topic.', 'wp-helpdesk' ),
					'errorCompleteTopic' => __( 'Please complete topic selection.', 'wp-helpdesk' ),
					'errorLoadTransitions' => __( 'Could not load follow-up topics. Please try again.', 'wp-helpdesk' ),
				),
			)
		);
	}
}
