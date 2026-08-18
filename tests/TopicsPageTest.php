<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use WPHelpdesk\Domain\Topic\TopicService;
use WPHelpdesk\Domain\Topic\TopicTransitionService;
use WPHelpdesk\Interfaces\Admin\Pages\TopicsPage;

require_once __DIR__ . '/bootstrap.php';

final class TopicsPageTest extends TestCase {
	protected function setUp(): void {
		wp_helpdesk_test_reset_state();
	}

	public function testRenderFormShowsFlowBehaviorAndFollowUpSelector(): void {
		$topic_service = new class extends TopicService {
			public function listTopics( array $args = [] ): array {
				return array(
					array( 'id' => 1, 'name' => 'Billing', 'title' => 'Billing', 'is_active' => 1 ),
					array( 'id' => 2, 'name' => 'Shipping', 'title' => 'Shipping', 'is_active' => 1 ),
				);
			}
		};

		$transition_service = new class extends TopicTransitionService {
			public function getSelectableNextTopicIds( int $topic_id ): array {
				return array( 2 );
			}
		};

		$page = new TopicsPageTestDouble( $topic_service, $transition_service );

		ob_start();
		$page->renderForm( 'new' );
		$output = (string) ob_get_clean();

		self::assertStringContainsString( 'Flow behavior', $output );
		self::assertStringContainsString( 'Final step', $output );
		self::assertStringContainsString( 'Follow-up topics', $output );
		self::assertStringContainsString( 'multiple', $output );
	}

	public function testHandlePostRejectsBranchWithoutNextTopic(): void {
		$page = new TopicsPageTestDouble(
			new class extends TopicService {},
			new class extends TopicTransitionService {}
		);

		$_GET['page'] = 'wp-helpdesk-topics';
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST = array(
			'hd_topic_nonce'  => 'valid-topic-nonce',
			'hd_topic_action' => 'create',
			'name'            => 'Billing',
			'node_type'       => 'branch',
		);

		$page->handlePost();

		self::assertStringContainsString( 'msg=branch-missing-transition', (string) $page->redirect_target );
	}

	public function testHandlePostFinalTopicClearsFollowUpsBeforeSync(): void {
		$topic_service = new class extends TopicService {
			public array $created_payload = array();

			public function createTopic( array $data ): int {
				$this->created_payload = $data;
				return 14;
			}
		};

		$transition_service = new class extends TopicTransitionService {
			public array $synced = array();

			public function syncAdminNextTopics( int $from_topic_id, array $next_topic_ids ): bool {
				$this->synced = $next_topic_ids;
				return true;
			}
		};

		$page = new TopicsPageTestDouble( $topic_service, $transition_service );

		$_GET['page'] = 'wp-helpdesk-topics';
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST = array(
			'hd_topic_nonce'   => 'valid-topic-nonce',
			'hd_topic_action'  => 'create',
			'name'             => 'Resolved',
			'node_type'        => 'final',
			'next_topic_ids'   => array( 99 ),
		);

		$page->handlePost();

		self::assertSame( 1, $topic_service->created_payload['is_final'] );
		self::assertSame( array(), $transition_service->synced );
		self::assertStringContainsString( 'msg=created', (string) $page->redirect_target );
	}
}

final class TopicsPageTestDouble extends TopicsPage {
	public ?string $redirect_target = null;

	public function renderForm( string $action ): void {
		$this->renderFormView( $action );
	}

	protected function redirectToList( string $message ): void {
		$this->redirect_target = $this->getListUrl( array( 'msg' => $message ) );
	}

	protected function redirectToForm( string $action, string $message, int $topic_id = 0 ): void {
		$args = array(
			'action' => $action,
			'msg'    => $message,
		);
		if ( $topic_id > 0 ) {
			$args['id'] = $topic_id;
		}
		$this->redirect_target = $this->getListUrl( $args );
	}
}
