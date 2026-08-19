<?php
/**
 * @package WP Helpdesk
 */

namespace WPHelpdesk\Interfaces\Admin\Pages;

use WPHelpdesk\Domain\Topic\TopicService;
use WPHelpdesk\Domain\Topic\TopicTransitionService;

class TopicsPage {
	protected TopicService $topic_service;
	protected TopicTransitionService $topic_transition_service;

	public function __construct( ?TopicService $topic_service = null, ?TopicTransitionService $topic_transition_service = null ) {
		$this->topic_service            = $topic_service ?: new TopicService();
		$this->topic_transition_service = $topic_transition_service ?: new TopicTransitionService();
	}

	/**
	 * Handle topic form submissions.
	 *
	 * @return void
	 */
	public function handlePost(): void {
		if ( 'wp-helpdesk-topics' !== ( $_GET['page'] ?? '' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		if ( 'POST' !== strtoupper( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
			return;
		}

		if ( ! current_user_can( 'hd_manage_topics' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage topics.', 'wp-helpdesk' ) );
		}

		$nonce = isset( $_POST['hd_topic_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['hd_topic_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'hd_topic_action' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'wp-helpdesk' ) );
		}

		$action = isset( $_POST['hd_topic_action'] ) ? sanitize_key( wp_unslash( $_POST['hd_topic_action'] ) ) : '';

		switch ( $action ) {
			case 'create':
				$this->handleCreate();
				break;
			case 'update':
				$this->handleUpdate();
				break;
			case 'delete':
				$this->handleDelete();
				break;
			case 'activate':
				$this->handleSetActive( true );
				break;
			case 'deactivate':
				$this->handleSetActive( false );
				break;
			case 'bulk':
				$this->handleBulk();
				break;
		}
	}

	/**
	 * Render the topics page.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( 'hd_manage_topics' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'wp-helpdesk' ) );
		}

		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';
		?>
		<div class="wrap hd-admin-wrap">
			<?php $this->renderNotice(); ?>
			<?php
			if ( 'new' === $action || 'edit' === $action ) {
				$this->renderFormView( $action );
			} else {
				$this->renderListView();
			}
			?>
		</div>
		<?php
	}

	/**
	 * Render the topic list screen.
	 *
	 * @return void
	 */
	protected function renderListView(): void {
		$search     = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$view_mode  = $this->getCurrentViewMode();
		$is_tree    = 'tree' === $view_mode;
		$page       = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;
		$per_page   = 20;
		$list_url   = $this->getListUrl();
		$topics     = array();
		$total      = 0;
		$pages      = 1;
		$tree       = array();
		$tree_total = 0;

		if ( $is_tree ) {
			$total  = $this->topic_service->countTopics();
			$topics = $this->topic_service->listTopics(
				array(
					'page'     => 1,
					'per_page' => max( 1, $total ),
				)
			);
			$tree       = $this->buildTopicTree( $topics, $search );
			$tree_total = $this->countTreeNodes( $tree );
		} else {
			$args   = array(
				'search'   => $search,
				'page'     => $page,
				'per_page' => $per_page,
			);
			$topics  = $this->topic_service->listTopics( $args );
			$total   = $this->topic_service->countTopics( $args );
			$pages   = max( 1, (int) ceil( $total / $per_page ) );
		}
		?>
		<h1 class="wp-heading-inline"><?php esc_html_e( 'Topics', 'wp-helpdesk' ); ?></h1>
		<a href="<?php echo esc_url( $this->getListUrl( array( 'action' => 'new' ) ) ); ?>" class="page-title-action"><?php esc_html_e( 'Add New', 'wp-helpdesk' ); ?></a>
		<hr class="wp-header-end">

		<form method="get" style="margin:16px 0;">
			<input type="hidden" name="page" value="wp-helpdesk-topics">
			<input type="hidden" name="view" value="<?php echo esc_attr( $view_mode ); ?>">
			<p class="search-box">
				<label class="screen-reader-text" for="topic-search-input"><?php esc_html_e( 'Search topics', 'wp-helpdesk' ); ?></label>
				<input type="search" id="topic-search-input" name="s" value="<?php echo esc_attr( $search ); ?>">
				<?php submit_button( __( 'Search Topics', 'wp-helpdesk' ), '', '', false, array( 'id' => 'search-submit' ) ); ?>
			</p>
		</form>

		<div class="hd-topic-view-controls" style="margin:0 0 16px;">
			<a class="button <?php echo esc_attr( $is_tree ? 'button-primary' : '' ); ?>" href="<?php echo esc_url( $this->getListUrl( array( 'view' => 'tree', 's' => $search ) ) ); ?>"><?php esc_html_e( 'Tree view', 'wp-helpdesk' ); ?></a>
			<a class="button <?php echo esc_attr( ! $is_tree ? 'button-primary' : '' ); ?>" href="<?php echo esc_url( $this->getListUrl( array( 'view' => 'flat', 's' => $search ) ) ); ?>"><?php esc_html_e( 'Flat view', 'wp-helpdesk' ); ?></a>
			<?php if ( $is_tree ) : ?>
				<button type="button" class="button" data-hd-tree-expand-all="1"><?php esc_html_e( 'Expand all', 'wp-helpdesk' ); ?></button>
				<button type="button" class="button" data-hd-tree-collapse-all="1"><?php esc_html_e( 'Collapse all', 'wp-helpdesk' ); ?></button>
			<?php endif; ?>
		</div>

		<form method="post">
			<?php wp_nonce_field( 'hd_topic_action', 'hd_topic_nonce' ); ?>
			<input type="hidden" name="hd_topic_action" value="bulk">
			<div class="tablenav top">
				<div class="alignleft actions">
					<label class="screen-reader-text" for="bulk-action-selector-top"><?php esc_html_e( 'Select bulk action', 'wp-helpdesk' ); ?></label>
					<select name="bulk_action" id="bulk-action-selector-top">
						<option value=""><?php esc_html_e( 'Bulk actions', 'wp-helpdesk' ); ?></option>
						<option value="activate"><?php esc_html_e( 'Activate', 'wp-helpdesk' ); ?></option>
						<option value="deactivate"><?php esc_html_e( 'Deactivate', 'wp-helpdesk' ); ?></option>
						<option value="delete"><?php esc_html_e( 'Delete', 'wp-helpdesk' ); ?></option>
					</select>
					<?php submit_button( __( 'Apply', 'wp-helpdesk' ), 'action', '', false ); ?>
				</div>
				<div class="tablenav-pages">
					<span class="displaying-num">
						<?php echo esc_html( sprintf( _n( '%d item', '%d items', $is_tree ? $tree_total : $total, 'wp-helpdesk' ), $is_tree ? $tree_total : $total ) ); ?>
					</span>
				</div>
			</div>

			<?php if ( $is_tree ) : ?>
				<?php $this->renderTreeTable( $tree ); ?>
			<?php else : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<td class="manage-column column-cb check-column"><input type="checkbox" aria-label="<?php esc_attr_e( 'Select all topics', 'wp-helpdesk' ); ?>"></td>
							<th scope="col"><?php esc_html_e( 'Name', 'wp-helpdesk' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Type', 'wp-helpdesk' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Active', 'wp-helpdesk' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Order', 'wp-helpdesk' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Updated', 'wp-helpdesk' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Actions', 'wp-helpdesk' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $topics ) ) : ?>
							<tr>
								<td colspan="7"><?php esc_html_e( 'No topics found.', 'wp-helpdesk' ); ?></td>
							</tr>
						<?php else : ?>
							<?php foreach ( $topics as $topic ) : ?>
								<tr>
									<th scope="row" class="check-column">
										<input type="checkbox" name="topic_ids[]" value="<?php echo esc_attr( (string) $topic['id'] ); ?>">
									</th>
									<td>
										<strong>
											<a href="<?php echo esc_url( $this->getListUrl( array( 'action' => 'edit', 'id' => (int) $topic['id'] ) ) ); ?>">
												<?php echo esc_html( (string) $topic['name'] ); ?>
											</a>
										</strong>
									</td>
									<td><?php echo esc_html( 'followup' === (string) ( $topic['type'] ?? '' ) ? __( 'Follow-up', 'wp-helpdesk' ) : __( 'Root', 'wp-helpdesk' ) ); ?></td>
									<td><?php echo esc_html( ! empty( $topic['is_active'] ) ? __( 'Yes', 'wp-helpdesk' ) : __( 'No', 'wp-helpdesk' ) ); ?></td>
									<td><?php echo esc_html( (string) (int) $topic['sort_order'] ); ?></td>
									<td><?php echo esc_html( (string) $topic['updated_at'] ); ?></td>
									<td><?php $this->renderTopicRowActions( $topic ); ?></td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</form>

		<?php if ( ! $is_tree && $pages > 1 ) : ?>
			<div class="tablenav bottom">
				<div class="tablenav-pages">
					<?php
					echo wp_kses_post(
						paginate_links(
							array(
								'base'      => esc_url_raw( add_query_arg( 'paged', '%#%', $list_url ) ),
								'add_args'  => array(
									'view' => $view_mode,
									's'    => $search,
								),
								'format'    => '',
								'current'   => $page,
								'total'     => $pages,
								'prev_text' => __( '&laquo;', 'wp-helpdesk' ),
								'next_text' => __( '&raquo;', 'wp-helpdesk' ),
							)
						)
					);
					?>
				</div>
			</div>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render create/edit form view.
	 *
	 * @param string $action Current action.
	 * @return void
	 */
	protected function renderFormView( string $action ): void {
		$topic = array(
			'id'          => 0,
			'name'        => '',
			'slug'        => '',
			'description' => '',
			'is_final'    => 0,
			'is_active'   => 1,
			'sort_order'  => 0,
		);

		if ( 'edit' === $action ) {
			$topic_id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$loaded   = $topic_id > 0 ? $this->topic_service->getTopic( $topic_id ) : null;
			if ( ! $loaded ) {
				?>
				<h1><?php esc_html_e( 'Edit Topic', 'wp-helpdesk' ); ?></h1>
				<div class="notice notice-error"><p><?php esc_html_e( 'Topic not found.', 'wp-helpdesk' ); ?></p></div>
				<p><a class="button" href="<?php echo esc_url( $this->getListUrl() ); ?>"><?php esc_html_e( 'Back to Topics', 'wp-helpdesk' ); ?></a></p>
				<?php
				return;
			}

			$topic = $loaded;
		}

		$node_type           = ! empty( $topic['is_final'] ) ? 'final' : 'branch';
		$topic_type          = isset( $topic['type'] ) && 'followup' === (string) $topic['type'] ? 'followup' : 'root';
		// Support legacy hierarchy_type from normalized row.
		if ( isset( $topic['hierarchy_type'] ) && 'follow_up' === (string) $topic['hierarchy_type'] ) {
			$topic_type = 'followup';
		}
		$current_parent_id = isset( $topic['parent_id'] ) && (int) $topic['parent_id'] > 0 ? (int) $topic['parent_id'] : 0;
		if ( empty( $topic['id'] ) && isset( $_GET['parent_topic_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$parent_from_url = (int) $_GET['parent_topic_id'];
			if ( $parent_from_url > 0 ) {
				$current_parent_id = $parent_from_url;
				$topic_type        = 'followup';
			}
		}
		$candidate_topics = $this->topic_service->listTopics(
			array(
				'per_page' => 250,
			)
		);
		$available_parent_topics = array_filter(
			$candidate_topics,
			static fn( array $candidate ): bool => (int) ( $candidate['id'] ?? 0 ) !== (int) ( $topic['id'] ?? 0 )
		);
		?>
		<h1><?php echo esc_html( 'edit' === $action ? __( 'Edit Topic', 'wp-helpdesk' ) : __( 'Add Topic', 'wp-helpdesk' ) ); ?></h1>
		<p><a class="button" href="<?php echo esc_url( $this->getListUrl() ); ?>"><?php esc_html_e( 'Back to Topics', 'wp-helpdesk' ); ?></a></p>
		<form method="post">
			<?php wp_nonce_field( 'hd_topic_action', 'hd_topic_nonce' ); ?>
			<input type="hidden" name="hd_topic_action" value="<?php echo esc_attr( 'edit' === $action ? 'update' : 'create' ); ?>">
			<?php if ( 'edit' === $action ) : ?>
				<input type="hidden" name="hd_topic_id" value="<?php echo esc_attr( (string) $topic['id'] ); ?>">
			<?php endif; ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="hd-topic-name"><?php esc_html_e( 'Name', 'wp-helpdesk' ); ?></label></th>
					<td>
						<input type="text" id="hd-topic-name" name="name" class="regular-text" value="<?php echo esc_attr( (string) $topic['name'] ); ?>" required>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="hd-topic-description"><?php esc_html_e( 'Description', 'wp-helpdesk' ); ?></label></th>
					<td>
						<textarea id="hd-topic-description" name="description" rows="4" class="large-text"><?php echo esc_textarea( (string) $topic['description'] ); ?></textarea>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Type', 'wp-helpdesk' ); ?></th>
					<td>
						<label style="display:block;margin-bottom:8px;">
							<input type="radio" name="topic_type" value="root" <?php checked( $topic_type, 'root' ); ?> id="hd-type-root">
							<?php esc_html_e( 'Root Topic', 'wp-helpdesk' ); ?>
						</label>
						<label style="display:block;">
							<input type="radio" name="topic_type" value="followup" <?php checked( $topic_type, 'followup' ); ?> id="hd-type-followup">
							<?php esc_html_e( 'Follow-up Topic', 'wp-helpdesk' ); ?>
						</label>
						<p class="description"><?php esc_html_e( 'Root topics appear in the first step. Follow-up topics appear after a parent topic is selected.', 'wp-helpdesk' ); ?></p>
					</td>
				</tr>
				<tr id="hd-parent-topic-row" <?php echo esc_attr( 'root' === $topic_type ? 'style="display:none"' : '' ); ?>>
					<th scope="row"><label for="hd-topic-parent"><?php esc_html_e( 'Parent Topic', 'wp-helpdesk' ); ?></label></th>
					<td>
						<select id="hd-topic-parent" name="parent_id" class="regular-text">
							<option value=""><?php esc_html_e( '— Select parent —', 'wp-helpdesk' ); ?></option>
							<?php foreach ( $available_parent_topics as $candidate_topic ) : ?>
								<?php $candidate_id = (int) $candidate_topic['id']; ?>
								<option value="<?php echo esc_attr( (string) $candidate_id ); ?>" <?php selected( $current_parent_id, $candidate_id ); ?>>
									<?php echo esc_html( (string) $candidate_topic['name'] ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php esc_html_e( 'Required for Follow-up topics. Select the parent topic this follow-up belongs to.', 'wp-helpdesk' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Active', 'wp-helpdesk' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="is_active" value="1" <?php checked( ! empty( $topic['is_active'] ) ); ?>>
							<?php esc_html_e( 'Topic is active', 'wp-helpdesk' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="hd-topic-sort-order"><?php esc_html_e( 'Sort Order', 'wp-helpdesk' ); ?></label></th>
					<td>
						<input type="number" id="hd-topic-sort-order" name="sort_order" value="<?php echo esc_attr( (string) (int) $topic['sort_order'] ); ?>" class="small-text" step="1">
					</td>
				</tr>
			</table>
			<?php submit_button( 'edit' === $action ? __( 'Update Topic', 'wp-helpdesk' ) : __( 'Create Topic', 'wp-helpdesk' ) ); ?>
		</form>
		<script>
		(function () {
			var typeInputs = document.querySelectorAll( 'input[name="topic_type"]' );
			var parentRow  = document.getElementById( 'hd-parent-topic-row' );
			if ( ! parentRow ) {
				return;
			}
			typeInputs.forEach( function ( input ) {
				input.addEventListener( 'change', function () {
					parentRow.style.display = input.value === 'followup' ? '' : 'none';
				} );
			} );
		}() );
		</script>
		<?php
	}

	/**
	 * Render the hierarchical tree/table hybrid.
	 *
	 * @param array<int, array<string, mixed>> $tree Hierarchy tree.
	 * @return void
	 */
	protected function renderTreeTable( array $tree ): void {
		?>
		<style>
			.hd-topic-tree { border:1px solid #ccd0d4; background:#fff; }
			.hd-topic-tree__header,
			.hd-topic-tree__row { display:grid; grid-template-columns: 36px minmax(320px, 2fr) minmax(120px, .8fr) minmax(80px, .5fr) minmax(140px, .8fr) minmax(260px, 1.2fr); gap:12px; align-items:center; padding:10px 12px; }
			.hd-topic-tree__header { border-bottom:1px solid #ccd0d4; font-weight:600; background:#f6f7f7; }
			.hd-topic-tree__row { border-top:1px solid #f0f0f1; }
			.hd-topic-tree__group { margin-left:0; }
			.hd-topic-tree__item.is-context > .hd-topic-tree__row { background:#fcfcfc; }
			.hd-topic-tree__item.is-match > .hd-topic-tree__row { background:#f0f6fc; }
			.hd-topic-tree__node { display:flex; align-items:center; gap:8px; min-width:0; }
			.hd-topic-tree__toggle { width:24px; height:24px; padding:0; line-height:1; }
			.hd-topic-tree__toggle-placeholder { width:24px; flex:0 0 24px; }
			.hd-topic-tree__connector { white-space:pre; font-family:monospace; color:#8c8f94; }
			.hd-topic-tree__meta { display:flex; flex-wrap:wrap; gap:6px; margin-top:4px; color:#50575e; }
			.hd-topic-tree__badge { display:inline-block; padding:2px 8px; border-radius:999px; background:#f0f0f1; font-size:12px; }
			.hd-topic-tree__badge.is-inactive { background:#fbeaea; color:#8a2424; }
			.hd-topic-tree__actions form { display:inline-block; margin:0 0 0 4px; }
			.hd-topic-tree__empty { padding:16px 12px; }
		</style>
		<div class="hd-topic-tree" data-hd-topic-tree="1" role="tree" aria-label="<?php esc_attr_e( 'Topics hierarchy', 'wp-helpdesk' ); ?>">
			<div class="hd-topic-tree__header">
				<div></div>
				<div><?php esc_html_e( 'Name', 'wp-helpdesk' ); ?></div>
				<div><?php esc_html_e( 'Slug', 'wp-helpdesk' ); ?></div>
				<div><?php esc_html_e( 'Active', 'wp-helpdesk' ); ?></div>
				<div><?php esc_html_e( 'Updated', 'wp-helpdesk' ); ?></div>
				<div><?php esc_html_e( 'Actions', 'wp-helpdesk' ); ?></div>
			</div>
			<?php if ( empty( $tree ) ) : ?>
				<div class="hd-topic-tree__empty"><?php esc_html_e( 'No topics found.', 'wp-helpdesk' ); ?></div>
			<?php else : ?>
				<?php $this->renderTreeNodes( $tree ); ?>
			<?php endif; ?>
		</div>
		<script>
			(function () {
				const tree = document.querySelector('[data-hd-topic-tree]');
				if (!tree) {
					return;
				}

				const storageKey = 'hd_topics_tree_state';
				const readState = () => {
					try {
						return JSON.parse(window.localStorage.getItem(storageKey) || '{}');
					} catch (error) {
						return {};
					}
				};
				const writeState = (state) => window.localStorage.setItem(storageKey, JSON.stringify(state));
				const applyItemState = (item, expanded) => {
					const button = item.querySelector('[data-hd-tree-toggle]');
					const group = item.querySelector('[data-hd-tree-group]');
					if (!button || !group) {
						return;
					}

					button.setAttribute('aria-expanded', expanded ? 'true' : 'false');
					button.textContent = expanded ? '−' : '+';
					group.hidden = !expanded;
				};

				const state = readState();
				tree.querySelectorAll('[data-hd-tree-item]').forEach((item) => {
					const itemId = item.getAttribute('data-hd-tree-item');
					if (Object.prototype.hasOwnProperty.call(state, itemId)) {
						applyItemState(item, !!state[itemId]);
					}
				});

				tree.addEventListener('click', (event) => {
					const button = event.target.closest('[data-hd-tree-toggle]');
					if (!button) {
						return;
					}

					const item = button.closest('[data-hd-tree-item]');
					const itemId = item ? item.getAttribute('data-hd-tree-item') : '';
					if (!item || !itemId) {
						return;
					}

					const expanded = button.getAttribute('aria-expanded') !== 'true';
					applyItemState(item, expanded);
					state[itemId] = expanded;
					writeState(state);
				});

				const expandAllButton = document.querySelector('[data-hd-tree-expand-all]');
				if (expandAllButton) {
					expandAllButton.addEventListener('click', () => {
						tree.querySelectorAll('[data-hd-tree-item]').forEach((item) => {
							const itemId = item.getAttribute('data-hd-tree-item');
							applyItemState(item, true);
							if (itemId) {
								state[itemId] = true;
							}
						});
						writeState(state);
					});
				}

				const collapseAllButton = document.querySelector('[data-hd-tree-collapse-all]');
				if (collapseAllButton) {
					collapseAllButton.addEventListener('click', () => {
						tree.querySelectorAll('[data-hd-tree-item]').forEach((item) => {
							const itemId = item.getAttribute('data-hd-tree-item');
							applyItemState(item, false);
							if (itemId) {
								state[itemId] = false;
							}
						});
						writeState(state);
					});
				}
			}());
		</script>
		<?php
	}

	/**
	 * Render tree nodes recursively.
	 *
	 * @param array<int, array<string, mixed>> $nodes Nodes.
	 * @param array<int, bool>                 $ancestor_has_more Branch connector context.
	 * @return void
	 */
	protected function renderTreeNodes( array $nodes, array $ancestor_has_more = array() ): void {
		$total = count( $nodes );

		foreach ( $nodes as $index => $node ) {
			$has_children = ! empty( $node['children'] );
			$is_last      = $index === $total - 1;
			$item_classes = array( 'hd-topic-tree__item' );

			if ( ! empty( $node['matches_search'] ) ) {
				$item_classes[] = 'is-match';
			} elseif ( ! empty( $node['children'] ) ) {
				$item_classes[] = 'is-context';
			}
			?>
			<div class="<?php echo esc_attr( implode( ' ', $item_classes ) ); ?>" data-hd-tree-item="<?php echo esc_attr( (string) (int) $node['id'] ); ?>" role="treeitem" aria-level="<?php echo esc_attr( (string) (int) $node['depth'] ); ?>" <?php echo $has_children ? 'aria-expanded="true"' : ''; ?>>
				<div class="hd-topic-tree__row">
					<div>
						<input type="checkbox" name="topic_ids[]" value="<?php echo esc_attr( (string) $node['id'] ); ?>">
					</div>
					<div class="hd-topic-tree__node">
						<?php if ( $has_children ) : ?>
							<button type="button" class="button-link hd-topic-tree__toggle" data-hd-tree-toggle="1" aria-expanded="true" aria-controls="<?php echo esc_attr( 'hd-topic-tree-group-' . (int) $node['id'] ); ?>">−</button>
						<?php else : ?>
							<span class="hd-topic-tree__toggle-placeholder" aria-hidden="true"></span>
						<?php endif; ?>
						<span class="hd-topic-tree__connector" aria-hidden="true"><?php echo esc_html( $this->buildTreeConnector( $ancestor_has_more, $is_last ) ); ?></span>
						<div>
							<strong>
								<a href="<?php echo esc_url( $this->getListUrl( array( 'action' => 'edit', 'id' => (int) $node['id'] ) ) ); ?>">
									<?php echo esc_html( (string) $node['name'] ); ?>
								</a>
							</strong>
							<div class="hd-topic-tree__meta">
								<span class="hd-topic-tree__badge <?php echo esc_attr( empty( $node['is_active'] ) ? 'is-inactive' : '' ); ?>"><?php echo esc_html( ! empty( $node['is_active'] ) ? __( 'Active', 'wp-helpdesk' ) : __( 'Inactive', 'wp-helpdesk' ) ); ?></span>
								<span class="hd-topic-tree__badge"><?php echo esc_html( sprintf( __( 'Depth %d', 'wp-helpdesk' ), (int) $node['depth'] ) ); ?></span>
								<span class="hd-topic-tree__badge"><?php echo esc_html( sprintf( _n( '%d child', '%d children', (int) $node['child_count'], 'wp-helpdesk' ), (int) $node['child_count'] ) ); ?></span>
								<span class="hd-topic-tree__badge"><?php echo esc_html( sprintf( __( 'Order %d', 'wp-helpdesk' ), (int) $node['sort_order'] ) ); ?></span>
							</div>
						</div>
					</div>
					<div><?php echo esc_html( (string) $node['slug'] ); ?></div>
					<div><?php echo esc_html( ! empty( $node['is_active'] ) ? __( 'Yes', 'wp-helpdesk' ) : __( 'No', 'wp-helpdesk' ) ); ?></div>
					<div><?php echo esc_html( (string) $node['updated_at'] ); ?></div>
					<div class="hd-topic-tree__actions"><?php $this->renderTopicRowActions( $node ); ?></div>
				</div>
				<?php if ( $has_children ) : ?>
					<div class="hd-topic-tree__group" id="<?php echo esc_attr( 'hd-topic-tree-group-' . (int) $node['id'] ); ?>" data-hd-tree-group="1" role="group">
						<?php
						$child_ancestor_has_more   = $ancestor_has_more;
						$child_ancestor_has_more[] = ! $is_last;
						$this->renderTreeNodes( $node['children'], $child_ancestor_has_more );
						?>
					</div>
				<?php endif; ?>
			</div>
			<?php
		}
	}

	/**
	 * Render row-level topic actions.
	 *
	 * @param array<string, mixed> $topic Topic row.
	 * @return void
	 */
	protected function renderTopicRowActions( array $topic ): void {
		?>
		<a class="button button-small" href="<?php echo esc_url( $this->getListUrl( array( 'action' => 'edit', 'id' => (int) $topic['id'] ) ) ); ?>"><?php esc_html_e( 'Edit', 'wp-helpdesk' ); ?></a>
		<a class="button button-small" href="<?php echo esc_url( $this->getListUrl( array( 'action' => 'new', 'parent_topic_id' => (int) $topic['id'] ) ) ); ?>"><?php esc_html_e( 'Add Child', 'wp-helpdesk' ); ?></a>
		<form method="post">
			<?php wp_nonce_field( 'hd_topic_action', 'hd_topic_nonce' ); ?>
			<input type="hidden" name="hd_topic_action" value="<?php echo esc_attr( ! empty( $topic['is_active'] ) ? 'deactivate' : 'activate' ); ?>">
			<input type="hidden" name="hd_topic_id" value="<?php echo esc_attr( (string) $topic['id'] ); ?>">
			<button type="submit" class="button button-small"><?php echo esc_html( ! empty( $topic['is_active'] ) ? __( 'Deactivate', 'wp-helpdesk' ) : __( 'Activate', 'wp-helpdesk' ) ); ?></button>
		</form>
		<form method="post">
			<?php wp_nonce_field( 'hd_topic_action', 'hd_topic_nonce' ); ?>
			<input type="hidden" name="hd_topic_action" value="delete">
			<input type="hidden" name="hd_topic_id" value="<?php echo esc_attr( (string) $topic['id'] ); ?>">
			<button type="submit" class="button button-small" onclick="return confirm('<?php echo esc_js( __( 'Delete this topic?', 'wp-helpdesk' ) ); ?>');"><?php esc_html_e( 'Delete', 'wp-helpdesk' ); ?></button>
		</form>
		<?php
	}

	/**
	 * Build the current admin hierarchy tree.
	 *
	 * @param array<int, array<string, mixed>> $topics Topics.
	 * @param string                           $search Search query.
	 * @return array<int, array<string, mixed>>
	 */
	protected function buildTopicTree( array $topics, string $search = '' ): array {
		return $this->topic_service->buildTopicTree( $topics, $this->topic_transition_service->getAdminParentTopicIdsMap(), $search );
	}

	/**
	 * Count nodes recursively.
	 *
	 * @param array<int, array<string, mixed>> $nodes Nodes.
	 * @return int
	 */
	protected function countTreeNodes( array $nodes ): int {
		$total = 0;

		foreach ( $nodes as $node ) {
			$total += 1 + $this->countTreeNodes( $node['children'] ?? array() );
		}

		return $total;
	}

	/**
	 * Resolve the current admin list view mode.
	 *
	 * @return string
	 */
	protected function getCurrentViewMode(): string {
		$requested_view = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : '';
		if ( in_array( $requested_view, array( 'tree', 'flat' ), true ) ) {
			update_user_meta( get_current_user_id(), 'hd_topics_view_mode', $requested_view );
			return $requested_view;
		}

		$stored_view = sanitize_key( (string) get_user_meta( get_current_user_id(), 'hd_topics_view_mode', true ) );

		return 'flat' === $stored_view ? 'flat' : 'tree';
	}

	/**
	 * Build monospace branch connector text for a tree row.
	 *
	 * @param array<int, bool> $ancestor_has_more Ancestor continuation flags.
	 * @param bool             $is_last Whether the current node is the last among its siblings.
	 * @return string
	 */
	protected function buildTreeConnector( array $ancestor_has_more, bool $is_last ): string {
		$connector = '';

		foreach ( $ancestor_has_more as $has_more ) {
			$connector .= $has_more ? '│  ' : '   ';
		}

		return $connector . ( $is_last ? '└─ ' : '├─ ' );
	}

	/**
	 * Handle topic creation.
	 *
	 * @return void
	 */
	protected function handleCreate(): void {
		$payload   = $this->getTopicPayloadFromPost();
		$type      = (string) ( $payload['type'] ?? 'root' );
		$parent_id = isset( $payload['parent_id'] ) && (int) $payload['parent_id'] > 0 ? (int) $payload['parent_id'] : null;

		$error_code = $this->topic_service->validateTypeConstraints( $type, $parent_id, 0 );
		if ( null !== $error_code ) {
			$this->redirectToForm( 'new', $error_code );
			return;
		}

		$topic_id = $this->topic_service->createTopic( $payload );
		$this->redirectToList( $topic_id > 0 ? 'created' : 'error' );
	}

	/**
	 * Handle topic update.
	 *
	 * @return void
	 */
	protected function handleUpdate(): void {
		$topic_id = isset( $_POST['hd_topic_id'] ) ? (int) $_POST['hd_topic_id'] : 0;
		if ( $topic_id <= 0 ) {
			$this->redirectToList( 'not-found' );
			return;
		}

		$payload   = $this->getTopicPayloadFromPost();
		$type      = (string) ( $payload['type'] ?? 'root' );
		$parent_id = isset( $payload['parent_id'] ) && (int) $payload['parent_id'] > 0 ? (int) $payload['parent_id'] : null;

		$error_code = $this->topic_service->validateTypeConstraints( $type, $parent_id, $topic_id );
		if ( null !== $error_code ) {
			$this->redirectToForm( 'edit', $error_code, $topic_id );
			return;
		}

		$updated = $this->topic_service->updateTopic( $topic_id, $payload );
		$this->redirectToList( $updated ? 'updated' : 'error' );
	}

	/**
	 * Handle topic deletion.
	 *
	 * @return void
	 */
	protected function handleDelete(): void {
		$topic_id = isset( $_POST['hd_topic_id'] ) ? (int) $_POST['hd_topic_id'] : 0;
		if ( $topic_id <= 0 ) {
			$this->redirectToList( 'not-found' );
			return;
		}

		$deleted = $this->topic_service->deleteTopic( $topic_id );
		$this->redirectToList( $deleted ? 'deleted' : 'error' );
	}

	/**
	 * Handle single activate/deactivate.
	 *
	 * @param bool $active Active flag.
	 * @return void
	 */
	protected function handleSetActive( bool $active ): void {
		$topic_id = isset( $_POST['hd_topic_id'] ) ? (int) $_POST['hd_topic_id'] : 0;
		if ( $topic_id <= 0 ) {
			$this->redirectToList( 'not-found' );
			return;
		}

		$updated = $this->topic_service->setActive( $topic_id, $active );
		$this->redirectToList( $updated ? ( $active ? 'activated' : 'deactivated' ) : 'error' );
	}

	/**
	 * Handle bulk actions.
	 *
	 * @return void
	 */
	protected function handleBulk(): void {
		$bulk_action = isset( $_POST['bulk_action'] ) ? sanitize_key( wp_unslash( $_POST['bulk_action'] ) ) : '';
		$topic_ids   = isset( $_POST['topic_ids'] ) && is_array( $_POST['topic_ids'] ) ? array_map( 'intval', wp_unslash( $_POST['topic_ids'] ) ) : array();
		$topic_ids   = array_values( array_filter( $topic_ids ) );

		if ( empty( $bulk_action ) || empty( $topic_ids ) ) {
			$this->redirectToList( 'invalid' );
			return;
		}

		$changed = 0;
		foreach ( $topic_ids as $topic_id ) {
			switch ( $bulk_action ) {
				case 'activate':
					$changed += $this->topic_service->setActive( $topic_id, true ) ? 1 : 0;
					break;
				case 'deactivate':
					$changed += $this->topic_service->setActive( $topic_id, false ) ? 1 : 0;
					break;
				case 'delete':
					$changed += $this->topic_service->deleteTopic( $topic_id ) ? 1 : 0;
					break;
			}
		}

		if ( 0 === $changed ) {
			$this->redirectToList( 'error' );
			return;
		}

		$this->redirectToList( 'bulk-updated' );
	}

	/**
	 * Build sanitized topic payload from POST.
	 *
	 * @return array<string, mixed>
	 */
	protected function getTopicPayloadFromPost(): array {
		$topic_type = isset( $_POST['topic_type'] ) && 'followup' === sanitize_key( wp_unslash( $_POST['topic_type'] ) ) ? 'followup' : 'root';
		$parent_id  = isset( $_POST['parent_id'] ) ? (int) wp_unslash( $_POST['parent_id'] ) : 0;

		return array(
			'name'        => isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '',
			'description' => isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '',
			'type'        => $topic_type,
			'parent_id'   => $parent_id > 0 ? $parent_id : null,
			'is_active'   => isset( $_POST['is_active'] ) ? 1 : 0,
			'sort_order'  => isset( $_POST['sort_order'] ) ? (int) wp_unslash( $_POST['sort_order'] ) : 0,
			// Keep legacy fields for backward compat with code that checks them.
			'hierarchy_type' => 'followup' === $topic_type ? 'follow_up' : 'top_level',
		);
	}

	/**
	 * Parse selected follow-up topic ids from POST (legacy support).
	 *
	 * @return array<int, int>
	 */
	protected function getNextTopicIdsFromPost(): array {
		$next_topic_ids = isset( $_POST['next_topic_ids'] ) && is_array( $_POST['next_topic_ids'] ) ? wp_unslash( $_POST['next_topic_ids'] ) : array();

		return array_values( array_unique( array_filter( array_map( 'intval', $next_topic_ids ) ) ) );
	}

	/**
	 * Parse selected parent topic ids from POST (legacy support).
	 *
	 * @return array<int, int>
	 */
	protected function getParentTopicIdsFromPost(): array {
		$parent_topic_ids = isset( $_POST['parent_topic_ids'] ) && is_array( $_POST['parent_topic_ids'] ) ? wp_unslash( $_POST['parent_topic_ids'] ) : array();

		return array_values( array_unique( array_filter( array_map( 'intval', $parent_topic_ids ) ) ) );
	}

	/**
	 * Render admin notices from the message query arg.
	 *
	 * @return void
	 */
	protected function renderNotice(): void {
		$msg = isset( $_GET['msg'] ) ? sanitize_key( wp_unslash( $_GET['msg'] ) ) : '';
		if ( '' === $msg ) {
			return;
		}

		$messages = array(
			'created'      => array( 'success', __( 'Topic created.', 'wp-helpdesk' ) ),
			'updated'      => array( 'success', __( 'Topic updated.', 'wp-helpdesk' ) ),
			'deleted'      => array( 'success', __( 'Topic deleted.', 'wp-helpdesk' ) ),
			'activated'    => array( 'success', __( 'Topic activated.', 'wp-helpdesk' ) ),
			'deactivated'  => array( 'success', __( 'Topic deactivated.', 'wp-helpdesk' ) ),
			'bulk-updated' => array( 'success', __( 'Bulk action completed.', 'wp-helpdesk' ) ),
			'invalid'      => array( 'warning', __( 'Select at least one topic and a valid bulk action.', 'wp-helpdesk' ) ),
			'not-found'    => array( 'error', __( 'Topic not found.', 'wp-helpdesk' ) ),
			// New simplified model error codes.
			'root-cannot-have-parent'  => array( 'error', __( 'Root topics cannot have a parent topic.', 'wp-helpdesk' ) ),
			'followup-missing-parent'  => array( 'error', __( 'Follow-up topics must have a parent topic.', 'wp-helpdesk' ) ),
			'invalid-parent-topic'     => array( 'error', __( 'The selected parent topic is invalid.', 'wp-helpdesk' ) ),
			'circular-parent-topic'    => array( 'error', __( 'The selected parent would create a circular hierarchy.', 'wp-helpdesk' ) ),
			'invalid-topic-type'       => array( 'error', __( 'Invalid topic type.', 'wp-helpdesk' ) ),
			// Legacy error codes (kept for backward compat).
			'branch-missing-transition' => array( 'error', __( 'Branch topics must include at least one valid follow-up topic.', 'wp-helpdesk' ) ),
			'invalid-transition' => array( 'error', __( 'One or more selected follow-up topics are invalid.', 'wp-helpdesk' ) ),
			'follow-up-missing-parent' => array( 'error', __( 'Follow-up topics must include at least one valid parent topic.', 'wp-helpdesk' ) ),
			'top-level-has-parent' => array( 'error', __( 'Top-level topics cannot have selected parent topics.', 'wp-helpdesk' ) ),
			'error'        => array( 'error', __( 'Unable to save the topic.', 'wp-helpdesk' ) ),
		);

		if ( ! isset( $messages[ $msg ] ) ) {
			return;
		}

		list( $type, $message ) = $messages[ $msg ];
		?>
		<div class="notice notice-<?php echo esc_attr( $type ); ?> is-dismissible"><p><?php echo esc_html( $message ); ?></p></div>
		<?php
	}

	/**
	 * Get the topics list URL.
	 *
	 * @param array<string, mixed> $args Optional query args.
	 * @return string
	 */
	protected function getListUrl( array $args = [] ): string {
		$defaults = array( 'page' => 'wp-helpdesk-topics' );

		return network_admin_url( 'admin.php?' . http_build_query( array_merge( $defaults, $args ) ) );
	}

	/**
	 * Redirect to the list view with a message.
	 *
	 * @param string $message Message code.
	 * @return void
	 */
	protected function redirectToList( string $message ): void {
		wp_safe_redirect( $this->getListUrl( array( 'msg' => $message ) ) );
		exit;
	}

	/**
	 * Redirect back to the topic form with a message.
	 *
	 * @param string $action Form action.
	 * @param string $message Message code.
	 * @param int    $topic_id Optional topic id.
	 * @return void
	 */
	protected function redirectToForm( string $action, string $message, int $topic_id = 0 ): void {
		$args = array(
			'action' => $action,
			'msg'    => $message,
		);

		if ( $topic_id > 0 ) {
			$args['id'] = $topic_id;
		}

		wp_safe_redirect( $this->getListUrl( $args ) );
		exit;
	}
}
