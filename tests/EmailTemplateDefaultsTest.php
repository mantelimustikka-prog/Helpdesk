<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use WPHelpdesk\Domain\Notification\EmailTemplateDefaults;
use WPHelpdesk\Support\Constants;

require_once __DIR__ . '/bootstrap.php';

final class EmailTemplateDefaultsTest extends TestCase {
	protected function setUp(): void {
		wp_helpdesk_test_reset_state();
	}

	public function testSubjectsMapCoversAllFourTemplates(): void {
		$subjects = EmailTemplateDefaults::subjects();

		self::assertArrayHasKey( Constants::OPTION_EMAIL_TEMPLATE_TICKET_CREATED_SUBJECT, $subjects );
		self::assertArrayHasKey( Constants::OPTION_EMAIL_TEMPLATE_TICKET_REPLY_SUBJECT, $subjects );
		self::assertArrayHasKey( Constants::OPTION_EMAIL_TEMPLATE_STATUS_CHANGED_SUBJECT, $subjects );
		self::assertArrayHasKey( Constants::OPTION_EMAIL_TEMPLATE_TICKET_CREATED_ADMIN_SUBJECT, $subjects );

		foreach ( $subjects as $key => $value ) {
			self::assertNotEmpty( $value, "Subject for $key should not be empty" );
		}
	}

	public function testBodiesMapCoversAllFourTemplates(): void {
		$bodies = EmailTemplateDefaults::bodies();

		self::assertArrayHasKey( Constants::OPTION_EMAIL_TEMPLATE_TICKET_CREATED_BODY, $bodies );
		self::assertArrayHasKey( Constants::OPTION_EMAIL_TEMPLATE_TICKET_REPLY_BODY, $bodies );
		self::assertArrayHasKey( Constants::OPTION_EMAIL_TEMPLATE_STATUS_CHANGED_BODY, $bodies );
		self::assertArrayHasKey( Constants::OPTION_EMAIL_TEMPLATE_TICKET_CREATED_ADMIN_BODY, $bodies );

		foreach ( $bodies as $key => $value ) {
			self::assertNotEmpty( $value, "Body for $key should not be empty" );
		}
	}

	public function testVariablesListIsNonEmpty(): void {
		$variables = EmailTemplateDefaults::variables();

		self::assertNotEmpty( $variables );
		self::assertArrayHasKey( '{ticket_no}', $variables );
		self::assertArrayHasKey( '{ticket_subject}', $variables );
		self::assertArrayHasKey( '{ticket_status}', $variables );
		self::assertArrayHasKey( '{ticket_link}', $variables );
		self::assertArrayHasKey( '{requester_name}', $variables );
		self::assertArrayHasKey( '{requester_email}', $variables );
		self::assertArrayHasKey( '{message_body}', $variables );
		self::assertArrayHasKey( '{old_status}', $variables );
		self::assertArrayHasKey( '{new_status}', $variables );
	}

	public function testSeedIfEmptyPopulatesEmptyOptions(): void {
		// Ensure all options are absent.
		foreach ( array_keys( EmailTemplateDefaults::subjects() ) as $key ) {
			unset( $GLOBALS['wp_site_options'][ $key ] );
		}
		foreach ( array_keys( EmailTemplateDefaults::bodies() ) as $key ) {
			unset( $GLOBALS['wp_site_options'][ $key ] );
		}

		EmailTemplateDefaults::seedIfEmpty();

		foreach ( EmailTemplateDefaults::subjects() as $key => $expected ) {
			self::assertSame( $expected, $GLOBALS['wp_site_options'][ $key ] ?? null, "Subject $key should be seeded" );
		}
		foreach ( EmailTemplateDefaults::bodies() as $key => $expected ) {
			self::assertSame( $expected, $GLOBALS['wp_site_options'][ $key ] ?? null, "Body $key should be seeded" );
		}
	}

	public function testSeedIfEmptyDoesNotOverwriteExistingValues(): void {
		$custom_subject = 'My custom subject';
		$custom_body    = '<p>My custom body</p>';

		$GLOBALS['wp_site_options'][ Constants::OPTION_EMAIL_TEMPLATE_TICKET_CREATED_SUBJECT ] = $custom_subject;
		$GLOBALS['wp_site_options'][ Constants::OPTION_EMAIL_TEMPLATE_TICKET_CREATED_BODY ]    = $custom_body;

		EmailTemplateDefaults::seedIfEmpty();

		self::assertSame( $custom_subject, $GLOBALS['wp_site_options'][ Constants::OPTION_EMAIL_TEMPLATE_TICKET_CREATED_SUBJECT ] );
		self::assertSame( $custom_body, $GLOBALS['wp_site_options'][ Constants::OPTION_EMAIL_TEMPLATE_TICKET_CREATED_BODY ] );
	}

	public function testDefaultBodiesContainExpectedPlaceholders(): void {
		$bodies = EmailTemplateDefaults::bodies();

		// Ticket-created body should include subject, requester, and link placeholders.
		$created_body = $bodies[ Constants::OPTION_EMAIL_TEMPLATE_TICKET_CREATED_BODY ];
		self::assertStringContainsString( '{ticket_no}', $created_body );
		self::assertStringContainsString( '{ticket_subject}', $created_body );
		self::assertStringContainsString( '{requester_name}', $created_body );

		// Reply body should include message body placeholder.
		$reply_body = $bodies[ Constants::OPTION_EMAIL_TEMPLATE_TICKET_REPLY_BODY ];
		self::assertStringContainsString( '{message_body}', $reply_body );

		// Status-changed body should include status placeholders.
		$status_body = $bodies[ Constants::OPTION_EMAIL_TEMPLATE_STATUS_CHANGED_BODY ];
		self::assertStringContainsString( '{old_status}', $status_body );
		self::assertStringContainsString( '{new_status}', $status_body );

		// Admin body should include requester email.
		$admin_body = $bodies[ Constants::OPTION_EMAIL_TEMPLATE_TICKET_CREATED_ADMIN_BODY ];
		self::assertStringContainsString( '{requester_email}', $admin_body );
	}
}
