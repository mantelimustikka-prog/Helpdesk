<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use WPHelpdesk\Domain\Attachment\AttachmentService;
use WPHelpdesk\Interfaces\Admin\Pages\TicketsPage;
use WPHelpdesk\Interfaces\Frontend\GuestTicketView;
use WPHelpdesk\Interfaces\Rest\PublicTicketController;

require_once __DIR__ . '/bootstrap.php';

/**
 * Tests for attachment viewer (admin + customer views) and
 * guest ticket follow-up link generation.
 */
final class AttachmentViewerAndGuestLinkTest extends TestCase {

	protected function setUp(): void {
		wp_helpdesk_test_reset_state();
	}

	// -------------------------------------------------------------------------
	// Guest token generation
	// -------------------------------------------------------------------------

	public function testGenerateGuestTokenReturnsSixtyFourCharHexString(): void {
		$controller = new class extends PublicTicketController {
			public function generateGuestTokenPublic(): string {
				return $this->generateGuestToken();
			}
		};

		$token = $controller->generateGuestTokenPublic();
		self::assertSame( 64, strlen( $token ) );
		self::assertRegExp( '/^[0-9a-f]{64}$/', $token );
	}

	public function testGenerateGuestTokenProducesDifferentValuesEachCall(): void {
		$controller = new class extends PublicTicketController {
			public function generateGuestTokenPublic(): string {
				return $this->generateGuestToken();
			}
		};

		$a = $controller->generateGuestTokenPublic();
		$b = $controller->generateGuestTokenPublic();
		self::assertNotSame( $a, $b );
	}

	// -------------------------------------------------------------------------
	// Guest ticket email includes ticket link
	// -------------------------------------------------------------------------

	public function testTicketCreatedEmailIncludesLinkWhenGuestTokenPresent(): void {
		$template = dirname( __DIR__ ) . '/templates/emails/ticket-created.php';

		$ticket = array(
			'ticket_no'      => 'HD-002000',
			'subject'        => 'My widget broke',
			'requester_name' => 'Jane Guest',
			'guest_token'    => 'abc123def456abc123def456abc123def456abc123def456abc123def456ab12',
		);

		ob_start();
		$vars = array( 'ticket' => $ticket );
		include $template;
		$output = (string) ob_get_clean();

		self::assertStringContainsString( 'View your ticket', $output );
		self::assertStringContainsString( 'HD-002000', $output );
		self::assertStringContainsString( $ticket['guest_token'], $output );
		self::assertStringContainsString( '/helpdesk/ticket/', $output );
	}

	public function testTicketCreatedEmailOmitsLinkWhenNoGuestToken(): void {
		$template = dirname( __DIR__ ) . '/templates/emails/ticket-created.php';

		$ticket = array(
			'ticket_no'      => 'HD-003000',
			'subject'        => 'Logged-in request',
			'requester_name' => 'Bob Member',
		);

		ob_start();
		$vars = array( 'ticket' => $ticket );
		include $template;
		$output = (string) ob_get_clean();

		self::assertStringNotContainsString( '/helpdesk/ticket/', $output );
		self::assertStringContainsString( 'Please log in', $output );
	}

	// -------------------------------------------------------------------------
	// findTicketByTokenAndNo (no real DB – returns null gracefully)
	// -------------------------------------------------------------------------

	public function testFindTicketByTokenAndNoReturnNullWhenBlank(): void {
		$controller = new PublicTicketController();
		self::assertNull( $controller->findTicketByTokenAndNo( '', 'sometoken' ) );
		self::assertNull( $controller->findTicketByTokenAndNo( 'HD-001', '' ) );
		self::assertNull( $controller->findTicketByTokenAndNo( '', '' ) );
	}

	// -------------------------------------------------------------------------
	// GuestTicketView::renderAttachments
	// -------------------------------------------------------------------------

	public function testRenderAttachmentsOutputsImageThumbnailWithLightboxButton(): void {
		$view        = new GuestTicketView();
		$attachments = array(
			array(
				'mime_type'  => 'image/jpeg',
				'url'        => 'https://example.test/photo.jpg',
				'file_name'  => 'photo.jpg',
				'file_size'  => 102400,
				'created_at' => '2026-01-01 10:00:00',
			),
		);

		ob_start();
		$view->renderAttachments( $attachments );
		$output = (string) ob_get_clean();

		self::assertStringContainsString( 'hd-attachment--image', $output );
		self::assertStringContainsString( 'hd-attachment__thumb-btn', $output );
		self::assertStringContainsString( 'data-lightbox-src', $output );
		self::assertStringContainsString( 'photo.jpg', $output );
	}

	public function testRenderAttachmentsOutputsDocumentWithOpenAndDownloadLinks(): void {
		$view        = new GuestTicketView();
		$attachments = array(
			array(
				'mime_type'  => 'application/pdf',
				'url'        => 'https://example.test/report.pdf',
				'file_name'  => 'report.pdf',
				'file_size'  => 204800,
				'created_at' => '2026-01-01 11:00:00',
			),
		);

		ob_start();
		$view->renderAttachments( $attachments );
		$output = (string) ob_get_clean();

		self::assertStringContainsString( 'hd-attachment--document', $output );
		self::assertStringContainsString( 'Open', $output );
		self::assertStringContainsString( 'Download', $output );
		self::assertStringContainsString( 'report.pdf', $output );
	}

	public function testRenderAttachmentsOutputsNothingForEmptyList(): void {
		$view = new GuestTicketView();

		ob_start();
		$view->renderAttachments( array() );
		$output = (string) ob_get_clean();

		self::assertSame( '', $output );
	}

	// -------------------------------------------------------------------------
	// Admin TicketsPage::renderAttachments
	// -------------------------------------------------------------------------

	public function testAdminRenderAttachmentsOutputsImageThumbnail(): void {
		$page        = new TicketsPage();
		$attachments = array(
			array(
				'mime_type'  => 'image/png',
				'url'        => 'https://example.test/screenshot.png',
				'file_name'  => 'screenshot.png',
				'file_size'  => 51200,
				'created_at' => '2026-01-02 09:00:00',
			),
		);

		ob_start();
		$page->renderAttachments( $attachments );
		$output = (string) ob_get_clean();

		self::assertStringContainsString( 'hd-attachment--image', $output );
		self::assertStringContainsString( 'hd-attachment__thumb-btn', $output );
		self::assertStringContainsString( 'data-lightbox-src', $output );
		self::assertStringContainsString( 'screenshot.png', $output );
	}

	public function testAdminRenderAttachmentsOutputsDocumentWithOpenAndDownload(): void {
		$page        = new TicketsPage();
		$attachments = array(
			array(
				'mime_type'  => 'application/pdf',
				'url'        => 'https://example.test/invoice.pdf',
				'file_name'  => 'invoice.pdf',
				'file_size'  => 30000,
				'created_at' => '2026-01-02 10:00:00',
			),
		);

		ob_start();
		$page->renderAttachments( $attachments );
		$output = (string) ob_get_clean();

		self::assertStringContainsString( 'hd-attachment--document', $output );
		self::assertStringContainsString( 'Open', $output );
		self::assertStringContainsString( 'Download', $output );
		self::assertStringContainsString( 'invoice.pdf', $output );
	}

	public function testAdminRenderAttachmentsOutputsNothingForEmptyList(): void {
		$page = new TicketsPage();

		ob_start();
		$page->renderAttachments( array() );
		$output = (string) ob_get_clean();

		self::assertSame( '', $output );
	}

	// -------------------------------------------------------------------------
	// GuestTicketView renders "not found" page for empty credentials
	// -------------------------------------------------------------------------

	public function testGuestTicketViewRendersNotFoundForBlankToken(): void {
		$view = new class extends GuestTicketView {
			protected function findTicket( string $ticket_no, string $guest_token ): ?array {
				return null;
			}
		};

		ob_start();
		$view->renderForTicket( 'HD-999', '' );
		$output = (string) ob_get_clean();

		self::assertStringContainsString( 'not found', strtolower( $output ) );
	}

	public function testGuestTicketViewRendersThreadAndAttachments(): void {
		$attachments = array(
			array(
				'mime_type'  => 'image/jpeg',
				'url'        => 'https://example.test/photo.jpg',
				'file_name'  => 'photo.jpg',
				'file_size'  => 1024,
				'created_at' => '2026-01-03 08:00:00',
			),
		);

		$spy_service = new class( $attachments ) extends AttachmentService {
			private array $items;

			public function __construct( array $items ) {
				$this->items = $items;
			}

			public function getForTicket( int $ticket_id ): array {
				return $this->items;
			}
		};

		$view = new class( $spy_service ) extends GuestTicketView {
			public function __construct( AttachmentService $attachment_service ) {
				parent::__construct( $attachment_service );
			}

			protected function findTicket( string $ticket_no, string $guest_token ): ?array {
				return array(
					'id'          => 7,
					'ticket_no'   => $ticket_no,
					'subject'     => 'Widget broke',
					'status'      => 'new',
					'guest_token' => $guest_token,
				);
			}

			protected function getMessages( int $ticket_id ): array {
				return array(
					array(
						'author_type' => 'guest',
						'created_at'  => '2026-01-03 08:00:00',
						'body'        => 'Hello, I need help.',
						'is_internal' => 0,
					),
				);
			}
		};

		ob_start();
		$view->renderForTicket( 'HD-007', 'validtoken' );
		$output = (string) ob_get_clean();

		self::assertStringContainsString( 'HD-007', $output );
		self::assertStringContainsString( 'Widget broke', $output );
		self::assertStringContainsString( 'Hello, I need help.', $output );
		self::assertStringContainsString( 'photo.jpg', $output );
		self::assertStringContainsString( 'hd-attachment__thumb-btn', $output );
		self::assertStringContainsString( 'hd-guest-reply-form', $output );
	}
}
