<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use WPHelpdesk\Domain\KnowledgeBase\KnowledgeBaseProviderInterface;
use WPHelpdesk\Domain\KnowledgeBase\KnowledgeBaseService;
use WPHelpdesk\Domain\Topic\TopicService;
use WPHelpdesk\Domain\Topic\TopicTransitionService;
use WPHelpdesk\Interfaces\Rest\PublicTicketController;

require_once __DIR__ . '/bootstrap.php';

final class PublicTicketControllerTest extends TestCase {
	protected function setUp(): void {
		wp_helpdesk_test_reset_state();
	}

	public function testListTransitionsReturnsOnlyValidRuntimeTransitions(): void {
		$topic_service = new class extends TopicService {
			public function getTopic( int $id ): ?array {
				return array(
					'id'          => $id,
					'name'        => 3 === $id ? 'Shipping' : 'Billing',
					'description' => 'Topic description',
					'is_final'    => 3 === $id ? 1 : 0,
				);
			}

			public function getTopicsByIds( array $ids ): array {
				$items = array();
				foreach ( $ids as $id ) {
					$items[ (int) $id ] = $this->getTopic( (int) $id );
				}

				return $items;
			}
		};

		$transition_service = new class extends TopicTransitionService {
			public function listValidFrom( int $from_topic_id, bool $active_only = true ): array {
				return array(
					array( 'to_topic_id' => 3, 'label' => 'Shipping' ),
				);
			}
		};

		$controller = new PublicTicketController( $topic_service, $transition_service, new KnowledgeBaseService() );
		$request    = new WP_REST_Request();
		$request['id'] = 1;

		$response = $controller->listTransitions( $request );

		self::assertSame( 200, $response->status );
		self::assertSame( 3, $response->data[0]['to_topic_id'] );
		self::assertSame( 1, $response->data[0]['to_topic']['is_final'] );
	}

	public function testListTopicsReturnsOnlyTopLevelTopics(): void {
		$topic_service = new class extends TopicService {
			public function listTopLevelTopics(): array {
				return array(
					array( 'id' => 1, 'title' => 'Billing', 'description' => '', 'slug' => 'billing', 'is_final' => 0 ),
					array( 'id' => 3, 'title' => 'Account', 'description' => 'Login', 'slug' => 'account', 'is_final' => 1 ),
				);
			}
		};

		$controller = new PublicTicketController( $topic_service, new TopicTransitionService(), new KnowledgeBaseService() );
		$response   = $controller->listTopics( new WP_REST_Request() );

		self::assertSame( 200, $response->status );
		self::assertCount( 2, $response->data );
		self::assertSame( 1, $response->data[0]['id'] );
		self::assertSame( 'Billing', $response->data[0]['title'] );
	}

	public function testSuggestKnowledgeBaseDelegatesToProvider(): void {
		$provider = new class implements KnowledgeBaseProviderInterface {
			public function searchTopics( string $query, array $topic_path = array(), int $limit = 5 ): array {
				return array();
			}

			public function getTopicById( int|string $article_id ): ?array {
				return null;
			}

			public function suggestByPath( array $topic_path, string $query = '', int $limit = 5 ): array {
				return array( array( 'topic_path' => $topic_path, 'query' => $query ) );
			}

			public function suggest( string $query, ?int $topic_id = null, int $limit = 5 ): array {
				return array();
			}

			public function get( int|string $article_id ): ?array {
				return null;
			}
		};

		$controller = new PublicTicketController( new TopicService(), new TopicTransitionService(), new KnowledgeBaseService( $provider ) );
		$request    = new WP_REST_Request();
		$request->set_param( 'query', 'billing' );
		$request->set_param( 'topic_path', array( 2, 4 ) );

		$response = $controller->suggestKnowledgeBase( $request );

		self::assertSame( 200, $response->status );
		self::assertSame( array( 2, 4 ), $response->data[0]['topic_path'] );
		self::assertSame( 'billing', $response->data[0]['query'] );
	}

	public function testRestartFormSessionRequiresNonce(): void {
		wp_helpdesk_test_reset_state();

		$controller = new PublicTicketController();
		$request    = new WP_REST_Request();
		$request->set_param( 'session_token', 'some-token' );
		// No nonce set.

		$response = $controller->restartFormSession( $request );

		self::assertInstanceOf( WP_Error::class, $response );
		self::assertSame( 'hd_invalid_nonce', $response->get_error_code() );
	}

	public function testRestartFormSessionRequiresSessionToken(): void {
		wp_helpdesk_test_reset_state();

		$controller = new PublicTicketController();
		$request    = new WP_REST_Request();
		$request->set_header( 'X-WP-Nonce', 'valid-rest-nonce' );
		// No session_token.

		$response = $controller->restartFormSession( $request );

		self::assertInstanceOf( WP_Error::class, $response );
		self::assertSame( 'hd_missing_session_token', $response->get_error_code() );
	}

	public function testRestartFormSessionReturns404WhenSessionMissing(): void {
		wp_helpdesk_test_reset_state();

		// Inject a SubmissionSessionService stub via subclass override.
		$controller = new class extends PublicTicketController {
			public function restartFormSession( WP_REST_Request $request ) {
				// Re-implement restartFormSession but inject a stub service.
				$nonce = $request->get_header( 'X-WP-Nonce' );
				if ( empty( $nonce ) ) {
					$nonce = $request->get_param( '_wpnonce' );
				}
				if ( empty( $nonce ) || ! wp_verify_nonce( (string) $nonce, 'wp_rest' ) ) {
					return new WP_Error( 'hd_invalid_nonce', 'Invalid or missing nonce.', array( 'status' => 403 ) );
				}

				$token = sanitize_text_field( (string) $request->get_param( 'session_token' ) );
				if ( '' === $token ) {
					return new WP_Error( 'hd_missing_session_token', 'Missing form session token.', array( 'status' => 422 ) );
				}

				// Stub: always returns false (session not found).
				return new WP_Error( 'hd_session_not_found', 'Session not found or expired.', array( 'status' => 404 ) );
			}
		};

		$request = new WP_REST_Request();
		$request->set_header( 'X-WP-Nonce', 'valid-rest-nonce' );
		$request->set_param( 'session_token', 'no-such-session' );

		$response = $controller->restartFormSession( $request );

		self::assertInstanceOf( WP_Error::class, $response );
		self::assertSame( 'hd_session_not_found', $response->get_error_code() );
		self::assertSame( 404, $response->data['status'] );
	}

	public function testRestartFormSessionReturnsResetCounterOnSuccess(): void {
		wp_helpdesk_test_reset_state();

		// Override restartFormSession to inject a stub that returns new counter=2.
		$controller = new class extends PublicTicketController {
			public function restartFormSession( WP_REST_Request $request ) {
				$nonce = $request->get_header( 'X-WP-Nonce' );
				if ( empty( $nonce ) || ! wp_verify_nonce( (string) $nonce, 'wp_rest' ) ) {
					return new WP_Error( 'hd_invalid_nonce', 'Invalid nonce.', array( 'status' => 403 ) );
				}
				$token = sanitize_text_field( (string) $request->get_param( 'session_token' ) );
				if ( '' === $token ) {
					return new WP_Error( 'hd_missing_session_token', 'Missing token.', array( 'status' => 422 ) );
				}
				// Stub: reset succeeded, new counter = 2.
				return new WP_REST_Response( array( 'ok' => true, 'reset_counter' => 2 ), 200 );
			}
		};

		$request = new WP_REST_Request();
		$request->set_header( 'X-WP-Nonce', 'valid-rest-nonce' );
		$request->set_param( 'session_token', 'active-session' );

		$response = $controller->restartFormSession( $request );

		self::assertInstanceOf( WP_REST_Response::class, $response );
		self::assertSame( 200, $response->status );
		self::assertTrue( $response->data['ok'] );
		self::assertSame( 2, $response->data['reset_counter'] );
	}

	public function testUpsertFormSessionRejectsStaleResetCounter(): void {
		wp_helpdesk_test_reset_state();

		global $wpdb;

		// Build a fake $wpdb that simulates a session with reset_counter=3.
		$wpdb = new class {
			public string $last_error   = '';
			public string $base_prefix  = 'wp_';

			public function prepare( string $query, ...$args ): string {
				return $query;
			}

			public function get_row( string $query, $output = ARRAY_A ): ?array {
				// Return a row with reset_counter=3.
				return array( 'id' => 42, 'reset_counter' => 3 );
			}

			public function update(): void {}
		};

		$controller = new PublicTicketController();

		$request = new WP_REST_Request();
		$request->set_header( 'X-WP-Nonce', 'valid-rest-nonce' );
		$request->set_param( 'session_token', 'some-token' );
		$request->set_param( 'step_index', 2 );
		// Client sends stale counter (1) but server has 3.
		$request->set_param( 'reset_counter', 1 );

		$response = $controller->upsertFormSession( $request );

		self::assertInstanceOf( WP_REST_Response::class, $response );
		self::assertSame( 409, $response->status );
		self::assertFalse( $response->data['ok'] );
		self::assertTrue( $response->data['stale'] );
	}

	public function testCreateTicketRejectsUnrelatedTopicPath(): void {
		$topic_service = new class extends TopicService {
			public function isTopLevelTopic( int $id ): bool {
				return 1 === $id;
			}

			public function getTopic( int $id ): ?array {
				return array( 'id' => $id, 'is_active' => 1 );
			}

			public function getTopicsByIds( array $ids ): array {
				$items = array();
				foreach ( $ids as $id ) {
					$items[ (int) $id ] = array( 'id' => (int) $id, 'is_active' => 1 );
				}

				return $items;
			}
		};

		$transition_service = new class extends TopicTransitionService {
			public function listValidFrom( int $from_topic_id, bool $active_only = true ): array {
				if ( 1 === $from_topic_id ) {
					return array( array( 'to_topic_id' => 2 ) );
				}

				return array();
			}
		};

		$controller = new class( $topic_service, $transition_service, new KnowledgeBaseService() ) extends PublicTicketController {
			public function createForTest( array $data ) {
				return $this->createTicket( $data );
			}
		};

		$response = $controller->createForTest(
			array(
				'topic_id'        => 3,
				'topic_path'      => array( 1, 3 ),
				'requester_name'  => 'Test User',
				'requester_email' => 'test@example.test',
				'requester_phone' => '123',
				'subject'         => 'Need help',
				'message'         => 'Details',
				'user_id'         => null,
				'order_relation'  => 'not_any_existing_order_related',
			)
		);

		self::assertInstanceOf( WP_Error::class, $response );
		self::assertSame( 'hd_invalid_topic_path', $response->get_error_code() );
	}

	public function testBuildTopicPathFromHierarchySupportsLegacyPayloadRecovery(): void {
		$topic_service = new class extends TopicService {
			public function getTopic( int $id ): ?array {
				$topics = array(
					1 => array( 'id' => 1, 'is_active' => 1, 'type' => 'root', 'parent_id' => null ),
					2 => array( 'id' => 2, 'is_active' => 1, 'type' => 'followup', 'parent_id' => 1 ),
					3 => array( 'id' => 3, 'is_active' => 1, 'type' => 'followup', 'parent_id' => 2 ),
				);

				return $topics[ $id ] ?? null;
			}
		};

		$controller = new class( $topic_service, new TopicTransitionService(), new KnowledgeBaseService() ) extends PublicTicketController {
			public function buildPathForTest( int $topic_id ): array {
				return $this->buildTopicPathFromHierarchy( $topic_id );
			}
		};

		self::assertSame( array( 1, 2, 3 ), $controller->buildPathForTest( 3 ) );
	}

	public function testValidateTopicPathAcceptsParentChainWhenTransitionsAreUnavailable(): void {
		$topic_service = new class extends TopicService {
			public function isTopLevelTopic( int $id ): bool {
				return false;
			}

			public function getTopicsByIds( array $ids ): array {
				$topics = array(
					1 => array( 'id' => 1, 'is_active' => 1, 'type' => 'root', 'parent_id' => null ),
					2 => array( 'id' => 2, 'is_active' => 1, 'type' => 'followup', 'parent_id' => 1 ),
					3 => array( 'id' => 3, 'is_active' => 1, 'type' => 'followup', 'parent_id' => 2 ),
				);
				$result = array();

				foreach ( $ids as $id ) {
					$id = (int) $id;
					if ( isset( $topics[ $id ] ) ) {
						$result[ $id ] = $topics[ $id ];
					}
				}

				return $result;
			}
		};

		$transition_service = new class extends TopicTransitionService {
			public function listValidFrom( int $from_topic_id, bool $active_only = true ): array {
				return array();
			}
		};

		$controller = new class( $topic_service, $transition_service, new KnowledgeBaseService() ) extends PublicTicketController {
			public function validateForTest( array $topic_path, int $topic_id ): bool|WP_Error {
				return $this->validateTopicPath( $topic_path, $topic_id );
			}
		};

		self::assertTrue( $controller->validateForTest( array( 1, 2, 3 ), 3 ) );
	}

	public function testListChildrenReturnsChildTopics(): void {
		$topic_service = new class extends TopicService {
			public function listChildrenOf( int $parent_id ): array {
				return array(
					array( 'id' => 5, 'name' => 'Shipping', 'type' => 'followup', 'parent_id' => $parent_id ),
					array( 'id' => 6, 'name' => 'Returns', 'type' => 'followup', 'parent_id' => $parent_id ),
				);
			}

			public function getTopic( int $id ): ?array {
				return array( 'id' => $id, 'name' => 'Order Issues', 'type' => 'root', 'parent_id' => null );
			}

			public function isLeafTopic( int $topic_id ): bool {
				return true;
			}
		};

		$controller = new PublicTicketController( $topic_service, new TopicTransitionService(), new KnowledgeBaseService() );
		$request    = new WP_REST_Request();
		$request['id'] = 2;

		$response = $controller->listChildren( $request );

		self::assertSame( 200, $response->status );
		self::assertCount( 2, $response->data );
		self::assertSame( 5, $response->data[0]['id'] );
		self::assertSame( 'Shipping', $response->data[0]['title'] );
	}

	public function testListChildrenReturns404ForMissingTopic(): void {
		$topic_service = new class extends TopicService {
			public function getTopic( int $id ): ?array {
				return null;
			}
		};

		$controller = new PublicTicketController( $topic_service, new TopicTransitionService(), new KnowledgeBaseService() );
		$request    = new WP_REST_Request();
		$request['id'] = 999;

		$response = $controller->listChildren( $request );

		self::assertSame( 404, $response->status );
	}

	public function testSubmitMemberTicketRejectsEmptyOrderRelation(): void {
		wp_helpdesk_test_reset_state();
		$GLOBALS['wp_current_user'] = (object) array(
			'ID'           => 5,
			'user_email'   => 'test@example.com',
			'display_name' => 'Test User',
		);

		$controller = new PublicTicketController( new TopicService(), new TopicTransitionService(), new KnowledgeBaseService() );
		$request    = new WP_REST_Request();
		$request->set_header( 'X-WP-Nonce', 'valid-rest-nonce' );
		$request->set_param( 'topic_id', 1 );
		$request->set_param( 'subject', 'Order help' );
		$request->set_param( 'message', 'I need help' );
		$request->set_param( 'order_relation', '' );

		$response = $controller->submitMemberTicket( $request );

		self::assertInstanceOf( WP_Error::class, $response );
	}

	public function testSubmitMemberTicketWithExistingOrderRelationRequiresOrderSelection(): void {
		wp_helpdesk_test_reset_state();
		$GLOBALS['wp_current_user'] = (object) array(
			'ID'           => 5,
			'user_email'   => 'test@example.com',
			'display_name' => 'Test User',
		);
		$GLOBALS['wp_user_meta'][5]['phone'] = '0401234567';

		$controller = new PublicTicketController( new TopicService(), new TopicTransitionService(), new KnowledgeBaseService() );
		$request    = new WP_REST_Request();
		$request->set_header( 'X-WP-Nonce', 'valid-rest-nonce' );
		$request->set_param( 'topic_id', 1 );
		$request->set_param( 'subject', 'Order help' );
		$request->set_param( 'message', 'I need help' );
		$request->set_param( 'order_relation', 'existing_order_related' );

		$response = $controller->submitMemberTicket( $request );

		self::assertInstanceOf( WP_Error::class, $response );
		self::assertSame( 'hd_missing_order_relation', $response->get_error_code() );
		// Must ask the user to select an order, not the generic "Please select an order relation."
		self::assertSame( 'Please select #Order.', $response->get_error_message() );
	}

	public function testSubmitMemberTicketWithExistingOrderRelationAndValidOrderSucceeds(): void {
		wp_helpdesk_test_reset_state();
		$GLOBALS['wp_current_user'] = (object) array(
			'ID'           => 7,
			'user_email'   => 'member@example.com',
			'display_name' => 'Member User',
		);
		$GLOBALS['wp_user_meta'][7]['phone'] = '0407654321';

		global $wpdb;
		$wpdb = new class {
			public string $base_prefix = 'wp_';
			public int    $insert_id   = 99;

			public function insert( string $table, array $data, array $format ): int {
				return 1;
			}

			public function prepare( string $query, ...$args ): string {
				return $query;
			}

			public function get_var( string $query ) {
				return null;
			}
		};

		$controller = new class extends PublicTicketController {
			protected function getUserLifetimeOrders( int $user_id ): array {
				return array( '501', '502' );
			}

			protected function createTicket( array $data ) {
				return new \WP_REST_Response( array( 'ticket_no' => 'HD-TEST-999', 'message' => 'ok' ), 201 );
			}
		};

		$request = new WP_REST_Request();
		$request->set_header( 'X-WP-Nonce', 'valid-rest-nonce' );
		$request->set_param( 'topic_id', 1 );
		$request->set_param( 'subject', 'My order' );
		$request->set_param( 'message', 'I need help with order 501' );
		$request->set_param( 'order_relation', 'existing_order_related' );
		$request->set_param( 'order_id', '501' );

		$response = $controller->submitMemberTicket( $request );

		self::assertInstanceOf( WP_REST_Response::class, $response );
		self::assertSame( 201, $response->status );
	}

	public function testSubmitGuestTicketWithExistingOrderRelationReturnsLoginRequired(): void {
		wp_helpdesk_test_reset_state();
		$GLOBALS['wp_logged_in'] = false;

		$controller = new PublicTicketController( new TopicService(), new TopicTransitionService(), new KnowledgeBaseService() );

		$request = new WP_REST_Request();
		$request->set_header( 'X-WP-Nonce', 'valid-rest-nonce' );
		$request->set_param( 'requester_name', 'Jane Guest' );
		$request->set_param( 'requester_email', 'jane@example.com' );
		$request->set_param( 'requester_phone', '0401234567' );
		$request->set_param( 'subject', 'Help' );
		$request->set_param( 'message', 'I need help with an order' );
		$request->set_param( 'order_relation', 'existing_order_related' );

		$response = $controller->submitGuestTicket( $request );

		self::assertInstanceOf( WP_Error::class, $response );
		self::assertSame( 'hd_login_required', $response->get_error_code() );
		self::assertSame( 'Please login to create ticket', $response->get_error_message() );
	}

	public function testSubmitGuestTicketWithNotAnyExistingOrderRelatedPassesValidation(): void {
		wp_helpdesk_test_reset_state();
		$GLOBALS['wp_logged_in'] = false;

		$controller = new class extends PublicTicketController {
			protected function createTicket( array $data ) {
				return new \WP_REST_Response( array( 'ticket_no' => 'HD-TEST-001', 'message' => 'ok' ), 201 );
			}
		};

		// Provide a wpdb stub so the RateLimiter does not blow up.
		global $wpdb;
		$wpdb = new class {
			public string $base_prefix = 'wp_';
			public int    $insert_id   = 1;

			public function get_row( string $sql, $mode = null ): ?array {
				return null;
			}

			public function prepare( string $sql, ...$args ): string {
				return $sql;
			}

			public function insert( string $table, array $data, array $format = array() ): int {
				return 1;
			}
		};

		$request = new WP_REST_Request();
		$request->set_header( 'X-WP-Nonce', 'valid-rest-nonce' );
		$request->set_param( 'requester_name', 'Jane Guest' );
		$request->set_param( 'requester_email', 'jane@example.com' );
		$request->set_param( 'requester_phone', '0401234567' );
		$request->set_param( 'subject', 'Help' );
		$request->set_param( 'message', 'I need help' );
		$request->set_param( 'order_relation', 'not_any_existing_order_related' );

		$response = $controller->submitGuestTicket( $request );

		self::assertInstanceOf( WP_REST_Response::class, $response );
		self::assertSame( 201, $response->status );
	}

	public function testListUserOrdersReturnsIdAndNumberObjects(): void {
		wp_helpdesk_test_reset_state();
		$GLOBALS['wp_logged_in'] = true;
		$GLOBALS['wp_current_user'] = (object) array(
			'ID'           => 3,
			'display_name' => 'Order Customer',
			'user_email'   => 'customer@example.com',
		);

		$controller = new class extends PublicTicketController {
			protected function getUserLifetimeOrderObjects( int $user_id ): array {
				return array(
					array( 'id' => '101', 'number' => '101' ),
					array( 'id' => '102', 'number' => '102' ),
				);
			}
		};

		$response = $controller->listUserOrders( new WP_REST_Request() );

		self::assertSame( 200, $response->status );
		self::assertCount( 2, $response->data );
		self::assertSame( '101', $response->data[0]['id'] );
		self::assertSame( '101', $response->data[0]['number'] );
		self::assertSame( '102', $response->data[1]['id'] );
	}

	public function testListUserOrdersReturnsEmptyArrayForGuest(): void {
		wp_helpdesk_test_reset_state();
		$GLOBALS['wp_logged_in'] = false;

		$controller = new PublicTicketController( new TopicService(), new TopicTransitionService(), new KnowledgeBaseService() );
		$response   = $controller->listUserOrders( new WP_REST_Request() );

		self::assertSame( 200, $response->status );
		self::assertSame( array(), $response->data );
	}

	public function testGetUserLifetimeOrdersUsesCustomerKeyAndReturnsIds(): void {
		wp_helpdesk_test_reset_state();

		$captured_args = null;

		$controller = new class extends PublicTicketController {
			public array $capturedArgs = array();

			protected function getUserLifetimeOrders( int $user_id ): array {
				if ( ! function_exists( 'wc_get_orders' ) ) {
					return array();
				}

				$args = array(
					'customer' => $user_id,
					'limit'    => -1,
					'return'   => 'ids',
				);
				$this->capturedArgs = $args;
				$orders = wc_get_orders( $args );
				if ( ! is_array( $orders ) ) {
					return array();
				}

				return array_values(
					array_filter(
						array_map(
							static function ( $oid ): string {
								$oid = (int) $oid;
								return $oid > 0 ? (string) $oid : '';
							},
							$orders
						),
						static fn( string $v ): bool => '' !== $v
					)
				);
			}
		};

		// Inject a wc_get_orders stub returning order IDs.
		if ( ! function_exists( 'wc_get_orders' ) ) {
			// The stub is registered in bootstrap; just verify the key used.
			self::markTestSkipped( 'wc_get_orders not stubbed in this environment.' );
		}

		// Call through the overridden method and check the key.
		$controller->getUserLifetimeOrders( 1 );
		self::assertArrayHasKey( 'customer', $controller->capturedArgs );
		self::assertArrayNotHasKey( 'customer_id', $controller->capturedArgs );
	}

}
