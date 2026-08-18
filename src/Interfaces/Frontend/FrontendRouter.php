<?php
/**
 * @package WP Helpdesk
 */

namespace WPHelpdesk\Interfaces\Frontend;

use WPHelpdesk\Support\Helpers;
use WPHelpdesk\Support\Constants;

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
		add_filter( 'query_vars', array( $this, 'addQueryVars' ) );
		add_action( 'template_redirect', array( $this, 'dispatch' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueueAssets' ) );
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
	 * Dispatch to the correct page controller based on the hd_page query var.
	 *
	 * @return void
	 */
	public function dispatch(): void {
		$hd_page = get_query_var( 'hd_page', '' );

		if ( '' === $hd_page ) {
			return;
		}

		switch ( $hd_page ) {
			case 'index':
				$this->helpdesk_page->render();
				exit;

			case 'new':
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
