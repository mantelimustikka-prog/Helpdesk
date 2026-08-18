<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use WPHelpdesk\Domain\Notification\NotificationService;
use WPHelpdesk\Support\Constants;

require_once __DIR__ . '/bootstrap.php';

final class NotificationServiceTest extends TestCase {
	protected function setUp(): void {
		wp_helpdesk_test_reset_state();
	}

	public function testHeaderFooterTogglesWrapOutboundMail(): void {
		$GLOBALS['wp_site_options'][ Constants::OPTION_EMAIL_HEADER_HTML ] = '<p>HEADER</p>';
		$GLOBALS['wp_site_options'][ Constants::OPTION_EMAIL_FOOTER_HTML ] = '<p>FOOTER</p>';
		$GLOBALS['wp_site_options'][ Constants::OPTION_EMAIL_HEADER_ENABLED ] = 1;
		$GLOBALS['wp_site_options'][ Constants::OPTION_EMAIL_FOOTER_ENABLED ] = 1;

		$service = new class extends NotificationService {
			protected function renderTemplate( string $template_path, array $vars ): string {
				return '<div>BODY</div>';
			}
		};

		$service->sendTicketCreated( array( 'ticket_no' => 'HD-001000' ), 'customer@example.test' );
		self::assertStringContainsString( '<p>HEADER</p><div>BODY</div><p>FOOTER</p>', $GLOBALS['wp_mail_calls'][0]['message'] );

		$GLOBALS['wp_mail_calls'] = array();
		$GLOBALS['wp_site_options'][ Constants::OPTION_EMAIL_HEADER_ENABLED ] = 0;
		$GLOBALS['wp_site_options'][ Constants::OPTION_EMAIL_FOOTER_ENABLED ] = 1;

		$service->sendTicketCreated( array( 'ticket_no' => 'HD-001001' ), 'customer@example.test' );
		self::assertSame( '<div>BODY</div><p>FOOTER</p>', $GLOBALS['wp_mail_calls'][0]['message'] );
	}
}
