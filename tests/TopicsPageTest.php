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
		self::assertStringContainsString( 'Parent topics', $output );
		self::assertStringContainsString( 'multiple', $output );
	}

	public function testHandlePostRejectsFollowUpWithoutParentTopic(): void {
		$page = new TopicsPageTestDouble(
			new class extends TopicService {},
			new class extends TopicTransitionService {}
		);

		$_GET['page'] = 'wp-helpdesk-topics';
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST = array(
			'hd_topic_nonce'  => 'valid-topic-nonce',
			'hd_topic_action' => 'create',
			'name'            => 'Billing follow-up',
			'node_type'       => 'final',
			'hierarchy_type'  => 'follow_up',
		);

		$page->handlePost();

		self::assertStringContainsString( 'msg=follow-up-missing-parent', (string) $page->redirect_target );
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

			public function syncAdminParentTopics( int $to_topic_id, array $parent_topic_ids ): bool {
				return true;
			}

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

	public function testRenderListViewDefaultsToTreeModeAndShowsHierarchyActions(): void {
		$topic_service = new class extends TopicService {
			public function countTopics( array $args = array() ): int {
				return 3;
			}

			public function listTopics( array $args = array() ): array {
				return array(
					array( 'id' => 1, 'name' => 'Billing', 'title' => 'Billing', 'slug' => 'billing', 'is_active' => 1, 'sort_order' => 0, 'updated_at' => '2026-08-18 21:14:13' ),
					array( 'id' => 2, 'name' => 'Invoices', 'title' => 'Invoices', 'slug' => 'invoices', 'is_active' => 1, 'sort_order' => 1, 'updated_at' => '2026-08-18 21:14:13' ),
					array( 'id' => 3, 'name' => 'Refunds', 'title' => 'Refunds', 'slug' => 'refunds', 'is_active' => 0, 'sort_order' => 2, 'updated_at' => '2026-08-18 21:14:13' ),
				);
			}

			public function buildTopicTree( array $topics, array $parent_ids_by_topic = array(), string $search = '' ): array {
				return array(
					array(
						'id' => 1,
						'name' => 'Billing',
						'slug' => 'billing',
						'is_active' => 1,
						'sort_order' => 0,
						'updated_at' => '2026-08-18 21:14:13',
						'depth' => 1,
						'child_count' => 2,
						'matches_search' => true,
						'children' => array(
							array(
								'id' => 2,
								'name' => 'Invoices',
								'slug' => 'invoices',
								'is_active' => 1,
								'sort_order' => 1,
								'updated_at' => '2026-08-18 21:14:13',
								'depth' => 2,
								'child_count' => 0,
								'matches_search' => true,
								'children' => array(),
							),
							array(
								'id' => 3,
								'name' => 'Refunds',
								'slug' => 'refunds',
								'is_active' => 0,
								'sort_order' => 2,
								'updated_at' => '2026-08-18 21:14:13',
								'depth' => 2,
								'child_count' => 0,
								'matches_search' => false,
								'children' => array(),
							),
						),
					),
				);
			}
		};

		$transition_service = new class extends TopicTransitionService {
			public function getAdminParentTopicIdsMap(): array {
				return array(
					2 => array( 1 ),
					3 => array( 1 ),
				);
			}
		};

		$page = new TopicsPageTestDouble( $topic_service, $transition_service );

		ob_start();
		$page->renderList();
		$output = (string) ob_get_clean();

		self::assertStringContainsString( 'Tree view', $output );
		self::assertStringContainsString( 'Expand all', $output );
		self::assertStringContainsString( 'data-hd-tree-toggle', $output );
		self::assertStringContainsString( 'Add Child', $output );
		self::assertStringContainsString( 'Delete', $output );
		self::assertStringContainsString( 'Topics hierarchy', $output );
		self::assertStringContainsString( 'Depth 2', $output );
		self::assertStringContainsString( 'Inactive', $output );
	}

	public function testRenderListViewUsesStoredFlatViewPreference(): void {
		$GLOBALS['wp_user_meta'][7]['hd_topics_view_mode'] = 'flat';

		$topic_service = new class extends TopicService {
			public function countTopics( array $args = array() ): int {
				return 1;
			}

			public function listTopics( array $args = array() ): array {
				return array(
					array( 'id' => 1, 'name' => 'Billing', 'slug' => 'billing', 'is_active' => 1, 'sort_order' => 0, 'updated_at' => '2026-08-18 21:14:13' ),
				);
			}
		};

		$page = new TopicsPageTestDouble( $topic_service, new class extends TopicTransitionService {} );

		ob_start();
		$page->renderList();
		$output = (string) ob_get_clean();

		self::assertStringContainsString( '<table class="widefat striped">', $output );
		self::assertStringNotContainsString( 'Topics hierarchy', $output );
	}

	public function testRenderFormPreselectsParentTopicWhenAddingChild(): void {
		$topic_service = new class extends TopicService {
			public function listTopics( array $args = array() ): array {
				return array(
					array( 'id' => 4, 'name' => 'Billing', 'title' => 'Billing', 'is_active' => 1 ),
					array( 'id' => 5, 'name' => 'Invoices', 'title' => 'Invoices', 'is_active' => 1 ),
				);
			}
		};

		$page = new TopicsPageTestDouble( $topic_service, new class extends TopicTransitionService {} );
		$_GET['parent_topic_id'] = 4;

		ob_start();
		$page->renderForm( 'new' );
		$output = (string) ob_get_clean();

		self::assertStringContainsString( 'value="follow_up" checked="checked"', $output );
		self::assertStringContainsString( 'option value="4" selected="selected"', $output );
	}
}

final class TopicsPageTestDouble extends TopicsPage {
	public ?string $redirect_target = null;

	public function renderList(): void {
		$this->renderListView();
	}

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
