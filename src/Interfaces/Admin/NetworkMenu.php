<?php
/**
 * @package WP Helpdesk
 */

namespace WPHelpdesk\Interfaces\Admin;

use WPHelpdesk\Interfaces\Admin\Pages\DashboardPage;
use WPHelpdesk\Interfaces\Admin\Pages\SettingsPage;
use WPHelpdesk\Interfaces\Admin\Pages\TicketsPage;
use WPHelpdesk\Interfaces\Admin\Pages\TopicsPage;
use WPHelpdesk\Support\Helpers;

class NetworkMenu {
	protected DashboardPage $dashboard_page;
	protected TicketsPage $tickets_page;
	protected TopicsPage $topics_page;
	protected SettingsPage $settings_page;

	public function __construct() {
		$this->dashboard_page = new DashboardPage();
		$this->tickets_page   = new TicketsPage();
		$this->topics_page    = new TopicsPage();
		$this->settings_page  = new SettingsPage();
	}

	/**
	 * Register network admin pages.
	 *
	 * @return void
	 */
	public function register(): void {
		$capability = 'hd_manage_tickets';
		$main_hook  = add_menu_page(
			__( 'Helpdesk', 'wp-helpdesk' ),
			__( 'Helpdesk', 'wp-helpdesk' ),
			$capability,
			'wp-helpdesk',
			array( $this->dashboard_page, 'render' ),
			'dashicons-sos',
			58
		);

		add_submenu_page(
			'wp-helpdesk',
			__( 'Dashboard', 'wp-helpdesk' ),
			__( 'Dashboard', 'wp-helpdesk' ),
			$capability,
			'wp-helpdesk',
			array( $this->dashboard_page, 'render' )
		);

		$tickets_hook = add_submenu_page(
			'wp-helpdesk',
			__( 'Tickets', 'wp-helpdesk' ),
			__( 'Tickets', 'wp-helpdesk' ),
			$capability,
			'wp-helpdesk-tickets',
			array( $this->tickets_page, 'render' )
		);

		$topics_hook = add_submenu_page(
			'wp-helpdesk',
			__( 'Topics', 'wp-helpdesk' ),
			__( 'Topics', 'wp-helpdesk' ),
			'hd_manage_topics',
			'wp-helpdesk-topics',
			array( $this->topics_page, 'render' )
		);

		$settings_hook = add_submenu_page(
			'wp-helpdesk',
			__( 'Settings', 'wp-helpdesk' ),
			__( 'Settings', 'wp-helpdesk' ),
			'hd_manage_settings',
			'wp-helpdesk-settings',
			array( $this->settings_page, 'render' )
		);

		add_action( 'load-' . $main_hook, array( $this, 'bootstrapPages' ) );
		add_action( 'load-' . $tickets_hook, array( $this, 'bootstrapPages' ) );
		add_action( 'load-' . $topics_hook, array( $this, 'bootstrapPages' ) );
		add_action( 'load-' . $settings_hook, array( $this, 'bootstrapPages' ) );
		add_action( 'network_admin_enqueue_scripts', array( $this, 'enqueueAssets' ) );
	}

	/**
	 * Bootstrap page handlers.
	 *
	 * @return void
	 */
	public function bootstrapPages(): void {
		$this->topics_page->handlePost();
		$this->settings_page->handlePost();
	}

	/**
	 * Enqueue shared admin assets.
	 *
	 * @param string $hook_suffix Current hook.
	 * @return void
	 */
	public function enqueueAssets( string $hook_suffix ): void {
		if ( false === strpos( $hook_suffix, 'wp-helpdesk' ) ) {
			return;
		}

		wp_enqueue_style(
			'wp-helpdesk-admin',
			Helpers::pluginUrl( 'assets/css/admin-helpdesk.css' ),
			array(),
			HD_VERSION
		);

		wp_enqueue_script(
			'wp-helpdesk-admin',
			Helpers::pluginUrl( 'assets/js/admin-helpdesk.js' ),
			array( 'jquery' ),
			HD_VERSION,
			true
		);

		wp_localize_script(
			'wp-helpdesk-admin',
			'WPHelpdeskAdmin',
			array(
				'restNonce' => wp_create_nonce( 'wp_rest' ),
				'restBase'  => esc_url_raw( rest_url( Helpers::restNamespace() . '/admin/' ) ),
			)
		);
	}
}
