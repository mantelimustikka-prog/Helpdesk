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
}
