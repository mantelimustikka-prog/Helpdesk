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

	public function testRenderFormShowsTypeAndParentSelectors(): void {
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

		self::assertStringContainsString( 'Type', $output );
		self::assertStringContainsString( 'Root Topic', $output );
		self::assertStringContainsString( 'Follow-up Topic', $output );
		self::assertStringContainsString( 'Parent Topic', $output );
		self::assertStringContainsString( 'Required for Follow-up topics', $output );
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
			'topic_type'      => 'FOLLOWUP',
		);

		$page->handlePost();

		self::assertStringContainsString( 'msg=followup-missing-parent', (string) $page->redirect_target );
	}

	public function testHandlePostAllowsRootTopicWithoutParent(): void {
		$topic_service = new class extends TopicService {
			public array $created_payload = array();

			public function createTopic( array $data ): int {
				$this->created_payload = $data;
				return 31;
			}
		};

		$page = new TopicsPageTestDouble( $topic_service, new class extends TopicTransitionService {} );

		$_GET['page'] = 'wp-helpdesk-topics';
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST = array(
			'hd_topic_nonce'  => 'valid-topic-nonce',
			'hd_topic_action' => 'create',
			'name'            => 'General',
			'topic_type'      => 'ROOT',
			'parent_id'       => '',
		);

		$page->handlePost();

		self::assertSame( 'root', $topic_service->created_payload['type'] );
		self::assertNull( $topic_service->created_payload['parent_id'] );
		self::assertStringContainsString( 'msg=created', (string) $page->redirect_target );
	}

	public function testHandlePostRootTopicIgnoresSubmittedParent(): void {
		$topic_service = new class extends TopicService {
			public array $created_payload = array();

			public function createTopic( array $data ): int {
				$this->created_payload = $data;
				return 32;
			}
		};

		$page = new TopicsPageTestDouble( $topic_service, new class extends TopicTransitionService {} );

		$_GET['page'] = 'wp-helpdesk-topics';
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST = array(
			'hd_topic_nonce'  => 'valid-topic-nonce',
			'hd_topic_action' => 'create',
			'name'            => 'General',
			'topic_type'      => 'ROOT',
			'parent_id'       => '9',
		);

		$page->handlePost();

		self::assertSame( 'root', $topic_service->created_payload['type'] );
		self::assertNull( $topic_service->created_payload['parent_id'] );
		self::assertStringContainsString( 'msg=created', (string) $page->redirect_target );
	}

	public function testHandlePostAllowsFollowUpTopicWithParent(): void {
		$topic_service = new class extends TopicService {
			public array $created_payload = array();

			public function validateTypeConstraints( string $type, ?int $parent_id, int $topic_id = 0 ): ?string {
				return null;
			}

			public function createTopic( array $data ): int {
				$this->created_payload = $data;
				return 33;
			}
		};

		$page = new TopicsPageTestDouble( $topic_service, new class extends TopicTransitionService {} );

		$_GET['page'] = 'wp-helpdesk-topics';
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST = array(
			'hd_topic_nonce'  => 'valid-topic-nonce',
			'hd_topic_action' => 'create',
			'name'            => 'Invoices',
			'topic_type'      => 'FOLLOWUP',
			'parent_id'       => '4',
		);

		$page->handlePost();

		self::assertSame( 'followup', $topic_service->created_payload['type'] );
		self::assertSame( 4, $topic_service->created_payload['parent_id'] );
		self::assertStringContainsString( 'msg=created', (string) $page->redirect_target );
	}

	public function testHandlePostRejectsInvalidTopicType(): void {
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
			'topic_type'      => 'unknown',
		);

		$page->handlePost();

		self::assertStringContainsString( 'msg=invalid-topic-type', (string) $page->redirect_target );
	}

	public function testHandlePostRootTopicSubmittedParentIsClearedBeforeCreate(): void {
		$topic_service = new class extends TopicService {
			public array $created_payload = array();

			public function createTopic( array $data ): int {
				$this->created_payload = $data;
				return 14;
			}
		};

		$page = new TopicsPageTestDouble( $topic_service, new class extends TopicTransitionService {} );

		$_GET['page'] = 'wp-helpdesk-topics';
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST = array(
			'hd_topic_nonce'  => 'valid-topic-nonce',
			'hd_topic_action' => 'create',
			'name'            => 'Resolved',
			'topic_type'      => 'ROOT',
			'parent_id'       => '99',
		);

		$page->handlePost();

		self::assertSame( 'root', $topic_service->created_payload['type'] );
		self::assertNull( $topic_service->created_payload['parent_id'] );
		self::assertStringContainsString( 'msg=created', (string) $page->redirect_target );
	}

	public function testHandlePostIncludesUnderlyingErrorDetailsWhenSaveFails(): void {
		$topic_service = new class extends TopicService {
			public function createTopic( array $data ): int {
				return 0;
			}

			public function getLastSaveError(): string {
				return "Unknown column 'type' in 'INSERT INTO'";
			}
		};

		$page = new TopicsPageTestDouble( $topic_service, new class extends TopicTransitionService {} );

		$_GET['page'] = 'wp-helpdesk-topics';
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST = array(
			'hd_topic_nonce'  => 'valid-topic-nonce',
			'hd_topic_action' => 'create',
			'name'            => 'General',
			'topic_type'      => 'ROOT',
		);

		$page->handlePost();

		$query = parse_url( (string) $page->redirect_target, PHP_URL_QUERY );
		parse_str( (string) $query, $args );
		self::assertSame( 'error', $args['msg'] ?? '' );
		self::assertSame( "Unknown column 'type' in 'INSERT INTO'", $args['error_detail'] ?? '' );
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

		self::assertStringContainsString( 'value="followup" checked="checked"', $output );
		self::assertStringContainsString( 'option value="4" selected="selected"', $output );
	}

	public function testHandlePostUpdatingTopicToRootClearsParent(): void {
		$topic_service = new class extends TopicService {
			public array $updated_payload = array();

			public function updateTopic( int $id, array $data ): bool {
				$this->updated_payload = $data;
				return true;
			}
		};

		$page = new TopicsPageTestDouble( $topic_service, new class extends TopicTransitionService {} );

		$_GET['page'] = 'wp-helpdesk-topics';
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST = array(
			'hd_topic_nonce'  => 'valid-topic-nonce',
			'hd_topic_action' => 'update',
			'hd_topic_id'     => 11,
			'name'            => 'Invoices',
			'topic_type'      => 'ROOT',
			'parent_id'       => '4',
		);

		$page->handlePost();

		self::assertSame( 'root', $topic_service->updated_payload['type'] );
		self::assertNull( $topic_service->updated_payload['parent_id'] );
		self::assertStringContainsString( 'msg=updated', (string) $page->redirect_target );
	}

	public function testRenderNoticeShowsUnderlyingSaveErrorDetails(): void {
		$page = new TopicsPageTestDouble(
			new class extends TopicService {},
			new class extends TopicTransitionService {}
		);

		$_GET['msg'] = 'error';
		$_GET['error_detail'] = "Unknown column 'type' in 'INSERT INTO'";

		ob_start();
		$page->renderNoticeForTest();
		$output = (string) ob_get_clean();

		self::assertStringContainsString( 'Unable to save the topic.', $output );
		self::assertStringContainsString( "Unknown column &#039;type&#039; in &#039;INSERT INTO&#039;", $output );
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

	public function renderNoticeForTest(): void {
		$this->renderNotice();
	}

	protected function redirectToList( string $message, string $error_detail = '' ): void {
		$args = array( 'msg' => $message );
		if ( '' !== $error_detail ) {
			$args['error_detail'] = $error_detail;
		}
		$this->redirect_target = $this->getListUrl( $args );
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
