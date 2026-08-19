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
		$search   = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$page     = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;
		$per_page = 20;
		$args     = array(
			'search'   => $search,
			'page'     => $page,
			'per_page' => $per_page,
		);
		$topics   = $this->topic_service->listTopics( $args );
		$total    = $this->topic_service->countTopics( $args );
		$pages    = max( 1, (int) ceil( $total / $per_page ) );
		$list_url = $this->getListUrl();
		?>
		<h1 class="wp-heading-inline"><?php esc_html_e( 'Topics', 'wp-helpdesk' ); ?></h1>
		<a href="<?php echo esc_url( $this->getListUrl( array( 'action' => 'new' ) ) ); ?>" class="page-title-action"><?php esc_html_e( 'Add New', 'wp-helpdesk' ); ?></a>
		<hr class="wp-header-end">

		<form method="get" style="margin:16px 0;">
			<input type="hidden" name="page" value="wp-helpdesk-topics">
			<p class="search-box">
				<label class="screen-reader-text" for="topic-search-input"><?php esc_html_e( 'Search topics', 'wp-helpdesk' ); ?></label>
				<input type="search" id="topic-search-input" name="s" value="<?php echo esc_attr( $search ); ?>">
				<?php submit_button( __( 'Search Topics', 'wp-helpdesk' ), '', '', false, array( 'id' => 'search-submit' ) ); ?>
			</p>
		</form>

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
						<?php echo esc_html( sprintf( _n( '%d item', '%d items', $total, 'wp-helpdesk' ), $total ) ); ?>
					</span>
				</div>
			</div>

			<table class="widefat striped">
				<thead>
					<tr>
						<td class="manage-column column-cb check-column"><input type="checkbox" aria-label="<?php esc_attr_e( 'Select all topics', 'wp-helpdesk' ); ?>"></td>
						<th scope="col"><?php esc_html_e( 'Name', 'wp-helpdesk' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Slug', 'wp-helpdesk' ); ?></th>
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
								<td><?php echo esc_html( (string) $topic['slug'] ); ?></td>
								<td><?php echo esc_html( ! empty( $topic['is_active'] ) ? __( 'Yes', 'wp-helpdesk' ) : __( 'No', 'wp-helpdesk' ) ); ?></td>
								<td><?php echo esc_html( (string) (int) $topic['sort_order'] ); ?></td>
								<td><?php echo esc_html( (string) $topic['updated_at'] ); ?></td>
								<td>
									<a class="button button-small" href="<?php echo esc_url( $this->getListUrl( array( 'action' => 'edit', 'id' => (int) $topic['id'] ) ) ); ?>"><?php esc_html_e( 'Edit', 'wp-helpdesk' ); ?></a>
									<form method="post" style="display:inline-block;margin-left:4px;">
										<?php wp_nonce_field( 'hd_topic_action', 'hd_topic_nonce' ); ?>
										<input type="hidden" name="hd_topic_action" value="<?php echo esc_attr( ! empty( $topic['is_active'] ) ? 'deactivate' : 'activate' ); ?>">
										<input type="hidden" name="hd_topic_id" value="<?php echo esc_attr( (string) $topic['id'] ); ?>">
										<button type="submit" class="button button-small"><?php echo esc_html( ! empty( $topic['is_active'] ) ? __( 'Deactivate', 'wp-helpdesk' ) : __( 'Activate', 'wp-helpdesk' ) ); ?></button>
									</form>
									<form method="post" style="display:inline-block;margin-left:4px;">
										<?php wp_nonce_field( 'hd_topic_action', 'hd_topic_nonce' ); ?>
										<input type="hidden" name="hd_topic_action" value="delete">
										<input type="hidden" name="hd_topic_id" value="<?php echo esc_attr( (string) $topic['id'] ); ?>">
										<button type="submit" class="button button-small" onclick="return confirm('<?php echo esc_js( __( 'Delete this topic?', 'wp-helpdesk' ) ); ?>');"><?php esc_html_e( 'Delete', 'wp-helpdesk' ); ?></button>
									</form>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</form>

		<?php if ( $pages > 1 ) : ?>
			<div class="tablenav bottom">
				<div class="tablenav-pages">
					<?php
					echo wp_kses_post(
						paginate_links(
							array(
								'base'      => esc_url_raw( add_query_arg( 'paged', '%#%', $list_url ) ),
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
		$selected_next_ids   = ! empty( $topic['id'] ) ? $this->topic_transition_service->getSelectableNextTopicIds( (int) $topic['id'] ) : array();
		$selected_parent_ids = ! empty( $topic['id'] ) ? $this->topic_transition_service->getSelectableParentTopicIds( (int) $topic['id'] ) : array();
		$hierarchy_type      = isset( $topic['hierarchy_type'] ) && 'follow_up' === (string) $topic['hierarchy_type'] ? 'follow_up' : 'top_level';
		$candidate_topics    = $this->topic_service->listTopics(
			array(
				'per_page' => 250,
			)
		);
		$available_next_step = array_filter(
			$candidate_topics,
			static fn( array $candidate ): bool => (int) ( $candidate['id'] ?? 0 ) !== (int) ( $topic['id'] ?? 0 )
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
					<th scope="row"><label for="hd-topic-slug"><?php esc_html_e( 'Slug', 'wp-helpdesk' ); ?></label></th>
					<td>
						<input type="text" id="hd-topic-slug" name="slug" class="regular-text" value="<?php echo esc_attr( (string) $topic['slug'] ); ?>">
						<p class="description"><?php esc_html_e( 'Leave blank to generate from the name.', 'wp-helpdesk' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="hd-topic-description"><?php esc_html_e( 'Description', 'wp-helpdesk' ); ?></label></th>
					<td>
						<textarea id="hd-topic-description" name="description" rows="6" class="large-text"><?php echo esc_textarea( (string) $topic['description'] ); ?></textarea>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Flow behavior', 'wp-helpdesk' ); ?></th>
					<td>
						<label style="display:block;margin-bottom:8px;">
							<input type="radio" name="node_type" value="final" <?php checked( $node_type, 'final' ); ?>>
							<?php esc_html_e( 'Final step', 'wp-helpdesk' ); ?>
						</label>
						<label style="display:block;">
							<input type="radio" name="node_type" value="branch" <?php checked( $node_type, 'branch' ); ?>>
							<?php esc_html_e( 'Branch to follow-up topics', 'wp-helpdesk' ); ?>
						</label>
						<p class="description"><?php esc_html_e( 'Final topics end the flow. Branch topics must point to at least one follow-up topic.', 'wp-helpdesk' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Hierarchy placement', 'wp-helpdesk' ); ?></th>
					<td>
						<label style="display:block;margin-bottom:8px;">
							<input type="radio" name="hierarchy_type" value="top_level" <?php checked( $hierarchy_type, 'top_level' ); ?>>
							<?php esc_html_e( 'Top-level topic (shown in first step)', 'wp-helpdesk' ); ?>
						</label>
						<label style="display:block;">
							<input type="radio" name="hierarchy_type" value="follow_up" <?php checked( $hierarchy_type, 'follow_up' ); ?>>
							<?php esc_html_e( 'Follow-up topic (reachable from selected parent topics)', 'wp-helpdesk' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="hd-topic-parent-topics"><?php esc_html_e( 'Parent topics', 'wp-helpdesk' ); ?></label></th>
					<td>
						<select id="hd-topic-parent-topics" name="parent_topic_ids[]" class="regular-text" multiple size="6">
							<?php foreach ( $available_parent_topics as $candidate_topic ) : ?>
								<?php $candidate_id = (int) $candidate_topic['id']; ?>
								<option value="<?php echo esc_attr( (string) $candidate_id ); ?>" <?php selected( in_array( $candidate_id, $selected_parent_ids, true ) ); ?>>
									<?php echo esc_html( (string) $candidate_topic['name'] ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php esc_html_e( 'Used when the topic is a follow-up topic. Select one or more valid parent topics.', 'wp-helpdesk' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="hd-topic-next-topics"><?php esc_html_e( 'Follow-up topics', 'wp-helpdesk' ); ?></label></th>
					<td>
						<select id="hd-topic-next-topics" name="next_topic_ids[]" class="regular-text" multiple size="6">
							<?php foreach ( $available_next_step as $candidate_topic ) : ?>
								<?php $candidate_id = (int) $candidate_topic['id']; ?>
								<option value="<?php echo esc_attr( (string) $candidate_id ); ?>" <?php selected( in_array( $candidate_id, $selected_next_ids, true ) ); ?>>
									<?php echo esc_html( (string) $candidate_topic['name'] ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php esc_html_e( 'Only used when the topic branches. Select one or more valid next topics.', 'wp-helpdesk' ); ?></p>
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
		<?php
	}

	/**
	 * Handle topic creation.
	 *
	 * @return void
	 */
	protected function handleCreate(): void {
		$payload        = $this->getTopicPayloadFromPost();
		$next_topic_ids = $this->getNextTopicIdsFromPost();
		$parent_topic_ids = $this->getParentTopicIdsFromPost();
		$error_code     = $this->topic_transition_service->validateBranchConfiguration( 0, ! empty( $payload['is_final'] ), $next_topic_ids );

		if ( null !== $error_code ) {
			$this->redirectToForm( 'new', $error_code );
			return;
		}

		$error_code = $this->topic_transition_service->validateHierarchyConfiguration( 0, (string) ( $payload['hierarchy_type'] ?? 'top_level' ), $parent_topic_ids );
		if ( null !== $error_code ) {
			$this->redirectToForm( 'new', $error_code );
			return;
		}

		$topic_id = $this->topic_service->createTopic( $payload );
		if (
			$topic_id > 0
			&& ! $this->topic_transition_service->syncAdminParentTopics( $topic_id, 'follow_up' === (string) ( $payload['hierarchy_type'] ?? 'top_level' ) ? $parent_topic_ids : array() )
		) {
			$this->redirectToForm( 'edit', 'error', $topic_id );
			return;
		}

		if ( $topic_id > 0 && ! $this->topic_transition_service->syncAdminNextTopics( $topic_id, ! empty( $payload['is_final'] ) ? array() : $next_topic_ids ) ) {
			$this->redirectToForm( 'edit', 'error', $topic_id );
			return;
		}

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

		$payload        = $this->getTopicPayloadFromPost();
		$next_topic_ids = $this->getNextTopicIdsFromPost();
		$parent_topic_ids = $this->getParentTopicIdsFromPost();
		$error_code     = $this->topic_transition_service->validateBranchConfiguration( $topic_id, ! empty( $payload['is_final'] ), $next_topic_ids );

		if ( null !== $error_code ) {
			$this->redirectToForm( 'edit', $error_code, $topic_id );
			return;
		}

		$error_code = $this->topic_transition_service->validateHierarchyConfiguration( $topic_id, (string) ( $payload['hierarchy_type'] ?? 'top_level' ), $parent_topic_ids );
		if ( null !== $error_code ) {
			$this->redirectToForm( 'edit', $error_code, $topic_id );
			return;
		}

		$updated = $this->topic_service->updateTopic( $topic_id, $payload );
		if (
			$updated
			&& ! $this->topic_transition_service->syncAdminParentTopics( $topic_id, 'follow_up' === (string) ( $payload['hierarchy_type'] ?? 'top_level' ) ? $parent_topic_ids : array() )
		) {
			$this->redirectToForm( 'edit', 'error', $topic_id );
			return;
		}
		if ( $updated && ! $this->topic_transition_service->syncAdminNextTopics( $topic_id, ! empty( $payload['is_final'] ) ? array() : $next_topic_ids ) ) {
			$this->redirectToForm( 'edit', 'error', $topic_id );
			return;
		}

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
		$node_type = isset( $_POST['node_type'] ) ? sanitize_key( wp_unslash( $_POST['node_type'] ) ) : 'branch';

		return array(
			'name'        => isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '',
			'slug'        => isset( $_POST['slug'] ) ? sanitize_text_field( wp_unslash( $_POST['slug'] ) ) : '',
			'description' => isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '',
			'is_final'    => 'final' === $node_type ? 1 : 0,
			'node_type'   => $node_type,
			'hierarchy_type' => isset( $_POST['hierarchy_type'] ) && 'follow_up' === sanitize_key( wp_unslash( $_POST['hierarchy_type'] ) ) ? 'follow_up' : 'top_level',
			'is_active'   => isset( $_POST['is_active'] ) ? 1 : 0,
			'sort_order'  => isset( $_POST['sort_order'] ) ? (int) wp_unslash( $_POST['sort_order'] ) : 0,
		);
	}

	/**
	 * Parse selected follow-up topic ids from POST.
	 *
	 * @return array<int, int>
	 */
	protected function getNextTopicIdsFromPost(): array {
		$next_topic_ids = isset( $_POST['next_topic_ids'] ) && is_array( $_POST['next_topic_ids'] ) ? wp_unslash( $_POST['next_topic_ids'] ) : array();

		$next_topic_ids = array_values( array_unique( array_filter( array_map( 'intval', $next_topic_ids ) ) ) );

		return $next_topic_ids;
	}

	/**
	 * Parse selected parent topic ids from POST.
	 *
	 * @return array<int, int>
	 */
	protected function getParentTopicIdsFromPost(): array {
		$parent_topic_ids = isset( $_POST['parent_topic_ids'] ) && is_array( $_POST['parent_topic_ids'] ) ? wp_unslash( $_POST['parent_topic_ids'] ) : array();

		$parent_topic_ids = array_values( array_unique( array_filter( array_map( 'intval', $parent_topic_ids ) ) ) );

		return $parent_topic_ids;
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
			'branch-missing-transition' => array( 'error', __( 'Branch topics must include at least one valid follow-up topic.', 'wp-helpdesk' ) ),
			'invalid-transition' => array( 'error', __( 'One or more selected follow-up topics are invalid.', 'wp-helpdesk' ) ),
			'follow-up-missing-parent' => array( 'error', __( 'Follow-up topics must include at least one valid parent topic.', 'wp-helpdesk' ) ),
			'invalid-parent-topic' => array( 'error', __( 'One or more selected parent topics are invalid.', 'wp-helpdesk' ) ),
			'top-level-has-parent' => array( 'error', __( 'Top-level topics cannot have incoming parent transitions.', 'wp-helpdesk' ) ),
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
