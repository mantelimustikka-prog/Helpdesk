<?php
/**
 * @package WP Helpdesk
 */

namespace WPHelpdesk\Interfaces\Frontend;

use WPHelpdesk\Domain\Attachment\AttachmentService;
use WPHelpdesk\Domain\Message\MessageService;
use WPHelpdesk\Domain\Ticket\TicketRepository;
use WPHelpdesk\Support\Helpers;
use WPHelpdesk\Support\RendersAttachmentsTrait;

class WooCommerceAccountHelpdesk {
	use RendersAttachmentsTrait;

	public const ENDPOINT = 'helpdesk';

	protected TicketRepository $ticket_repository;
	protected MessageService $message_service;
	protected AttachmentService $attachment_service;

	/** @var array{type:string,message:string}|null */
	protected ?array $notice = null;

	public function __construct(
		?TicketRepository $ticket_repository = null,
		?MessageService $message_service = null,
		?AttachmentService $attachment_service = null
	) {
		$this->ticket_repository = $ticket_repository ?: new TicketRepository();
		$this->message_service   = $message_service ?: new MessageService();
		$this->attachment_service = $attachment_service ?: new AttachmentService();
	}

	/**
	 * Register WooCommerce My Account hooks when WooCommerce account URLs exist.
	 *
	 * @return void
	 */
	public function register(): void {
		if ( ! $this->isWooCommerceAvailable() ) {
			return;
		}

		// When register() is called at init priority 1 (the normal production
		// path), init is already in progress so we register the endpoint directly
		// rather than scheduling another init callback.  Scheduling via add_action
		// is kept as the fallback for any remaining callers that invoke register()
		// before init fires (e.g. unit tests or legacy call sites).
		if ( function_exists( 'doing_action' ) && doing_action( 'init' ) ) {
			$this->addEndpoint();
		} else {
			add_action( 'init', array( $this, 'addEndpoint' ) );
		}

		add_filter( 'query_vars', array( $this, 'addQueryVars' ) );
		add_filter( 'woocommerce_account_menu_items', array( $this, 'addMenuItem' ), 40 );
		// Safety-net: re-insert after any third-party filter that may have replaced
		// the menu array.  A competing theme/plugin that reconstructs the menu from
		// scratch at, say, priority 50 would wipe out our priority-40 insertion;
		// the priority-9999 callback guarantees Helpdesk is still present in the
		// final array handed to the template.
		add_filter( 'woocommerce_account_menu_items', array( $this, 'addMenuItem' ), 9999 );
		add_action( 'woocommerce_account_' . self::ENDPOINT . '_endpoint', array( $this, 'render' ) );
	}

	/**
	 * Register the account endpoint.
	 *
	 * @return void
	 */
	public function addEndpoint(): void {
		$mask = ( defined( 'EP_ROOT' ) ? EP_ROOT : 0 ) | ( defined( 'EP_PAGES' ) ? EP_PAGES : 0 );
		add_rewrite_endpoint( self::ENDPOINT, $mask );
	}

	/**
	 * Add the endpoint query var for fallback requests.
	 *
	 * @param array<int, string> $vars Existing query vars.
	 * @return array<int, string>
	 */
	public function addQueryVars( array $vars ): array {
		if ( ! in_array( self::ENDPOINT, $vars, true ) ) {
			$vars[] = self::ENDPOINT;
		}

		return $vars;
	}

	/**
	 * Inject the top-level Helpdesk account menu item.
	 *
	 * @param array<string, string> $items Existing account items.
	 * @return array<string, string>
	 */
	public function addMenuItem( array $items ): array {
		if ( isset( $items[ self::ENDPOINT ] ) ) {
			return $items;
		}

		$updated  = array();
		$inserted = false;

		foreach ( $items as $key => $label ) {
			if ( 'customer-logout' === $key && ! $inserted ) {
				$updated[ self::ENDPOINT ] = __( 'Helpdesk', 'wp-helpdesk' );
				$inserted                  = true;
			}

			$updated[ $key ] = $label;
		}

		if ( ! $inserted ) {
			$updated[ self::ENDPOINT ] = __( 'Helpdesk', 'wp-helpdesk' );
		}

		return $updated;
	}

	/**
	 * Front-end interfaces for the admin dashboard listing.
	 *
	 * @return array<int, array{group:string,label:string,url:string,path:string}>
	 */
	public function getInterfaceLinks(): array {
		$links = array(
			array(
				'group' => __( 'Standalone Helpdesk pages', 'wp-helpdesk' ),
				'label' => __( 'Helpdesk home', 'wp-helpdesk' ),
				'url'   => home_url( '/helpdesk/' ),
				'path'  => $this->extractPath( home_url( '/helpdesk/' ) ),
			),
			array(
				'group' => __( 'Standalone Helpdesk pages', 'wp-helpdesk' ),
				'label' => __( 'New request (guest)', 'wp-helpdesk' ),
				'url'   => home_url( '/helpdesk/new/' ),
				'path'  => $this->extractPath( home_url( '/helpdesk/new/' ) ),
			),
			array(
				'group' => __( 'Standalone Helpdesk pages', 'wp-helpdesk' ),
				'label' => __( 'New request (member)', 'wp-helpdesk' ),
				'url'   => home_url( '/helpdesk/member/new/' ),
				'path'  => $this->extractPath( home_url( '/helpdesk/member/new/' ) ),
			),
		);

		if ( $this->isWooCommerceAvailable() ) {
			$links[] = array(
				'group' => __( 'WooCommerce My Account', 'wp-helpdesk' ),
				'label' => __( 'Helpdesk overview', 'wp-helpdesk' ),
				'url'   => $this->buildAccountUrl(),
				'path'  => $this->extractPath( $this->buildAccountUrl() ),
			);
			$links[] = array(
				'group' => __( 'WooCommerce My Account', 'wp-helpdesk' ),
				'label' => __( 'My requests', 'wp-helpdesk' ),
				'url'   => $this->buildAccountUrl( 'requests' ),
				'path'  => $this->extractPath( $this->buildAccountUrl( 'requests' ) ),
			);
			$links[] = array(
				'group' => __( 'WooCommerce My Account', 'wp-helpdesk' ),
				'label' => __( 'New request entry point', 'wp-helpdesk' ),
				'url'   => $this->buildAccountUrl( 'new' ),
				'path'  => $this->extractPath( $this->buildAccountUrl( 'new' ) ),
			);
			$links[] = array(
				'group' => __( 'WooCommerce My Account', 'wp-helpdesk' ),
				'label' => __( 'Request details / status pattern', 'wp-helpdesk' ),
				'url'   => $this->buildAccountUrl( 'request/{ticket-no}' ),
				'path'  => $this->extractPath( $this->buildAccountUrl( 'request/{ticket-no}' ) ),
			);
		}

		return $links;
	}

	/**
	 * Render the account endpoint content.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! is_user_logged_in() ) {
			$this->renderMessageBlock(
				__( 'Helpdesk', 'wp-helpdesk' ),
				__( 'Please sign in to access your helpdesk requests.', 'wp-helpdesk' ),
				'notice'
			);
			return;
		}

		$route = $this->parseEndpointRequest();

		if ( 'POST' === strtoupper( (string) ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) && 'request' === $route['view'] ) {
			if ( $this->handleReplySubmission( $route['ticket_no'] ) ) {
				return;
			}
		}

		$active_nav = 'request' === $route['view'] ? 'requests' : $route['view'];
		$links      = $this->getNavigationLinks( $active_nav );

		?>
		<section class="hd-account-helpdesk">
			<p class="hd-account-helpdesk__breadcrumbs"><?php esc_html_e( 'My Account / Helpdesk', 'wp-helpdesk' ); ?></p>
			<h2><?php esc_html_e( 'Helpdesk', 'wp-helpdesk' ); ?></h2>

			<?php $this->renderNotice(); ?>

			<nav class="hd-account-helpdesk__nav" aria-label="<?php esc_attr_e( 'Helpdesk navigation', 'wp-helpdesk' ); ?>">
				<ul>
					<?php foreach ( $links as $link ) : ?>
						<li>
							<a class="<?php echo esc_attr( $link['active'] ? 'is-active' : '' ); ?>" href="<?php echo esc_url( $link['url'] ); ?>">
								<?php echo esc_html( $link['label'] ); ?>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			</nav>

			<?php
			switch ( $route['view'] ) {
				case 'new':
					$this->renderNewRequestPage();
					break;
				case 'requests':
					$this->renderRequestsPage();
					break;
				case 'request':
					$this->renderRequestDetailPage( $route['ticket_no'] );
					break;
				default:
					$this->renderOverviewPage();
					break;
			}
			?>
		</section>
		<?php
	}

	/**
	 * Render the overview subpage.
	 *
	 * @return void
	 */
	protected function renderOverviewPage(): void {
		$tickets = $this->listOwnedTickets( 5 );
		?>
		<div class="hd-account-helpdesk__section">
			<h3><?php esc_html_e( 'Your support options', 'wp-helpdesk' ); ?></h3>
			<p><?php esc_html_e( 'Create a new request or review your existing conversations with the helpdesk team.', 'wp-helpdesk' ); ?></p>
			<p>
				<a href="<?php echo esc_url( $this->buildAccountUrl( 'new' ) ); ?>"><?php esc_html_e( 'Start a new request', 'wp-helpdesk' ); ?></a>
				&nbsp;|&nbsp;
				<a href="<?php echo esc_url( $this->buildAccountUrl( 'requests' ) ); ?>"><?php esc_html_e( 'View my requests', 'wp-helpdesk' ); ?></a>
			</p>
		</div>
		<?php

		if ( empty( $tickets ) ) {
			$this->renderMessageBlock(
				__( 'No requests yet', 'wp-helpdesk' ),
				__( 'You have not created any helpdesk requests yet. Start a new request to contact support.', 'wp-helpdesk' ),
				'notice',
				$this->buildAccountUrl( 'new' ),
				__( 'Create request', 'wp-helpdesk' )
			);
			return;
		}

		$this->renderRequestsTable( $tickets );
	}

	/**
	 * Render the new-request entry page.
	 *
	 * @return void
	 */
	protected function renderNewRequestPage(): void {
		$this->renderMessageBlock(
			__( 'New request', 'wp-helpdesk' ),
			__( 'Use the member request form to submit a new support request with your account details prefilled.', 'wp-helpdesk' ),
			'notice',
			home_url( '/helpdesk/member/new/' ),
			__( 'Open request form', 'wp-helpdesk' )
		);
	}

	/**
	 * Render the requests list subpage.
	 *
	 * @return void
	 */
	protected function renderRequestsPage(): void {
		$tickets = $this->listOwnedTickets( 20 );

		if ( empty( $tickets ) ) {
			$this->renderMessageBlock(
				__( 'No requests found', 'wp-helpdesk' ),
				__( 'You do not have any helpdesk requests yet.', 'wp-helpdesk' ),
				'notice',
				$this->buildAccountUrl( 'new' ),
				__( 'Create request', 'wp-helpdesk' )
			);
			return;
		}

		$this->renderRequestsTable( $tickets );
	}

	/**
	 * Render a request detail view with replies.
	 *
	 * @param string $ticket_no Ticket number from the endpoint path.
	 * @return void
	 */
	protected function renderRequestDetailPage( string $ticket_no ): void {
		$ticket = $this->getAccessibleTicket( $ticket_no );

		if ( null === $ticket ) {
			$this->renderMessageBlock(
				__( 'Request not found', 'wp-helpdesk' ),
				__( 'That request could not be found or you do not have permission to view it.', 'wp-helpdesk' ),
				'error'
			);
			return;
		}

		$messages = $this->message_service->listMessages(
			(int) $ticket['id'],
			array(
				'is_internal' => 0,
			)
		);
		$attachments = $this->attachment_service->getForTicket( (int) $ticket['id'] );
		?>
		<div class="hd-account-helpdesk__section">
			<h3><?php echo esc_html( (string) $ticket['subject'] ); ?></h3>
			<p>
				<strong><?php esc_html_e( 'Request number:', 'wp-helpdesk' ); ?></strong>
				<?php echo esc_html( (string) $ticket['ticket_no'] ); ?>
				<br>
				<strong><?php esc_html_e( 'Status:', 'wp-helpdesk' ); ?></strong>
				<?php echo esc_html( (string) $ticket['status'] ); ?>
			</p>
			<p><a href="<?php echo esc_url( $this->buildAccountUrl( 'requests' ) ); ?>">&larr; <?php esc_html_e( 'Back to my requests', 'wp-helpdesk' ); ?></a></p>
		</div>

		<div class="hd-account-helpdesk__section">
			<h4><?php esc_html_e( 'Messages', 'wp-helpdesk' ); ?></h4>
			<?php if ( empty( $messages ) ) : ?>
				<p><?php esc_html_e( 'No messages yet.', 'wp-helpdesk' ); ?></p>
			<?php else : ?>
				<ul class="hd-account-helpdesk__messages">
					<?php foreach ( $messages as $message ) : ?>
						<li>
							<strong><?php echo esc_html( $this->formatAuthorType( (string) ( $message['author_type'] ?? '' ) ) ); ?></strong>
							<?php if ( ! empty( $message['created_at'] ) ) : ?>
								(<?php echo esc_html( (string) $message['created_at'] ); ?>)
							<?php endif; ?>
							<div><?php echo nl2br( esc_html( (string) ( $message['body'] ?? '' ) ) ); ?></div>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>

		<?php if ( ! empty( $attachments ) ) : ?>
		<div class="hd-account-helpdesk__section">
			<h4><?php esc_html_e( 'Attachments', 'wp-helpdesk' ); ?></h4>
			<?php $this->renderAttachments( $attachments ); ?>
		</div>
		<?php endif; ?>

		<div class="hd-account-helpdesk__section">
			<h4><?php esc_html_e( 'Send a reply', 'wp-helpdesk' ); ?></h4>
			<form method="post" enctype="multipart/form-data">
				<?php wp_nonce_field( 'hd_my_account_reply', 'hd_my_account_reply_nonce' ); ?>
				<input type="hidden" name="hd_helpdesk_action" value="reply">
				<p>
					<label for="hd-helpdesk-reply-body"><?php esc_html_e( 'Message', 'wp-helpdesk' ); ?></label><br>
					<textarea id="hd-helpdesk-reply-body" name="hd_helpdesk_reply_body" rows="6" style="width:100%;"></textarea>
				</p>
				<p>
					<label for="hd-helpdesk-reply-attachment"><?php esc_html_e( 'Attachment', 'wp-helpdesk' ); ?></label><br>
					<input
						type="file"
						id="hd-helpdesk-reply-attachment"
						name="hd_helpdesk_attachment"
						accept="image/jpeg,image/png,image/gif,application/pdf,text/plain,application/zip"
					>
					<span style="font-size:0.875em;"><?php esc_html_e( 'Optional. JPEG, PNG, GIF, PDF, TXT, ZIP. Max 10 MB.', 'wp-helpdesk' ); ?></span>
				</p>
				<p><button type="submit"><?php esc_html_e( 'Send reply', 'wp-helpdesk' ); ?></button></p>
			</form>
		</div>
		<?php
	}

	/**
	 * Render a basic requests table.
	 *
	 * @param array<int, array<string, mixed>> $tickets Tickets to display.
	 * @return void
	 */
	protected function renderRequestsTable( array $tickets ): void {
		?>
		<table class="shop_table shop_table_responsive my_account_orders widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Request', 'wp-helpdesk' ); ?></th>
					<th><?php esc_html_e( 'Subject', 'wp-helpdesk' ); ?></th>
					<th><?php esc_html_e( 'Status', 'wp-helpdesk' ); ?></th>
					<th><?php esc_html_e( 'Updated', 'wp-helpdesk' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $tickets as $ticket ) : ?>
					<tr>
						<td><a href="<?php echo esc_url( $this->buildAccountUrl( 'request/' . rawurlencode( (string) $ticket['ticket_no'] ) ) ); ?>"><?php echo esc_html( (string) $ticket['ticket_no'] ); ?></a></td>
						<td><?php echo esc_html( (string) $ticket['subject'] ); ?></td>
						<td><?php echo esc_html( (string) $ticket['status'] ); ?></td>
						<td><?php echo esc_html( (string) ( $ticket['updated_at'] ?? $ticket['created_at'] ?? '' ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Render a simple message block with optional CTA.
	 *
	 * @param string      $title   Block title.
	 * @param string      $body    Block copy.
	 * @param string      $type    notice|error.
	 * @param string|null $cta_url Optional CTA URL.
	 * @param string|null $cta     Optional CTA label.
	 * @return void
	 */
	protected function renderMessageBlock( string $title, string $body, string $type = 'notice', ?string $cta_url = null, ?string $cta = null ): void {
		?>
		<div class="hd-account-helpdesk__section hd-account-helpdesk__section--<?php echo esc_attr( $type ); ?>">
			<h3><?php echo esc_html( $title ); ?></h3>
			<p><?php echo esc_html( $body ); ?></p>
			<?php if ( null !== $cta_url && null !== $cta ) : ?>
				<p><a href="<?php echo esc_url( $cta_url ); ?>"><?php echo esc_html( $cta ); ?></a></p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render a pending notice.
	 *
	 * @return void
	 */
	protected function renderNotice(): void {
		if ( null === $this->notice ) {
			return;
		}

		?>
		<div class="hd-account-helpdesk__notice hd-account-helpdesk__notice--<?php echo esc_attr( $this->notice['type'] ); ?>">
			<p><?php echo esc_html( $this->notice['message'] ); ?></p>
		</div>
		<?php
	}

	/**
	 * Handle member reply submission on the request detail view.
	 *
	 * @param string $ticket_no Ticket number.
	 * @return bool True when a redirect was issued.
	 */
	protected function handleReplySubmission( string $ticket_no ): bool {
		$action = sanitize_key( (string) ( $_POST['hd_helpdesk_action'] ?? '' ) );
		if ( 'reply' !== $action ) {
			return false;
		}

		$nonce = sanitize_text_field( (string) ( $_POST['hd_my_account_reply_nonce'] ?? '' ) );
		if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'hd_my_account_reply' ) ) {
			$this->notice = array(
				'type'    => 'error',
				'message' => __( 'Security check failed.', 'wp-helpdesk' ),
			);
			return false;
		}

		$ticket = $this->getAccessibleTicket( $ticket_no );
		if ( null === $ticket ) {
			$this->notice = array(
				'type'    => 'error',
				'message' => __( 'That request could not be found or you do not have permission to reply to it.', 'wp-helpdesk' ),
			);
			return false;
		}

		$body = sanitize_textarea_field( (string) ( $_POST['hd_helpdesk_reply_body'] ?? '' ) );
		if ( '' === trim( $body ) ) {
			$this->notice = array(
				'type'    => 'error',
				'message' => __( 'Please enter a reply before sending.', 'wp-helpdesk' ),
			);
			return false;
		}

		$message_id = $this->message_service->postReply(
			(int) $ticket['id'],
			$body,
			'member',
			get_current_user_id(),
			false
		);

		if ( $message_id <= 0 ) {
			$this->notice = array(
				'type'    => 'error',
				'message' => __( 'Your reply could not be sent. Please try again.', 'wp-helpdesk' ),
			);
			return false;
		}

		// Handle optional file attachment on the reply.
		$this->maybeUploadReplyAttachment( (int) $ticket['id'], $message_id );

		$message = $this->message_service->getMessage( $message_id );
		do_action(
			'hd_ticket_replied',
			$ticket,
			$message ?: array(
				'id'             => $message_id,
				'ticket_id'      => (int) $ticket['id'],
				'author_user_id' => get_current_user_id(),
				'author_type'    => 'member',
				'body'           => $body,
				'is_internal'    => 0,
			)
		);

		return $this->redirectTo( $this->buildAccountUrl( 'request/' . $ticket_no ) );
	}

	/**
	 * Upload a reply attachment from $_FILES if one was submitted.
	 *
	 * Looks for a file under the 'hd_helpdesk_attachment' key. Silently skips
	 * when no file is present. Errors are not fatal to the reply submission.
	 *
	 * @param int      $ticket_id  Ticket ID.
	 * @param int|null $message_id Message ID, or null.
	 * @return \WP_Error|null WP_Error on failure, null on success or no file.
	 */
	protected function maybeUploadReplyAttachment( int $ticket_id, ?int $message_id ): ?\WP_Error {
		if ( ! empty( $_FILES['hd_helpdesk_attachment'] ) && ! empty( $_FILES['hd_helpdesk_attachment']['name'] ) ) {
			$result = $this->attachment_service->handleUpload(
				$_FILES['hd_helpdesk_attachment'],
				$ticket_id,
				$message_id,
				get_current_user_id()
			);
			if ( $result instanceof \WP_Error ) {
				return $result;
			}
		}
		return null;
	}

	/**
	 * Resolve navigation links for the current view.
	 *
	 * @param string $active Active nav key.
	 * @return array<int, array{label:string,url:string,active:bool}>
	 */
	protected function getNavigationLinks( string $active ): array {
		return array(
			array(
				'label'  => __( 'Overview', 'wp-helpdesk' ),
				'url'    => $this->buildAccountUrl(),
				'active' => 'overview' === $active,
			),
			array(
				'label'  => __( 'New request', 'wp-helpdesk' ),
				'url'    => $this->buildAccountUrl( 'new' ),
				'active' => 'new' === $active,
			),
			array(
				'label'  => __( 'My requests', 'wp-helpdesk' ),
				'url'    => $this->buildAccountUrl( 'requests' ),
				'active' => 'requests' === $active,
			),
		);
	}

	/**
	 * Parse the endpoint payload into a supported subview.
	 *
	 * @return array{view:string,ticket_no:string}
	 */
	protected function parseEndpointRequest(): array {
		$value = trim( (string) get_query_var( self::ENDPOINT, '' ), '/' );

		if ( 'new' === $value ) {
			return array(
				'view'      => 'new',
				'ticket_no' => '',
			);
		}

		if ( 'requests' === $value ) {
			return array(
				'view'      => 'requests',
				'ticket_no' => '',
			);
		}

		if ( 0 === strpos( $value, 'request/' ) ) {
			return array(
				'view'      => 'request',
				'ticket_no' => sanitize_text_field( rawurldecode( substr( $value, strlen( 'request/' ) ) ) ),
			);
		}

		return array(
			'view'      => 'overview',
			'ticket_no' => '',
		);
	}

	/**
	 * List the current member's tickets.
	 *
	 * @param int $per_page Page size.
	 * @return array<int, array<string, mixed>>
	 */
	protected function listOwnedTickets( int $per_page ): array {
		$user = wp_get_current_user();

		return $this->ticket_repository->listForUser(
			Helpers::getNetworkId(),
			get_current_user_id(),
			(string) ( $user->user_email ?? '' ),
			array(
				'per_page' => $per_page,
			)
		);
	}

	/**
	 * Retrieve an accessible ticket by ticket number.
	 *
	 * @param string $ticket_no Ticket number.
	 * @return array<string, mixed>|null
	 */
	protected function getAccessibleTicket( string $ticket_no ): ?array {
		$ticket = $this->ticket_repository->findByTicketNo( $ticket_no, Helpers::getNetworkId() );
		if ( null === $ticket ) {
			return null;
		}

		if ( current_user_can( 'hd_manage_tickets' ) ) {
			return $ticket;
		}

		$user       = wp_get_current_user();
		$current_id = get_current_user_id();
		$user_id    = isset( $ticket['user_id'] ) ? (int) $ticket['user_id'] : 0;
		$email      = sanitize_email( (string) ( $user->user_email ?? '' ) );

		if ( $user_id > 0 ) {
			return $user_id === $current_id ? $ticket : null;
		}

		return sanitize_email( (string) ( $ticket['requester_email'] ?? '' ) ) === $email ? $ticket : null;
	}

	/**
	 * Build a My Account endpoint URL for a subview.
	 *
	 * @param string $subpath Optional endpoint value.
	 * @return string
	 */
	protected function buildAccountUrl( string $subpath = '' ): string {
		$value = trim( $subpath, '/' );
		$account_page = $this->getAccountPageUrl();

		if ( '' !== $account_page && function_exists( 'wc_get_endpoint_url' ) ) {
			$base         = function_exists( 'wc_get_account_endpoint_url' )
				? wc_get_account_endpoint_url( self::ENDPOINT )
				: rtrim( $account_page, '/' ) . '/' . self::ENDPOINT . '/';

			if ( '' === $value ) {
				return $base;
			}

			if ( 0 === strpos( $value, 'request/' ) ) {
				$ticket_no = substr( $value, strlen( 'request/' ) );

				if ( false !== strpos( $account_page, '?' ) ) {
					return $this->buildNonPrettyAccountUrl( 'request/' . $ticket_no );
				}

				return trailingslashit( $base ) . 'request/' . rawurlencode( $ticket_no ) . '/';
			}

			return wc_get_endpoint_url( self::ENDPOINT, $value, $account_page );
		}

		if ( function_exists( 'wc_get_account_endpoint_url' ) && '' === $value ) {
			return wc_get_account_endpoint_url( self::ENDPOINT );
		}

		$base = function_exists( 'wc_get_account_endpoint_url' )
			? wc_get_account_endpoint_url( self::ENDPOINT )
			: home_url( '/my-account/helpdesk/' );

		if ( '' === $value ) {
			return $base;
		}

		if ( false !== strpos( $base, '?' ) ) {
			return $this->buildNonPrettyAccountUrl( $value );
		}

		return trailingslashit( $base ) . $value . '/';
	}

	/**
	 * Whether WooCommerce account pages appear to be available.
	 *
	 * Checks that WooCommerce core is loaded (class_exists guard) before
	 * attempting any permalink resolution that depends on rewrite context.
	 *
	 * @return bool
	 */
	protected function isWooCommerceAvailable(): bool {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return false;
		}

		return '' !== $this->getAccountPageUrl();
	}

	/**
	 * Resolve the WooCommerce My Account page permalink when it is available.
	 *
	 * Guards against a PHP fatal (Call to a member function get_page_permastruct()
	 * on null) that occurs when WordPress's global $wp_rewrite object has not yet
	 * been initialised — e.g. during plugins_loaded, WP-CLI, cron, or REST-only
	 * requests where the normal rewrite boot sequence is skipped.
	 *
	 * @return string
	 */
	protected function getAccountPageUrl(): string {
		if ( ! function_exists( 'wc_get_page_permalink' ) ) {
			return '';
		}

		// Bail early when the rewrite context is not yet initialised to prevent
		// get_page_permastruct() from being called on a null $wp_rewrite object.
		global $wp_rewrite;
		if ( null === $wp_rewrite ) {
			return '';
		}

		$url = wc_get_page_permalink( 'myaccount' );

		return is_string( $url ) ? trim( $url ) : '';
	}

	/**
	 * Convert a full URL to a display path.
	 *
	 * @param string $url Full URL.
	 * @return string
	 */
	protected function extractPath( string $url ): string {
		$path  = (string) parse_url( $url, PHP_URL_PATH );
		$query = (string) parse_url( $url, PHP_URL_QUERY );

		$display = '' !== $query ? $path . '?' . $query : $path;

		return rawurldecode( $display );
	}

	/**
	 * Build a non-pretty account URL fallback.
	 *
	 * @param string $value Endpoint value.
	 * @return string
	 */
	protected function buildNonPrettyAccountUrl( string $value ): string {
		$account_page = $this->getAccountPageUrl();
		if ( '' === $account_page ) {
			$account_page = home_url( '/my-account/' );
		}
		$separator    = false !== strpos( $account_page, '?' ) ? '&' : '?';

		return $account_page . $separator . self::ENDPOINT . '=' . rawurlencode( $value );
	}

	/**
	 * Redirect to the given URL.
	 *
	 * @param string $url Redirect target.
	 * @return bool
	 */
	protected function redirectTo( string $url ): bool {
		wp_safe_redirect( $url );

		if ( defined( 'WP_HELPDESK_TESTING' ) && WP_HELPDESK_TESTING ) {
			return true;
		}

		exit;
	}

	/**
	 * Present a readable author label.
	 *
	 * @param string $author_type Stored author type.
	 * @return string
	 */
	protected function formatAuthorType( string $author_type ): string {
		$map = array(
			'member' => __( 'You', 'wp-helpdesk' ),
			'agent'  => __( 'Support', 'wp-helpdesk' ),
			'system' => __( 'System', 'wp-helpdesk' ),
			'guest'  => __( 'Guest', 'wp-helpdesk' ),
		);

		return $map[ $author_type ] ?? ucfirst( $author_type );
	}
}
