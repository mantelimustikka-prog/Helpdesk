<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use WPHelpdesk\Domain\Message\MessageService;
use WPHelpdesk\Domain\Ticket\TicketRepository;
use WPHelpdesk\Interfaces\Frontend\WooCommerceAccountHelpdesk;

require_once __DIR__ . '/bootstrap.php';

final class WooCommerceAccountHelpdeskTest extends TestCase {
	private TicketRepositoryDouble $ticket_repository;
	private MessageServiceDouble $message_service;
	private WooCommerceAccountHelpdesk $integration;

	protected function setUp(): void {
		wp_helpdesk_test_reset_state();
		$GLOBALS['wp_current_user_caps']['hd_manage_tickets'] = false;
		$this->ticket_repository = new TicketRepositoryDouble();
		$this->message_service   = new MessageServiceDouble();
		$this->integration       = new WooCommerceAccountHelpdesk( $this->ticket_repository, $this->message_service );
	}

	public function testMenuIncludesHelpdeskBeforeLogout(): void {
		$items = array(
			'dashboard'       => 'Dashboard',
			'orders'          => 'Orders',
			'customer-logout' => 'Log out',
		);

		$updated = $this->integration->addMenuItem( $items );

		self::assertSame( array( 'dashboard', 'orders', 'helpdesk', 'customer-logout' ), array_keys( $updated ) );
		self::assertSame( 'Helpdesk', $updated['helpdesk'] );
	}

	public function testEndpointRegistrationAddsRewriteEndpointAndQueryVar(): void {
		$this->integration->addEndpoint();
		$vars = $this->integration->addQueryVars( array( 'paged' ) );

		self::assertSame( 'helpdesk', $GLOBALS['wp_rewrite_endpoints'][0]['name'] );
		self::assertContains( 'helpdesk', $vars );
	}

	public function testRegisterAddsWooCommerceHooksWhenAccountPageIsAvailable(): void {
		$this->integration->register();

		self::assertArrayHasKey( 'init', $GLOBALS['wp_filters'] );
		self::assertArrayHasKey( 'query_vars', $GLOBALS['wp_filters'] );
		self::assertArrayHasKey( 'woocommerce_account_menu_items', $GLOBALS['wp_filters'] );
		self::assertArrayHasKey( 'woocommerce_account_helpdesk_endpoint', $GLOBALS['wp_filters'] );
	}

	public function testRegisterDoesNothingWhenWooCommerceAccountPageIsUnavailable(): void {
		$integration = new WooCommerceUnavailableAccountHelpdeskDouble( $this->ticket_repository, $this->message_service );

		$integration->register();

		self::assertArrayNotHasKey( 'init', $GLOBALS['wp_filters'] );
		self::assertArrayNotHasKey( 'query_vars', $GLOBALS['wp_filters'] );
		self::assertArrayNotHasKey( 'woocommerce_account_menu_items', $GLOBALS['wp_filters'] );
	}

	/**
	 * Regression: PHP Fatal error: Call to a member function get_page_permastruct() on null.
	 *
	 * When $wp_rewrite is null (plugins_loaded, CLI, cron, or REST-only contexts)
	 * getAccountPageUrl() must return '' and register() must not register any hooks,
	 * preventing a fatal in wp-includes/link-template.php.
	 */
	public function testRegisterDoesNothingWhenWpRewriteIsNull(): void {
		$GLOBALS['wp_rewrite'] = null;

		$this->integration->register();

		self::assertArrayNotHasKey( 'init', $GLOBALS['wp_filters'] );
		self::assertArrayNotHasKey( 'query_vars', $GLOBALS['wp_filters'] );
		self::assertArrayNotHasKey( 'woocommerce_account_menu_items', $GLOBALS['wp_filters'] );
		self::assertArrayNotHasKey( 'woocommerce_account_helpdesk_endpoint', $GLOBALS['wp_filters'] );
	}

	/**
	 * Regression: getInterfaceLinks must not include WooCommerce links when $wp_rewrite is null.
	 */
	public function testGetInterfaceLinksExcludesWooCommerceLinksWhenWpRewriteIsNull(): void {
		$GLOBALS['wp_rewrite'] = null;

		$links = $this->integration->getInterfaceLinks();

		$groups = array_column( $links, 'group' );
		self::assertNotContains( 'WooCommerce My Account', $groups );
		// Standalone pages must still be present.
		self::assertContains( 'Standalone Helpdesk pages', $groups );
	}

	/**
	 * Regression: register() must not fatal when WooCommerce class does not exist.
	 */
	public function testRegisterDoesNothingWhenWooCommerceClassAbsent(): void {
		$integration = new WooCommerceClassAbsentDouble( $this->ticket_repository, $this->message_service );

		$integration->register();

		self::assertArrayNotHasKey( 'init', $GLOBALS['wp_filters'] );
		self::assertArrayNotHasKey( 'woocommerce_account_menu_items', $GLOBALS['wp_filters'] );
	}

	/**
	 * Conflict-tolerance regression: a third-party filter running between our
	 * priority-40 and priority-9999 registrations that reconstructs the menu
	 * without the Helpdesk key must still end up with Helpdesk present because
	 * the late-priority safety-net callback re-inserts it.
	 */
	public function testMenuSurvivesCompetingFilterAtHigherPriority(): void {
		$this->integration->register();

		// Sanity-check: both our callbacks were registered.
		self::assertCount( 2, $GLOBALS['wp_filters']['woocommerce_account_menu_items'] );

		// Inject a competing callback that runs between our two registrations and
		// replaces the full menu with a whitelist that omits the Helpdesk item —
		// mimicking a theme/plugin that hard-codes the menu items it wants.
		$filters    = $GLOBALS['wp_filters']['woocommerce_account_menu_items'];
		$known_items = array(
			'dashboard'       => 'Dashboard',
			'orders'          => 'Orders',
			'customer-logout' => 'Log out',
		);
		$competing  = static function () use ( $known_items ): array {
			return $known_items;
		};
		$GLOBALS['wp_filters']['woocommerce_account_menu_items'] = array_merge(
			array_slice( $filters, 0, 1 ), // our priority-40 callback
			array( $competing ),           // simulated priority-50 third-party filter
			array_slice( $filters, 1 )     // our priority-9999 safety-net callback
		);

		$result = apply_filters( 'woocommerce_account_menu_items', $known_items );

		self::assertArrayHasKey( 'helpdesk', $result );
		self::assertSame( 'Helpdesk', $result['helpdesk'] );
	}

	/**
	 * Idempotency: calling addMenuItem when 'helpdesk' is already in the array
	 * must not insert a second copy (no duplicates).
	 */
	public function testAddMenuItemIsIdempotentNoDuplicates(): void {
		$items = array(
			'dashboard'       => 'Dashboard',
			'helpdesk'        => 'Helpdesk',
			'customer-logout' => 'Log out',
		);

		$once  = $this->integration->addMenuItem( $items );
		$twice = $this->integration->addMenuItem( $once );

		self::assertSame( 1, array_count_values( array_keys( $twice ) )['helpdesk'] );
		self::assertSame( array_keys( $once ), array_keys( $twice ) );
	}

	/**
	 * Conflict-tolerance: a competing filter that iterates known keys and
	 * unsets anything it does not recognise (another common pattern used by
	 * themes) must still produce a final array that contains Helpdesk because
	 * the priority-9999 safety-net re-inserts it.
	 */
	public function testMenuSurvivesCompetingFilterThatUnsetsUnknownKeys(): void {
		$this->integration->register();

		$whitelist  = array( 'dashboard', 'orders', 'customer-logout' );
		$competing  = static function ( array $items ) use ( $whitelist ): array {
			return array_intersect_key( $items, array_flip( $whitelist ) );
		};

		// Insert the aggressive filter between our priority-40 and priority-9999 hooks.
		$filters = $GLOBALS['wp_filters']['woocommerce_account_menu_items'];
		$GLOBALS['wp_filters']['woocommerce_account_menu_items'] = array_merge(
			array_slice( $filters, 0, 1 ),
			array( $competing ),
			array_slice( $filters, 1 )
		);

		$base_items = array(
			'dashboard'       => 'Dashboard',
			'orders'          => 'Orders',
			'customer-logout' => 'Log out',
		);

		$result = apply_filters( 'woocommerce_account_menu_items', $base_items );

		self::assertArrayHasKey( 'helpdesk', $result );
		self::assertSame( 'Helpdesk', $result['helpdesk'] );
		// No duplicates.
		self::assertSame( 1, array_count_values( array_keys( $result ) )['helpdesk'] );
	}

	/**
	 * Boot-order regression: when register() runs during the init action
	 * (doing_action returns true) the endpoint must be registered directly
	 * rather than deferred via an additional add_action('init') call.
	 */
	public function testRegisterCallsAddEndpointDirectlyWhenDoingInit(): void {
		$integration = new WooCommerceDoingInitDouble( $this->ticket_repository, $this->message_service );

		$init_callbacks_before = count( $GLOBALS['wp_filters']['init'] ?? array() );

		$integration->register();

		// addEndpoint() should have been called directly — confirm rewrite endpoint is present.
		self::assertNotEmpty( $GLOBALS['wp_rewrite_endpoints'] );
		self::assertSame( 'helpdesk', $GLOBALS['wp_rewrite_endpoints'][0]['name'] );
		// The number of init callbacks must not have grown (no extra add_action('init') scheduled).
		self::assertCount( $init_callbacks_before, $GLOBALS['wp_filters']['init'] ?? array() );
	}

	/**
	 * Boot-order regression: when register() runs before init the endpoint
	 * must be deferred via add_action('init') as usual.
	 */
	public function testRegisterSchedulesAddEndpointWhenNotDoingInit(): void {
		// Default test environment: doing_action('init') returns false.
		$this->integration->register();

		// init callback must have been scheduled (not immediately called).
		self::assertArrayHasKey( 'init', $GLOBALS['wp_filters'] );
		// Rewrite endpoint should NOT have been registered yet.
		self::assertEmpty( $GLOBALS['wp_rewrite_endpoints'] );
	}

	public function testRenderOverviewShowsEmptyStateCta(): void {
		$GLOBALS['wp_query_vars']['helpdesk'] = '';

		ob_start();
		$this->integration->render();
		$output = (string) ob_get_clean();

		self::assertStringContainsString( 'My Account / Helpdesk', $output );
		self::assertStringContainsString( 'No requests yet', $output );
		self::assertStringContainsString( 'Create request', $output );
	}

	public function testRenderNewRequestSubviewShowsMemberFormEntryPoint(): void {
		$GLOBALS['wp_query_vars']['helpdesk'] = 'new';

		ob_start();
		$this->integration->render();
		$output = (string) ob_get_clean();

		self::assertStringContainsString( 'Open request form', $output );
		self::assertStringContainsString( '/helpdesk/member/new/', $output );
	}

	public function testRenderRequestsSubviewShowsOwnedTickets(): void {
		$GLOBALS['wp_query_vars']['helpdesk'] = 'requests';
		$this->ticket_repository->tickets     = array(
			array(
				'id'         => 11,
				'ticket_no'  => 'HD-10011',
				'user_id'    => 7,
				'subject'    => 'Need billing help',
				'status'     => 'in_progress',
				'updated_at' => '2026-08-19 07:00:00',
			),
		);

		ob_start();
		$this->integration->render();
		$output = (string) ob_get_clean();

		self::assertStringContainsString( 'Need billing help', $output );
		self::assertStringContainsString( 'in_progress', $output );
		self::assertStringContainsString( '/my-account/helpdesk/request/HD-10011/', $output );
	}

	public function testRenderDetailShowsMessagesAndReplyFormForOwnedTicket(): void {
		$GLOBALS['wp_query_vars']['helpdesk'] = 'request/HD-10011';
		$this->ticket_repository->ticket      = array(
			'id'              => 11,
			'ticket_no'       => 'HD-10011',
			'user_id'         => 7,
			'requester_email' => 'agent@example.test',
			'subject'         => 'Need billing help',
			'status'          => 'waiting_customer',
		);
		$this->message_service->messages      = array(
			array(
				'id'          => 51,
				'author_type' => 'agent',
				'created_at'  => '2026-08-19 07:10:00',
				'body'        => 'We need one more detail.',
			),
		);

		ob_start();
		$this->integration->render();
		$output = (string) ob_get_clean();

		self::assertStringContainsString( 'Need billing help', $output );
		self::assertStringContainsString( 'waiting_customer', $output );
		self::assertStringContainsString( 'Support', $output );
		self::assertStringContainsString( 'Send a reply', $output );
	}

	public function testRenderDetailBlocksUnownedTicket(): void {
		$GLOBALS['wp_query_vars']['helpdesk'] = 'request/HD-10011';
		$this->ticket_repository->ticket      = array(
			'id'              => 11,
			'ticket_no'       => 'HD-10011',
			'user_id'         => 99,
			'requester_email' => 'other@example.test',
			'subject'         => 'Need billing help',
			'status'          => 'waiting_customer',
		);

		ob_start();
		$this->integration->render();
		$output = (string) ob_get_clean();

		self::assertStringContainsString( 'Request not found', $output );
	}

	public function testReplySubmissionPostsMemberMessageAndRedirects(): void {
		$GLOBALS['wp_query_vars']['helpdesk'] = 'request/HD-10011';
		$this->ticket_repository->ticket      = array(
			'id'              => 11,
			'ticket_no'       => 'HD-10011',
			'user_id'         => 7,
			'requester_email' => 'agent@example.test',
			'subject'         => 'Need billing help',
			'status'          => 'waiting_customer',
		);
		$this->message_service->reply_id      = 88;
		$_SERVER['REQUEST_METHOD']            = 'POST';
		$_POST = array(
			'hd_helpdesk_action'        => 'reply',
			'hd_my_account_reply_nonce' => 'valid-reply-nonce',
			'hd_helpdesk_reply_body'    => 'Here is my extra detail.',
		);
		$GLOBALS['wp_valid_nonces']['hd_my_account_reply'] = 'valid-reply-nonce';

		$this->integration->render();

		self::assertSame( 11, $this->message_service->posted_ticket_id );
		self::assertSame( 'member', $this->message_service->posted_author_type );
		self::assertSame( 'Here is my extra detail.', $this->message_service->posted_body );
		self::assertSame( 'https://example.test/my-account/helpdesk/request/HD-10011/', $GLOBALS['wp_safe_redirect_to'] );
	}

	public function testRenderRequiresAuthentication(): void {
		$GLOBALS['wp_logged_in']             = false;
		$GLOBALS['wp_query_vars']['helpdesk'] = '';

		ob_start();
		$this->integration->render();
		$output = (string) ob_get_clean();

		self::assertStringContainsString( 'Please sign in to access your helpdesk requests.', $output );
	}
}

final class TicketRepositoryDouble extends TicketRepository {
	/** @var array<int, array<string, mixed>> */
	public array $tickets = array();

	/** @var array<string, mixed>|null */
	public ?array $ticket = null;

	public function listForUser( int $network_id, int $user_id, string $email, array $args = array() ): array {
		return $this->tickets;
	}

	public function findByTicketNo( string $ticket_no, int $network_id ): ?array {
		return $this->ticket;
	}
}

final class MessageServiceDouble extends MessageService {
	/** @var array<int, array<string, mixed>> */
	public array $messages = array();
	public int $reply_id = 0;
	public int $posted_ticket_id = 0;
	public string $posted_body = '';
	public string $posted_author_type = '';

	public function listMessages( int $ticket_id, array $args = array() ): array {
		return $this->messages;
	}

	public function postReply(
		int $ticket_id,
		string $body,
		string $author_type = 'agent',
		?int $author_user_id = null,
		bool $is_internal = false
	): int {
		$this->posted_ticket_id   = $ticket_id;
		$this->posted_body        = $body;
		$this->posted_author_type = $author_type;

		return $this->reply_id;
	}

	public function getMessage( int $id ): ?array {
		return array(
			'id'          => $id,
			'author_type' => 'member',
			'body'        => $this->posted_body,
		);
	}
}

final class WooCommerceUnavailableAccountHelpdeskDouble extends WooCommerceAccountHelpdesk {
	protected function getAccountPageUrl(): string {
		return '';
	}
}

/** Simulates an environment where the WooCommerce core class is not loaded. */
final class WooCommerceClassAbsentDouble extends WooCommerceAccountHelpdesk {
	protected function isWooCommerceAvailable(): bool {
		return false;
	}
}

/**
 * Simulates the environment where register() is invoked while the 'init'
 * action is currently firing (doing_action('init') === true).
 */
final class WooCommerceDoingInitDouble extends WooCommerceAccountHelpdesk {
	protected function isWooCommerceAvailable(): bool {
		return true;
	}

	public function register(): void {
		// Override the global doing_action stub for the duration of this call.
		$GLOBALS['wp_doing_action']['init'] = true;
		parent::register();
		$GLOBALS['wp_doing_action']['init'] = false;
	}
}
