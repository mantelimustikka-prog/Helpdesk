<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use WPHelpdesk\Domain\Attachment\AttachmentService;
use WPHelpdesk\Interfaces\Admin\Pages\TicketsPage;
use WPHelpdesk\Interfaces\Frontend\GuestTicketView;
use WPHelpdesk\Interfaces\Rest\PublicTicketController;
use WPHelpdesk\Support\Constants;

require_once __DIR__ . '/bootstrap.php';

/**
 * Tests that attachment upload is wired up correctly for both user and admin
 * flows, and that ticket detail views render attachments.
 */
final class AttachmentUploadAndDisplayTest extends TestCase {

	protected function setUp(): void {
		wp_helpdesk_test_reset_state();
	}

	// -------------------------------------------------------------------------
	// Public ticket creation response includes ticket_id
	// -------------------------------------------------------------------------

	public function testGuestTicketCreationResponseIncludesTicketId(): void {
		$GLOBALS['wp_site_options'][ Constants::OPTION_GENERAL_REQUIRE_TOPIC ] = 0;

		global $wpdb;
		$call_count = 0;
		$wpdb       = new class ( $call_count ) {
			public string $base_prefix = 'wp_';
			public string $sitemeta    = 'wp_sitemeta';
			public int    $insert_id   = 77;
			private int   $calls;

			public function __construct( int &$calls ) {
				$this->calls = &$calls;
			}

			public function prepare( string $query, ...$args ): string {
				return $query;
			}
			public function query( string $query ): int {
				return 1;
			}
			public function get_var( string $query ) {
				return 2001;
			}
			public function insert( string $table, array $data, array $format = array() ): int {
				++$this->calls;
				// First insert = ticket (id 77), second = message (id 78).
				$this->insert_id = ( 1 === $this->calls ) ? 77 : 78;
				return 1;
			}
			public function get_row( string $query, string $output = OBJECT ) {
				return null;
			}
		};

		$controller = new class extends PublicTicketController {
			/** @param array<string, mixed> $data */
			public function exposeCreateTicket( array $data ) {
				return $this->createTicket( $data );
			}
		};

		$response = $controller->exposeCreateTicket( array(
			'topic_id'        => 0,
			'requester_name'  => 'Alice',
			'requester_email' => 'alice@example.com',
			'requester_phone' => '123456789',
			'subject'         => 'Test subject',
			'message'         => 'Test message',
			'user_id'         => null,
			'form_type'       => 'guest',
			'topic_path'      => array(),
			'session_token'   => '',
			'order_relation'  => 'not_any_existing_order_related',
		) );

		self::assertInstanceOf( WP_REST_Response::class, $response );
		self::assertIsArray( $response->data );
		self::assertArrayHasKey( 'ticket_id', $response->data, 'Response must include ticket_id so the frontend can upload attachments' );
		self::assertArrayHasKey( 'ticket_no', $response->data );
	}

	public function testMemberTicketCreationResponseIncludesTicketId(): void {
		$GLOBALS['wp_site_options'][ Constants::OPTION_GENERAL_REQUIRE_TOPIC ] = 0;

		global $wpdb;
		$call_count = 0;
		$wpdb       = new class ( $call_count ) {
			public string $base_prefix = 'wp_';
			public string $sitemeta    = 'wp_sitemeta';
			public int    $insert_id   = 55;
			private int   $calls;

			public function __construct( int &$calls ) {
				$this->calls = &$calls;
			}

			public function prepare( string $query, ...$args ): string {
				return $query;
			}
			public function query( string $query ): int {
				return 1;
			}
			public function get_var( string $query ) {
				return 2001;
			}
			public function insert( string $table, array $data, array $format = array() ): int {
				++$this->calls;
				$this->insert_id = ( 1 === $this->calls ) ? 55 : 56;
				return 1;
			}
			public function get_row( string $query, string $output = OBJECT ) {
				return null;
			}
		};

		$controller = new class extends PublicTicketController {
			/** @param array<string, mixed> $data */
			public function exposeCreateTicket( array $data ) {
				return $this->createTicket( $data );
			}
		};

		$response = $controller->exposeCreateTicket( array(
			'topic_id'        => 0,
			'requester_name'  => 'Bob',
			'requester_email' => 'bob@example.com',
			'requester_phone' => '987654321',
			'subject'         => 'Member ticket',
			'message'         => 'Member message',
			'user_id'         => 42,
			'form_type'       => 'member',
			'topic_path'      => array(),
			'session_token'   => '',
			'order_relation'  => 'not_any_existing_order_related',
		) );

		self::assertInstanceOf( WP_REST_Response::class, $response );
		self::assertIsArray( $response->data );
		self::assertArrayHasKey( 'ticket_id', $response->data, 'Member ticket response must include ticket_id' );
	}

	// -------------------------------------------------------------------------
	// Admin TicketsPage reply form includes a file input and multipart enctype
	// -------------------------------------------------------------------------

	public function testAdminTicketsPageReplyFormIncludesFileInput(): void {
		global $wpdb;
		$call = 0;
		$wpdb = new class ( $call ) {
			public string $base_prefix = 'wp_';
			public string $sitemeta    = 'wp_sitemeta';
			public int    $insert_id   = 0;
			private int   $calls;

			public function __construct( int &$calls ) {
				$this->calls = &$calls;
			}

			public function prepare( string $query, ...$args ): string {
				return $query;
			}
			public function query( string $query ): int {
				return 1;
			}
			public function get_var( string $query ) {
				return 2001;
			}
			public function get_results( string $query, string $output = OBJECT ): array {
				++$this->calls;
				// First call = listTickets, second call = getMessages (return empty).
				if ( $this->calls === 1 ) {
					return array(
						array(
							'id'              => 10,
							'ticket_no'       => 'HD-0001',
							'subject'         => 'My issue',
							'requester_name'  => 'Bob',
							'requester_email' => 'bob@example.com',
							'requester_phone' => '987654321',
							'status'          => 'new',
							'updated_at'      => '2025-01-01 00:00:00',
							'order_relation'  => 'not_any_existing_order_related',
						),
					);
				}
				return array();
			}
			public function get_row( string $query, string $output = OBJECT ) {
				return array(
					'id'              => 10,
					'ticket_no'       => 'HD-0001',
					'subject'         => 'My issue',
					'requester_name'  => 'Bob',
					'requester_email' => 'bob@example.com',
					'requester_phone' => '987654321',
					'status'          => 'new',
					'network_id'      => 2001,
					'updated_at'      => '2025-01-01 00:00:00',
					'order_relation'  => 'not_any_existing_order_related',
				);
			}
		};

		$_GET['ticket_id'] = 10;

		$stub_attachment_service = new class extends AttachmentService {
			public function getForTicket( int $ticket_id ): array {
				return array();
			}
		};

		ob_start();
		( new TicketsPage( $stub_attachment_service ) )->render();
		$output = (string) ob_get_clean();

		unset( $_GET['ticket_id'] );

		self::assertStringContainsString( 'type="file"', $output, 'Admin reply form must include a file input' );
		self::assertStringContainsString( 'hd_attachment', $output, 'File input name must be hd_attachment' );
		self::assertStringContainsString( 'multipart/form-data', $output, 'Reply form must use multipart/form-data encoding' );
	}

	// -------------------------------------------------------------------------
	// Admin TicketsPage renders attachments when they exist
	// -------------------------------------------------------------------------

	public function testAdminTicketsPageRendersAttachmentsForSelectedTicket(): void {
		global $wpdb;
		$call2 = 0;
		$wpdb  = new class ( $call2 ) {
			public string $base_prefix = 'wp_';
			public string $sitemeta    = 'wp_sitemeta';
			public int    $insert_id   = 0;
			private int   $calls;

			public function __construct( int &$calls ) {
				$this->calls = &$calls;
			}

			public function prepare( string $query, ...$args ): string {
				return $query;
			}
			public function query( string $query ): int {
				return 1;
			}
			public function get_var( string $query ) {
				return 2001;
			}
			public function get_results( string $query, string $output = OBJECT ): array {
				++$this->calls;
				if ( $this->calls === 1 ) {
					return array(
						array(
							'id'              => 5,
							'ticket_no'       => 'HD-0002',
							'subject'         => 'Another issue',
							'requester_name'  => 'Carol',
							'requester_email' => 'carol@example.com',
							'requester_phone' => '111222333',
							'status'          => 'new',
							'updated_at'      => '2025-01-02 00:00:00',
							'order_relation'  => 'not_any_existing_order_related',
						),
					);
				}
				return array();
			}
			public function get_row( string $query, string $output = OBJECT ) {
				return array(
					'id'              => 5,
					'ticket_no'       => 'HD-0002',
					'subject'         => 'Another issue',
					'requester_name'  => 'Carol',
					'requester_email' => 'carol@example.com',
					'requester_phone' => '111222333',
					'status'          => 'new',
					'network_id'      => 2001,
					'updated_at'      => '2025-01-02 00:00:00',
					'order_relation'  => 'not_any_existing_order_related',
				);
			}
		};

		$_GET['ticket_id'] = 5;

		$stub_attachment_service = new class extends AttachmentService {
			public function getForTicket( int $ticket_id ): array {
				return array(
					array(
						'id'               => 1,
						'ticket_id'        => 5,
						'message_id'       => null,
						'wp_attachment_id' => 99,
						'mime_type'        => 'application/pdf',
						'file_size'        => 1024,
						'file_name'        => 'invoice.pdf',
						'created_at'       => '2025-01-02 00:00:00',
						'url'              => 'https://example.com/uploads/invoice.pdf',
					),
				);
			}
		};

		ob_start();
		( new TicketsPage( $stub_attachment_service ) )->render();
		$output = (string) ob_get_clean();

		unset( $_GET['ticket_id'] );

		self::assertStringContainsString( 'hd-attachments', $output, 'Attachment section must be rendered' );
		self::assertStringContainsString( 'invoice.pdf', $output, 'Attachment filename must appear' );
	}

	// -------------------------------------------------------------------------
	// GuestTicketView renders image attachments
	// -------------------------------------------------------------------------

	public function testGuestTicketViewRendersImageAttachment(): void {
		global $wpdb;
		$wpdb = new class {
			public string $base_prefix = 'wp_';
			public string $sitemeta    = 'wp_sitemeta';

			public function prepare( string $query, ...$args ): string {
				return $query;
			}
			public function query( string $query ): int {
				return 1;
			}
			public function get_var( string $query ) {
				return 2001;
			}
			public function get_row( string $query, string $output = OBJECT ) {
				return array(
					'id'               => 3,
					'ticket_no'        => 'HD-0003',
					'subject'          => 'Image issue',
					'status'           => 'new',
					'guest_token_hash' => hash( 'sha256', 'secret-token' ),
					'network_id'       => 2001,
				);
			}
			public function get_results( string $query, string $output = OBJECT ): array {
				return array();
			}
		};

		$stub_attachment_service = new class extends AttachmentService {
			public function getForTicket( int $ticket_id ): array {
				return array(
					array(
						'id'               => 2,
						'ticket_id'        => 3,
						'message_id'       => null,
						'wp_attachment_id' => 55,
						'mime_type'        => 'image/jpeg',
						'file_size'        => 2048,
						'file_name'        => 'screenshot.jpg',
						'created_at'       => '2025-01-03 00:00:00',
						'url'              => 'https://example.com/uploads/screenshot.jpg',
					),
				);
			}
		};

		ob_start();
		( new GuestTicketView( $stub_attachment_service ) )->renderForTicket( 'HD-0003', 'secret-token' );
		$output = (string) ob_get_clean();

		self::assertStringContainsString( 'hd-attachments', $output, 'Attachment section must be rendered' );
		self::assertStringContainsString( 'hd-attachment--image', $output, 'Image attachment must use image class' );
		self::assertStringContainsString( 'screenshot.jpg', $output, 'Image filename must appear' );
		self::assertStringContainsString( 'hd-attachment__thumb', $output, 'Image must render as thumbnail' );
	}

	// -------------------------------------------------------------------------
	// GuestTicketView renders document attachments with download link
	// -------------------------------------------------------------------------

	public function testGuestTicketViewRendersDocumentAttachment(): void {
		global $wpdb;
		$wpdb = new class {
			public string $base_prefix = 'wp_';
			public string $sitemeta    = 'wp_sitemeta';

			public function prepare( string $query, ...$args ): string {
				return $query;
			}
			public function query( string $query ): int {
				return 1;
			}
			public function get_var( string $query ) {
				return 2001;
			}
			public function get_row( string $query, string $output = OBJECT ) {
				return array(
					'id'               => 4,
					'ticket_no'        => 'HD-0004',
					'subject'          => 'Doc issue',
					'status'           => 'new',
					'guest_token_hash' => hash( 'sha256', 'doc-token' ),
					'network_id'       => 2001,
				);
			}
			public function get_results( string $query, string $output = OBJECT ): array {
				return array();
			}
		};

		$stub_attachment_service = new class extends AttachmentService {
			public function getForTicket( int $ticket_id ): array {
				return array(
					array(
						'id'               => 3,
						'ticket_id'        => 4,
						'message_id'       => null,
						'wp_attachment_id' => 66,
						'mime_type'        => 'application/pdf',
						'file_size'        => 4096,
						'file_name'        => 'report.pdf',
						'created_at'       => '2025-01-04 00:00:00',
						'url'              => 'https://example.com/uploads/report.pdf',
					),
				);
			}
		};

		ob_start();
		( new GuestTicketView( $stub_attachment_service ) )->renderForTicket( 'HD-0004', 'doc-token' );
		$output = (string) ob_get_clean();

		self::assertStringContainsString( 'hd-attachment--document', $output, 'Document attachment must use document class' );
		self::assertStringContainsString( 'report.pdf', $output, 'Document filename must appear' );
		self::assertStringContainsString( 'download=', $output, 'Document must have a download link' );
	}

	// -------------------------------------------------------------------------
	// Public attachment endpoint denies wrong guest_token
	// -------------------------------------------------------------------------

	public function testPublicUploadAttachmentDeniesWrongGuestToken(): void {
		$GLOBALS['wp_valid_nonces']['wp_rest'] = 'valid_nonce';
		$GLOBALS['wp_logged_in']               = false;

		global $wpdb;
		$wpdb = new class {
			public string $base_prefix = 'wp_';
			public string $sitemeta    = 'wp_sitemeta';

			public function prepare( string $query, ...$args ): string {
				return $query;
			}
			public function query( string $query ): int {
				return 1;
			}
			public function get_var( string $query ) {
				return 2001;
			}
			public function get_row( string $query, string $output = OBJECT ) {
				return array(
					'id'               => 9,
					'ticket_no'        => 'HD-0009',
					'user_id'          => 0,
					'guest_token_hash' => hash( 'sha256', 'correct-token' ),
				);
			}
		};

		$controller = new class extends PublicTicketController {
			protected function hashGuestToken( string $token ): string {
				return hash( 'sha256', $token );
			}
		};

		$request = new WP_REST_Request();
		$request->set_header( 'X-WP-Nonce', 'valid_nonce' );
		$request->set_param( 'id', 9 );
		$request->set_param( 'guest_token', 'wrong-token' );

		$result = $controller->uploadAttachment( $request );

		self::assertInstanceOf( WP_Error::class, $result );
		self::assertSame( 'hd_forbidden', $result->get_error_code() );
	}

	// -------------------------------------------------------------------------
	// Admin reply attachment upload: AttachmentService is called
	// -------------------------------------------------------------------------

	public function testAdminReplyWithAttachmentCallsUploadService(): void {
		$upload_calls = array();

		$spy_attachment = new class ( $upload_calls ) extends AttachmentService {
			/** @var array<int, array<string, mixed>> */
			private array $log;

			/** @param array<int, array<string, mixed>> &$log */
			public function __construct( array &$log ) {
				$this->log = &$log;
			}

			/** @param array<string, mixed> $file */
			public function handleUpload( array $file, int $ticket_id, ?int $message_id, int $user_id ) {
				$this->log[] = array(
					'file_name'  => $file['name'],
					'ticket_id'  => $ticket_id,
					'message_id' => $message_id,
				);
				return array( 'id' => 1 );
			}

			public function getForTicket( int $ticket_id ): array {
				return array();
			}
		};

		// Use a test double that exposes the protected helper method.
		$page = new class ( $spy_attachment ) extends TicketsPage {
			public function callMaybeUploadReplyAttachment( int $ticket_id, ?int $message_id ): void {
				$this->maybeUploadReplyAttachment( $ticket_id, $message_id );
			}
		};

		$_FILES['hd_attachment'] = array(
			'name'     => 'doc.pdf',
			'type'     => 'application/pdf',
			'tmp_name' => '/tmp/phpXXXXXX',
			'error'    => UPLOAD_ERR_OK,
			'size'     => 1024,
		);

		$page->callMaybeUploadReplyAttachment( 7, 20 );

		unset( $_FILES['hd_attachment'] );

		self::assertNotEmpty( $upload_calls, 'handleUpload must be called when a file is attached to admin reply' );
		self::assertSame( 7, $upload_calls[0]['ticket_id'] );
		self::assertSame( 20, $upload_calls[0]['message_id'] );
		self::assertSame( 'doc.pdf', $upload_calls[0]['file_name'] );
	}

	// -------------------------------------------------------------------------
	// GuestTicketView reply form includes a file input
	// -------------------------------------------------------------------------

	public function testGuestTicketViewReplyFormIncludesFileInput(): void {
		$view = new class extends GuestTicketView {
			protected function findTicket( string $ticket_no, string $guest_token ): ?array {
				return array(
					'id'        => 12,
					'ticket_no' => $ticket_no,
					'subject'   => 'Test subject',
					'status'    => 'open',
				);
			}

			protected function getMessages( int $ticket_id ): array {
				return array();
			}
		};

		$stub_attachment_service = new class extends AttachmentService {
			public function getForTicket( int $ticket_id ): array {
				return array();
			}
		};

		$view_with_service = new class( $stub_attachment_service ) extends GuestTicketView {
			public function __construct( AttachmentService $attachment_service ) {
				parent::__construct( $attachment_service );
			}

			protected function findTicket( string $ticket_no, string $guest_token ): ?array {
				return array(
					'id'        => 12,
					'ticket_no' => $ticket_no,
					'subject'   => 'Test subject',
					'status'    => 'open',
				);
			}

			protected function getMessages( int $ticket_id ): array {
				return array();
			}
		};

		ob_start();
		$view_with_service->renderForTicket( 'HD-0012', 'sometoken' );
		$output = (string) ob_get_clean();

		self::assertStringContainsString( 'type="file"', $output, 'Guest reply form must include a file input' );
		self::assertStringContainsString( 'hd-guest-reply-attachment', $output, 'File input id must be hd-guest-reply-attachment' );
		self::assertStringContainsString( 'hd-file-input', $output, 'File input must use the hd-file-input class for visibility' );
		self::assertStringContainsString( 'type="submit"', $output, 'Guest reply form must use a real submit button' );
		self::assertStringContainsString( 'data-ticket-id', $output, 'Reply form must carry data-ticket-id for JS upload' );
	}

	// -------------------------------------------------------------------------
	// submitGuestReply REST response includes ticket_id
	// -------------------------------------------------------------------------

	public function testSubmitGuestReplyResponseIncludesTicketId(): void {
		global $wpdb;
		$wpdb = new class {
			public string $base_prefix = 'wp_';
			public string $sitemeta    = 'wp_sitemeta';
			public int    $insert_id   = 99;

			public function prepare( string $query, ...$args ): string {
				return $query;
			}
			public function query( string $query ): int {
				return 1;
			}
			public function get_var( string $query ) {
				return 2001;
			}
			public function insert( string $table, array $data, array $format = array() ): int {
				$this->insert_id = 99;
				return 1;
			}
			public function get_row( string $query, string $output = OBJECT ): ?array {
				return array( 'id' => 99, 'ticket_no' => 'HD-0099' );
			}
		};

		$GLOBALS['wp_valid_nonces']['wp_rest'] = 'valid_nonce';

		$controller = new class extends PublicTicketController {
			public function findTicketByTokenAndNo( string $ticket_no, string $guest_token ): ?array {
				return array( 'id' => 55, 'ticket_no' => $ticket_no, 'status' => 'open' );
			}
		};

		$request = new WP_REST_Request();
		$request->set_header( 'X-WP-Nonce', 'valid_nonce' );
		$request->set_param( 'ticket_no', 'HD-0055' );
		$request->set_param( 'guest_token', 'sometoken' );
		$request->set_param( 'message', 'Hello, I need help.' );

		$response = $controller->submitGuestReply( $request );

		self::assertInstanceOf( WP_REST_Response::class, $response );
		self::assertSame( 201, $response->status );
		self::assertArrayHasKey( 'ticket_id', $response->data, 'submitGuestReply response must include ticket_id for attachment upload' );
		self::assertSame( 55, $response->data['ticket_id'] );
	}
}
