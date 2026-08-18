<?php
/**
 * @package WP Helpdesk
 */

namespace WPHelpdesk\Bootstrap;

use WPHelpdesk\Support\Constants;

/**
 * Ensures the three helpdesk WP pages exist and stores their IDs in blog
 * options so the frontend router can look them up.
 *
 * All public methods are safe to call repeatedly (idempotent).
 */
class PageBootstrapper {

	/**
	 * Pages to ensure.  Key = option name, value = page config.
	 *
	 * @var array<string, array{title:string, slug:string}>
	 */
	private const PAGES = array(
		Constants::OPTION_PAGE_INDEX      => array(
			'title' => 'Helpdesk',
			'slug'  => 'helpdesk',
		),
		Constants::OPTION_PAGE_NEW        => array(
			'title' => 'Submit a Request',
			'slug'  => 'helpdesk-new',
		),
		Constants::OPTION_PAGE_MEMBER_NEW => array(
			'title' => 'Submit a Request (Members)',
			'slug'  => 'helpdesk-member-new',
		),
	);

	/**
	 * Ensure all three helpdesk pages exist for the current site.
	 *
	 * On a multisite network activation the caller is expected to iterate
	 * over sites and call this method per-site (switching blogs as needed).
	 *
	 * @return void
	 */
	public static function ensurePages(): void {
		foreach ( self::PAGES as $option_key => $page_config ) {
			$stored_id = (int) get_option( $option_key, 0 );

			// Reuse existing page if it is still published.
			if ( $stored_id > 0 ) {
				$post = get_post( $stored_id );
				if ( $post instanceof \WP_Post && 'publish' === $post->post_status ) {
					continue;
				}
			}

			// Search for an existing page with this slug before creating one.
			$existing = get_page_by_path( sanitize_title( $page_config['slug'] ), OBJECT, 'page' );
			if ( $existing instanceof \WP_Post && 'trash' !== $existing->post_status ) {
				update_option( $option_key, (int) $existing->ID );
				continue;
			}

			// Create a minimal placeholder page.
			$page_id = wp_insert_post(
				array(
					'post_title'   => sanitize_text_field( $page_config['title'] ),
					'post_name'    => sanitize_title( $page_config['slug'] ),
					'post_status'  => 'publish',
					'post_type'    => 'page',
					'post_content' => '',
					'post_author'  => self::resolveAuthorId(),
				),
				false
			);

			if ( ! is_wp_error( $page_id ) && $page_id > 0 ) {
				update_option( $option_key, (int) $page_id );
			}
		}
	}

	/**
	 * Retrieve the stored page ID for a given route option key.
	 *
	 * @param string $option_key One of the Constants::OPTION_PAGE_* keys.
	 * @return int|null Page ID or null when not found / not published.
	 */
	public static function getPageId( string $option_key ): ?int {
		$id = (int) get_option( $option_key, 0 );
		if ( $id <= 0 ) {
			return null;
		}

		$post = get_post( $id );
		if ( ! $post instanceof \WP_Post || 'publish' !== $post->post_status ) {
			return null;
		}

		return $id;
	}

	/**
	 * Resolve a safe author ID for page creation.
	 *
	 * Uses the current user when available, otherwise falls back to the first
	 * administrator account, and finally to 0 (no author required for pages).
	 *
	 * @return int
	 */
	private static function resolveAuthorId(): int {
		$current_id = (int) get_current_user_id();
		if ( $current_id > 0 ) {
			return $current_id;
		}

		$admins = get_users(
			array(
				'role'   => 'administrator',
				'number' => 1,
				'fields' => 'ID',
			)
		);

		return ! empty( $admins ) ? (int) $admins[0] : 0;
	}
}
