<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use WPHelpdesk\Bootstrap\RewriteRuleManager;
use WPHelpdesk\Support\Constants;

require_once __DIR__ . '/bootstrap.php';

final class RewriteRuleManagerTest extends TestCase {

	protected function setUp(): void {
		wp_helpdesk_test_reset_state();
	}

	protected function tearDown(): void {
		wp_helpdesk_test_reset_state();
	}

	// -------------------------------------------------------------------------
	// detectMissingRules()
	// -------------------------------------------------------------------------

	public function testDetectMissingRulesReturnsTrueWhenOptionIsEmpty(): void {
		// 'rewrite_rules' option is absent by default.
		$manager = new RewriteRuleManager();
		self::assertTrue( $manager->detectMissingRules() );
	}

	public function testDetectMissingRulesReturnsTrueWhenCanaryAbsent(): void {
		$GLOBALS['wp_options']['rewrite_rules'] = array(
			'^some-other-rule/?$' => 'index.php?page_id=1',
		);

		$manager = new RewriteRuleManager();
		self::assertTrue( $manager->detectMissingRules() );
	}

	public function testDetectMissingRulesReturnsFalseWhenCanaryPresent(): void {
		$GLOBALS['wp_options']['rewrite_rules'] = array(
			'^helpdesk/?$' => 'index.php?hd_page=index',
		);

		$manager = new RewriteRuleManager();
		self::assertFalse( $manager->detectMissingRules() );
	}

	// -------------------------------------------------------------------------
	// addRewriteRules()
	// -------------------------------------------------------------------------

	public function testAddRewriteRulesRegistersAllHelpdeskPatterns(): void {
		$manager = new RewriteRuleManager();
		$manager->addRewriteRules();

		$patterns = array_column( $GLOBALS['wp_rewrite_rules'], 0 );

		self::assertContains( '^helpdesk/?$', $patterns );
		self::assertContains( '^helpdesk/new/?$', $patterns );
		self::assertContains( '^helpdesk/member/new/?$', $patterns );
		self::assertContains( '^helpdesk/requests/?$', $patterns );
		self::assertContains( '^helpdesk/request/([^/]+)/?$', $patterns );
		self::assertContains( '^helpdesk/ticket/([^/]+)/([^/]+)/?$', $patterns );
	}

	// -------------------------------------------------------------------------
	// forceFlushAndRegister()
	// -------------------------------------------------------------------------

	public function testForceFlushAndRegisterCallsFlushRewriteRules(): void {
		$manager = new RewriteRuleManager();
		$manager->forceFlushAndRegister();

		self::assertNotEmpty( $GLOBALS['wp_flush_rewrite_rules_calls'] );
		self::assertFalse( $GLOBALS['wp_flush_rewrite_rules_calls'][0] );
	}

	public function testForceFlushAndRegisterUpdatesRewriteVersionOption(): void {
		$manager = new RewriteRuleManager();
		$manager->forceFlushAndRegister();

		self::assertSame(
			Constants::REWRITE_VERSION,
			$GLOBALS['wp_options'][ Constants::OPTION_REWRITE_VERSION ]
		);
	}

	public function testForceFlushAndRegisterUpdatesLastCheckOption(): void {
		$manager = new RewriteRuleManager();
		$before  = time();
		$manager->forceFlushAndRegister();
		$after = time();

		$stored = (int) $GLOBALS['wp_options'][ RewriteRuleManager::OPTION_LAST_RULE_CHECK ];
		self::assertGreaterThanOrEqual( $before, $stored );
		self::assertLessThanOrEqual( $after, $stored );
	}

	// -------------------------------------------------------------------------
	// ensureRulesExist()
	// -------------------------------------------------------------------------

	public function testEnsureRulesExistDoesNotFlushWhenVersionCurrentAndRulesPresent(): void {
		$GLOBALS['wp_options'][ Constants::OPTION_REWRITE_VERSION ] = Constants::REWRITE_VERSION;
		$GLOBALS['wp_options']['rewrite_rules'] = array(
			'^helpdesk/?$' => 'index.php?hd_page=index',
		);

		$manager = new RewriteRuleManager();
		$manager->ensureRulesExist();

		self::assertEmpty( $GLOBALS['wp_flush_rewrite_rules_calls'] );
	}

	public function testEnsureRulesExistFlushesWhenVersionMismatch(): void {
		// Version stored does not match the constant.
		$GLOBALS['wp_options'][ Constants::OPTION_REWRITE_VERSION ] = 'old_version';
		// Rules are present (so detectMissingRules returns false), but version
		// mismatch should still trigger a flush.
		$GLOBALS['wp_options']['rewrite_rules'] = array(
			'^helpdesk/?$' => 'index.php?hd_page=index',
		);

		$manager = new RewriteRuleManager();
		$manager->ensureRulesExist();

		self::assertNotEmpty( $GLOBALS['wp_flush_rewrite_rules_calls'] );
	}

	public function testEnsureRulesExistFlushesWhenRulesMissingAndRateLimitExpired(): void {
		$GLOBALS['wp_options'][ Constants::OPTION_REWRITE_VERSION ]        = Constants::REWRITE_VERSION;
		$GLOBALS['wp_options'][ RewriteRuleManager::OPTION_LAST_RULE_CHECK ] = time() - DAY_IN_SECONDS - 1;
		// No canary rewrite rule → detectMissingRules() returns true.

		$manager = new RewriteRuleManager();
		$manager->ensureRulesExist();

		self::assertNotEmpty( $GLOBALS['wp_flush_rewrite_rules_calls'] );
	}

	public function testEnsureRulesExistSkipsFlushWhenRulesMissingButRateLimitActive(): void {
		$GLOBALS['wp_options'][ Constants::OPTION_REWRITE_VERSION ]        = Constants::REWRITE_VERSION;
		$GLOBALS['wp_options'][ RewriteRuleManager::OPTION_LAST_RULE_CHECK ] = time() - 100; // Recent check.
		// No canary rewrite rule → detectMissingRules() returns true.

		$manager = new RewriteRuleManager();
		$manager->ensureRulesExist();

		self::assertEmpty( $GLOBALS['wp_flush_rewrite_rules_calls'] );
	}
}
