<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use WPHelpdesk\Interfaces\Frontend\GuestTicketForm;
use WPHelpdesk\Interfaces\Frontend\MemberTicketForm;

require_once __DIR__ . '/bootstrap.php';

/**
 * Tests that topic description UI elements are present in the rendered frontend forms.
 */
final class FrontendTopicDescriptionTest extends TestCase {

	protected function setUp(): void {
		wp_helpdesk_test_reset_state();
		// Require topic selection.
		$GLOBALS['wp_site_options']['hd_require_topic'] = 1;
	}

	// -----------------------------------------------------------------------
	// Guest form – step 0 description hint
	// -----------------------------------------------------------------------

	public function testGuestFormStep0ContainsTopicDescriptionHintElement(): void {
		$output = $this->captureGuestForm();

		self::assertStringContainsString( 'id="hd-topic-description"', $output );
		self::assertStringContainsString( 'aria-live="polite"', $output );
	}

	public function testGuestFormStep0DescriptionHintIsInsideStep0(): void {
		$output = $this->captureGuestForm();

		// The description hint must appear before the hidden step-1 form step marker.
		$posHint  = strpos( $output, 'id="hd-topic-description"' );
		$posStep1 = strpos( $output, 'hd-form-step--hidden" data-step="1"' );
		self::assertNotFalse( $posHint );
		self::assertNotFalse( $posStep1 );
		self::assertLessThan( $posStep1, $posHint, 'Topic description hint should appear in step 0, before step 1.' );
	}

	// -----------------------------------------------------------------------
	// Guest form – step 1 topic description summary
	// -----------------------------------------------------------------------

	public function testGuestFormStep1ContainsTopicDescriptionSummaryElement(): void {
		$output = $this->captureGuestForm();

		self::assertStringContainsString( 'data-role="topic-description-step1"', $output );
		self::assertStringContainsString( 'hd-topic-description-summary', $output );
	}

	public function testGuestFormStep1DescriptionSummaryIsInsideStep1(): void {
		$output = $this->captureGuestForm();

		// Locate the step-1 container opening and the start of step-2.
		$step1Start = strpos( $output, 'hd-form-step--hidden" data-step="1"' );
		$step2Start = strpos( $output, 'hd-form-step--hidden" data-step="2"' );
		$summaryPos = strpos( $output, 'data-role="topic-description-step1"' );

		self::assertNotFalse( $step1Start );
		self::assertNotFalse( $step2Start );
		self::assertNotFalse( $summaryPos );
		self::assertGreaterThan( $step1Start, $summaryPos, 'Summary should appear after step 1 opens.' );
		self::assertLessThan( $step2Start, $summaryPos, 'Summary should appear before step 2 opens.' );
	}

	public function testGuestFormStep1DescriptionSummaryIsInitiallyEmpty(): void {
		$output = $this->captureGuestForm();

		// The element must be rendered empty in the HTML (populated by JS at runtime).
		self::assertRegExp(
			'%data-role="topic-description-step1"[^>]*>\s*</div>%',
			$output,
			'The step-1 topic description summary container must be empty in the server-rendered HTML.'
		);
	}

	public function testGuestOrderRelationOptionsUsePlaceholderAndNewValues(): void {
		$output = $this->captureGuestForm();

		self::assertStringContainsString( '<option value="" selected disabled>Select Option</option>', $output );
		self::assertStringContainsString( '<option value="existing_order_related">Existing order related</option>', $output );
		self::assertStringContainsString( '<option value="not_any_existing_order_related">Not any existing order related</option>', $output );
		self::assertStringNotContainsString( 'not_order_related', $output );
		self::assertStringContainsString( 'data-role="order-select-field"', $output );
		self::assertStringContainsString( 'name="order_id"', $output );
	}

	// -----------------------------------------------------------------------
	// Member form – step 0 description hint
	// -----------------------------------------------------------------------

	public function testMemberFormStep0ContainsTopicDescriptionHintElement(): void {
		$output = $this->captureMemberForm();

		self::assertStringContainsString( 'id="hd-member-topic-description"', $output );
		self::assertStringContainsString( 'aria-live="polite"', $output );
	}

	// -----------------------------------------------------------------------
	// Member form – step 1 topic description summary
	// -----------------------------------------------------------------------

	public function testMemberFormStep1ContainsTopicDescriptionSummaryElement(): void {
		$output = $this->captureMemberForm();

		self::assertStringContainsString( 'data-role="topic-description-step1"', $output );
		self::assertStringContainsString( 'hd-topic-description-summary', $output );
	}

	public function testMemberFormStep1DescriptionSummaryIsInsideStep1(): void {
		$output = $this->captureMemberForm();

		$step1Start = strpos( $output, 'hd-form-step--hidden" data-step="1"' );
		$step2Start = strpos( $output, 'hd-form-step--hidden" data-step="2"' );
		$summaryPos = strpos( $output, 'data-role="topic-description-step1"' );

		self::assertNotFalse( $step1Start );
		self::assertNotFalse( $step2Start );
		self::assertNotFalse( $summaryPos );
		self::assertGreaterThan( $step1Start, $summaryPos, 'Summary should appear after step 1 opens.' );
		self::assertLessThan( $step2Start, $summaryPos, 'Summary should appear before step 2 opens.' );
	}

	public function testMemberFormStep1DescriptionSummaryIsInitiallyEmpty(): void {
		$output = $this->captureMemberForm();

		self::assertRegExp(
			'%data-role="topic-description-step1"[^>]*>\s*</div>%',
			$output,
			'The step-1 topic description summary container must be empty in the server-rendered HTML.'
		);
	}

	public function testMemberOrderRelationOptionsUsePlaceholderAndNewValues(): void {
		$output = $this->captureMemberForm();

		self::assertStringContainsString( '<option value="" selected disabled>Select Option</option>', $output );
		self::assertStringContainsString( '<option value="existing_order_related">Existing order related</option>', $output );
		self::assertStringContainsString( '<option value="not_any_existing_order_related">Not any existing order related</option>', $output );
		self::assertStringNotContainsString( 'not_order_related', $output );
		self::assertStringContainsString( 'data-role="order-select-field"', $output );
		self::assertStringContainsString( 'name="order_id"', $output );
	}

	public function testMemberOrderSelectFieldIsInitiallyHiddenForDynamicLoad(): void {
		$output = $this->captureMemberForm();

		// The order-select-field must start hidden so the JS can reveal it after
		// fetching the user's orders when "Existing order related" is selected.
		self::assertRegExp(
			'%hd-form-step--hidden[^"]*"\s+data-role="order-select-field"%',
			$output,
			'The order-select-field container must carry hd-form-step--hidden so it is hidden until JS reveals it.'
		);
	}

	public function testMemberOrderIdSelectStartsWithoutRequiredAttributeForDynamicLoad(): void {
		$output = $this->captureMemberForm();

		// The order_id select must NOT be required in the server HTML.
		// The JS adds required dynamically when "Existing order related" is selected.
		self::assertStringContainsString( 'name="order_id"', $output );
		self::assertStringContainsString( 'aria-required="false"', $output );
	}

	// -----------------------------------------------------------------------
	// Layout width
	// -----------------------------------------------------------------------

	public function testFrontendCssUsesWiderMaxWidth(): void {
		$css = file_get_contents( __DIR__ . '/../assets/css/helpdesk-frontend.css' );
		self::assertNotFalse( $css );

		// The .hd-wrap rule must not use the old 680px constraint.
		self::assertStringNotContainsString( 'max-width: 680px', $css );

		// It must declare a wider constraint (at least 800px).
		preg_match( '/\.hd-wrap\s*\{[^}]+max-width:\s*(\d+)px/', $css, $matches );
		self::assertNotEmpty( $matches, '.hd-wrap must define a max-width in pixels.' );
		self::assertGreaterThanOrEqual( 800, (int) $matches[1], '.hd-wrap max-width should be at least 800px.' );
	}

	// -----------------------------------------------------------------------
	// Helpers
	// -----------------------------------------------------------------------

	private function captureGuestForm(): string {
		$form = new GuestTicketFormTestDouble();
		ob_start();
		$form->renderForm();
		return (string) ob_get_clean();
	}

	private function captureMemberForm(): string {
		$form = new MemberTicketFormTestDouble();
		ob_start();
		$form->renderMemberForm();
		return (string) ob_get_clean();
	}
}

// ---------------------------------------------------------------------------
// Test doubles that expose protected methods for testing.
// ---------------------------------------------------------------------------

final class GuestTicketFormTestDouble extends GuestTicketForm {
	public function renderForm(): void {
		parent::renderForm();
	}
}

final class MemberTicketFormTestDouble extends MemberTicketForm {
	public function renderMemberForm(): void {
		parent::renderMemberForm();
	}
}
