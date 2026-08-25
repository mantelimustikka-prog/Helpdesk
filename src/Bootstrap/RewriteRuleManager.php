<?php
/**
 * @package WP Helpdesk
 */

namespace WPHelpdesk\Bootstrap;

use WPHelpdesk\Support\Constants;
use WPHelpdesk\Infrastructure\Logger;

/**
 * Manages registration and health of WP Helpdesk rewrite rules.
 *
 * Designed to be called on every bootstrap and on each front-end request so
 * that missing or stale rules are detected and corrected automatically,
 * particularly in multisite networks where per-site activation hooks may not
 * have fired correctly for every sub-site.
 */
class RewriteRuleManager {

	/**
	 * Rewrite rule pattern used as a canary to detect missing rules.
	 */
	private const CANARY_PATTERN = '^helpdesk/?$';

	/**
	 * Option key used to throttle automatic flush checks (once per day per site).
	 */
	public const OPTION_LAST_RULE_CHECK = 'hd_rewrite_last_check';

	protected Logger $logger;

	public function __construct( ?Logger $logger = null ) {
		$this->logger = $logger ?: new Logger();
	}

	/**
	 * Verify that the helpdesk rewrite rules are registered; if they are
	 * missing, flush and re-register them automatically.
	 *
	 * Safe to call on every request — the expensive flush is rate-limited to
	 * once per day using a per-site option, and always skipped when a version
	 * mismatch flush would happen anyway (that path already flushes).
	 *
	 * @return void
	 */
	public function ensureRulesExist(): void {
		// Fast path: version is current and rules exist — nothing to do.
		$stored_version = (string) get_option( Constants::OPTION_REWRITE_VERSION, '' );
		if ( $stored_version === Constants::REWRITE_VERSION && ! $this->detectMissingRules() ) {
			return;
		}

		// Rate-limit the flush to once per day to avoid performance impact on
		// every request when rules are inexplicably missing.
		$last_check = (int) get_option( self::OPTION_LAST_RULE_CHECK, 0 );
		if ( ( time() - $last_check ) < DAY_IN_SECONDS && $stored_version === Constants::REWRITE_VERSION ) {
			return;
		}

		$this->forceFlushAndRegister();
	}

	/**
	 * Return true when the canary rewrite rule is absent from WordPress's
	 * compiled rewrite rule set.
	 *
	 * @return bool
	 */
	public function detectMissingRules(): bool {
		if ( ! function_exists( 'get_option' ) ) {
			return false;
		}

		// WordPress stores compiled rewrite rules in the 'rewrite_rules' option.
		$rules = get_option( 'rewrite_rules', array() );
		if ( ! is_array( $rules ) ) {
			return true;
		}

		return ! array_key_exists( self::CANARY_PATTERN, $rules );
	}

	/**
	 * Re-register all helpdesk rewrite rules and immediately flush WordPress's
	 * compiled rule cache.
	 *
	 * @return void
	 */
	public function forceFlushAndRegister(): void {
		// Re-add the rewrite rules so they are picked up by the flush below.
		$this->addRewriteRules();

		flush_rewrite_rules( false );

		update_option( Constants::OPTION_REWRITE_VERSION, Constants::REWRITE_VERSION );
		update_option( self::OPTION_LAST_RULE_CHECK, time() );

		$this->logger->info( 'WP Helpdesk: rewrite rules were missing or stale — flushed and re-registered.' );
	}

	/**
	 * Register all helpdesk rewrite rules with WordPress.
	 *
	 * Mirrors the rules registered in FrontendRouter so they can be added
	 * independently when the router has not yet been initialised.
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
}
