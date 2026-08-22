<?php
/**
 * @package WP Helpdesk
 */

namespace WPHelpdesk\Interfaces\Frontend;

use WPHelpdesk\Support\Helpers;
use WPHelpdesk\Support\Constants;
use WPHelpdesk\Interfaces\Frontend\FormDefinitionFactory;
use WPHelpdesk\Interfaces\Frontend\GuestTicketView;

/**
 * Registers WordPress rewrite rules for the customer-facing helpdesk pages
 * and dispatches requests to the appropriate page controller.
 *
 * Routes:
 *  /helpdesk/                – main landing page
 *  /helpdesk/new/            – guest (non-logged-in) ticket submission form
 *  /helpdesk/member/new/     – member (logged-in) ticket submission form
 *  /helpdesk/requests/       – member request listing
 *  /helpdesk/request/{no}/   – member request detail/reply
 */
class FrontendRouter {

	protected HelpdeskPage $helpdesk_page;
	protected GuestTicketForm $guest_form;
	protected MemberTicketForm $member_form;
	protected GuestTicketView $ticket_view;
	protected WooCommerceAccountHelpdesk $member_helpdesk;

	public function __construct(
		?HelpdeskPage $helpdesk_page = null,
		?GuestTicketForm $guest_form = null,
		?MemberTicketForm $member_form = null,
		?GuestTicketView $ticket_view = null,
		?WooCommerceAccountHelpdesk $member_helpdesk = null
	) {
		$this->helpdesk_page = $helpdesk_page ?: new HelpdeskPage();
		$this->guest_form    = $guest_form    ?: new GuestTicketForm();
		$this->member_form   = $member_form   ?: new MemberTicketForm();
		$this->ticket_view   = $ticket_view   ?: new GuestTicketView();
		$this->member_helpdesk = $member_helpdesk ?: new WooCommerceAccountHelpdesk();
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
		add_rewrite_rule( '^helpdesk/requests/?$', 'index.php?hd_page=member_requests', 'top' );
		add_rewrite_rule( '^helpdesk/request/([^/]+)/?$', 'index.php?hd_page=member_request&hd_ticket_no=$matches[1]', 'top' );
		add_rewrite_rule( '^helpdesk/ticket/([^/]+)/([^/]+)/?$', 'index.php?hd_page=ticket_view&hd_ticket_no=$matches[1]&hd_guest_token=$matches[2]', 'top' );
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
		$vars[] = 'hd_ticket_no';
		$vars[] = 'hd_guest_token';
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

			case 'member_requests':
				$this->member_helpdesk->renderStandalone( 'requests' );
				exit;

			case 'member_request':
				$ticket_no = rawurldecode( (string) get_query_var( 'hd_ticket_no', '' ) );
				$this->member_helpdesk->renderStandalone( 'request/' . $ticket_no );
				exit;

			case 'ticket_view':
				$ticket_no   = sanitize_text_field( (string) get_query_var( 'hd_ticket_no', '' ) );
				$guest_token = sanitize_text_field( (string) get_query_var( 'hd_guest_token', '' ) );
				$this->ticket_view->renderForTicket( $ticket_no, $guest_token );
				exit;
		}
	}

	/**
	 * Derive the hd_page value from the current request path when the rewrite
	 * query var is absent (e.g. rule not yet flushed, or mapped-page context).
	 *
	 * @return string One of 'index'|'new'|'member_new'|'member_requests'|'member_request'|'ticket_view', or '' when not matched.
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

		if ( '/helpdesk/requests/' === $path ) {
			return 'member_requests';
		}

		if ( '/helpdesk/' === $path ) {
			return 'index';
		}

		// /helpdesk/request/{ticket_no}/
		if ( 1 === preg_match( '#^/helpdesk/request/([^/]+)/$#', $path, $m ) ) {
			$this->setRuntimeQueryVar( 'hd_ticket_no', rawurldecode( $m[1] ) );
			return 'member_request';
		}

		// /helpdesk/ticket/{ticket_no}/{guest_token}/
		if ( 1 === preg_match( '#^/helpdesk/ticket/([^/]+)/([^/]+)/$#', $path, $m ) ) {
			$this->setRuntimeQueryVar( 'hd_ticket_no', rawurldecode( $m[1] ) );
			$this->setRuntimeQueryVar( 'hd_guest_token', rawurldecode( $m[2] ) );
			return 'ticket_view';
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

		$admin_color  = sanitize_hex_color( (string) get_site_option( Constants::OPTION_APPEARANCE_ADMIN_REPLY_COLOR, '' ) );
		$client_color = sanitize_hex_color( (string) get_site_option( Constants::OPTION_APPEARANCE_CLIENT_REPLY_COLOR, '' ) );
		$inline_css   = '';
		if ( $admin_color ) {
			$inline_css .= '.hd-thread__message--agent .hd-thread__body{color:' . $admin_color . ';}';
		}
		if ( $client_color ) {
			$inline_css .= '.hd-thread__message--guest .hd-thread__body,.hd-thread__message--member .hd-thread__body{color:' . $client_color . ';}';
		}
		if ( $inline_css ) {
			wp_add_inline_style( 'wp-helpdesk-frontend', $inline_css );
		}

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
					'selectOrderPlaceholder' => __( 'Select #Order', 'wp-helpdesk' ),
					'errorSelectTopic'   => __( 'Please select a topic.', 'wp-helpdesk' ),
					'errorCompleteTopic' => __( 'Please complete topic selection.', 'wp-helpdesk' ),
					'errorLoadTransitions' => __( 'Could not load follow-up topics. Please try again.', 'wp-helpdesk' ),
					'errorSelectOrderRelation' => __( 'Please select an order relation.', 'wp-helpdesk' ),
					'errorLoginRequired' => __( 'Please login to create ticket', 'wp-helpdesk' ),
					'errorSelectOrder' => __( 'Please select #Order.', 'wp-helpdesk' ),
					'kbSuggestionsTitle' => __( 'Helpful articles', 'wp-helpdesk' ),
					'kbNoSuggestions'    => __( 'No related knowledge base articles were found for this topic yet.', 'wp-helpdesk' ),
				),
			)
		);
	}

	/**
	 * Set a query var for the current request in both runtime and tests.
	 *
	 * @param string $key   Query var key.
	 * @param string $value Query var value.
	 * @return void
	 */
	protected function setRuntimeQueryVar( string $key, string $value ): void {
		if ( function_exists( 'set_query_var' ) ) {
			set_query_var( $key, $value );
			return;
		}

		$GLOBALS['wp_query_vars'][ $key ] = $value;
	}
}
