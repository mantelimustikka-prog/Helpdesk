<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use WPHelpdesk\Interfaces\Rest\AppPasswordResetController;

require_once __DIR__ . '/bootstrap.php';

/**
 * Testable sub-class that bypasses all database operations.
 */
final class FakeAppPasswordResetController extends AppPasswordResetController {
	/** @var array<string, mixed>|null  Most-recently inserted reset code row. */
	public ?array $insertedRow = null;

	/** @var array<string, mixed>|null  Most-recently saved token. */
	public ?array $savedToken = null;

	/** @var int|null  ID of most-recently deleted record. */
	public ?int $deletedId = null;

	/** @var int  How many times incrementAttempts() was called. */
	public int $incrementCallCount = 0;

	// Preset data for find* helpers.
	private ?array $recentCode  = null;
	private ?array $validCode   = null;
	private ?array $validToken  = null;

	public function setRecentCode( ?array $row ): void {
		$this->recentCode = $row;
	}

	public function setValidCode( ?array $row ): void {
		$this->validCode = $row;
	}

	public function setValidToken( ?array $row ): void {
		$this->validToken = $row;
	}

	protected function findRecentCode( string $email, int $minutes ): ?array {
		return $this->recentCode;
	}

	protected function findValidCode( string $email, string $code ): ?array {
		return $this->validCode;
	}

	protected function findValidToken( string $email, string $reset_token ): ?array {
		return $this->validToken;
	}

	protected function insertResetCode( string $email, string $code, string $created_at, string $expires_at ): void {
		$this->insertedRow = compact( 'email', 'code', 'created_at', 'expires_at' );
	}

	protected function saveResetToken( int $id, string $reset_token, string $token_expires, string $last_attempt_at ): void {
		$this->savedToken = compact( 'id', 'reset_token', 'token_expires', 'last_attempt_at' );
	}

	protected function incrementAttempts( string $email, string $code ): void {
		++$this->incrementCallCount;
	}

	protected function deleteResetRecord( int $id ): void {
		$this->deletedId = $id;
	}
}

final class AppPasswordResetControllerTest extends TestCase {

	protected function setUp(): void {
		wp_helpdesk_test_reset_state();
	}

	// =========================================================================
	// requestResetCode
	// =========================================================================

	public function testRequestResetCodeReturnsBadRequestForMissingEmail(): void {
		$controller = new FakeAppPasswordResetController();
		$request    = new WP_REST_Request();

		$response = $controller->requestResetCode( $request );

		self::assertSame( 400, $response->status );
		self::assertFalse( $response->data['success'] );
	}

	public function testRequestResetCodeReturnsBadRequestForInvalidEmail(): void {
		$controller = new FakeAppPasswordResetController();
		$request    = new WP_REST_Request();
		$request->set_param( 'email', 'not-an-email' );

		$response = $controller->requestResetCode( $request );

		self::assertSame( 400, $response->status );
		self::assertFalse( $response->data['success'] );
	}

	public function testRequestResetCodeGeneratesAndStoresCode(): void {
		$controller = new FakeAppPasswordResetController();
		$request    = new WP_REST_Request();
		$request->set_param( 'email', 'user@example.com' );

		$response = $controller->requestResetCode( $request );

		self::assertSame( 200, $response->status );
		self::assertTrue( $response->data['success'] );
		self::assertSame( 'Reset code sent to email', $response->data['message'] );
		self::assertNotNull( $controller->insertedRow );
		self::assertSame( 'user@example.com', $controller->insertedRow['email'] );
		self::assertRegExp( '/^\d{8}$/', $controller->insertedRow['code'] );
	}

	public function testRequestResetCodeSendsEmail(): void {
		$controller = new FakeAppPasswordResetController();
		$request    = new WP_REST_Request();
		$request->set_param( 'email', 'user@example.com' );

		$controller->requestResetCode( $request );

		self::assertCount( 1, $GLOBALS['wp_mail_calls'] );
		self::assertSame( 'user@example.com', $GLOBALS['wp_mail_calls'][0]['to'] );
		self::assertSame( 'WP HelpD App Password Reset', $GLOBALS['wp_mail_calls'][0]['subject'] );
	}

	public function testRequestResetCodeReusesCodeIfRequestedWithinFiveMinutes(): void {
		$controller = new FakeAppPasswordResetController();
		$controller->setRecentCode( array( 'id' => 1, 'reset_code' => '12345678' ) );

		$request = new WP_REST_Request();
		$request->set_param( 'email', 'user@example.com' );

		$response = $controller->requestResetCode( $request );

		self::assertSame( 200, $response->status );
		// No new code was inserted because a recent one was reused.
		self::assertNull( $controller->insertedRow );
		// Email should still be sent.
		self::assertCount( 1, $GLOBALS['wp_mail_calls'] );
	}

	// =========================================================================
	// verifyResetCode
	// =========================================================================

	public function testVerifyResetCodeReturnsBadRequestForMissingFields(): void {
		$controller = new FakeAppPasswordResetController();
		$request    = new WP_REST_Request();

		$response = $controller->verifyResetCode( $request );

		self::assertSame( 400, $response->status );
		self::assertFalse( $response->data['success'] );
	}

	public function testVerifyResetCodeReturnsErrorForInvalidCode(): void {
		$controller = new FakeAppPasswordResetController();
		// validCode is null → code not found / expired.
		$request = new WP_REST_Request();
		$request->set_param( 'email', 'user@example.com' );
		$request->set_param( 'code', '99999999' );

		$response = $controller->verifyResetCode( $request );

		self::assertSame( 400, $response->status );
		self::assertSame( 'invalid_code', $response->data['error']['code'] );
		self::assertSame( 1, $controller->incrementCallCount );
	}

	public function testVerifyResetCodeBlocksAfterThreeFailedAttempts(): void {
		$controller = new FakeAppPasswordResetController();
		$controller->setValidCode( array( 'id' => 1, 'attempts' => 3 ) );

		$request = new WP_REST_Request();
		$request->set_param( 'email', 'user@example.com' );
		$request->set_param( 'code', '12345678' );

		$response = $controller->verifyResetCode( $request );

		self::assertSame( 400, $response->status );
		self::assertSame( 'too_many_attempts', $response->data['error']['code'] );
	}

	public function testVerifyResetCodeReturnsResetTokenOnSuccess(): void {
		$controller = new FakeAppPasswordResetController();
		$controller->setValidCode( array( 'id' => 5, 'attempts' => 0 ) );

		$request = new WP_REST_Request();
		$request->set_param( 'email', 'user@example.com' );
		$request->set_param( 'code', '12345678' );

		$response = $controller->verifyResetCode( $request );

		self::assertSame( 200, $response->status );
		self::assertTrue( $response->data['success'] );
		self::assertArrayHasKey( 'reset_token', $response->data );
		self::assertSame( 64, strlen( $response->data['reset_token'] ) );
		self::assertNotNull( $controller->savedToken );
		self::assertSame( 5, $controller->savedToken['id'] );
	}

	// =========================================================================
	// resetPassword
	// =========================================================================

	public function testResetPasswordReturnsBadRequestForMissingFields(): void {
		$controller = new FakeAppPasswordResetController();
		$request    = new WP_REST_Request();

		$response = $controller->resetPassword( $request );

		self::assertSame( 400, $response->status );
		self::assertFalse( $response->data['success'] );
	}

	public function testResetPasswordRejectsShortPassword(): void {
		$controller = new FakeAppPasswordResetController();
		$request    = new WP_REST_Request();
		$request->set_param( 'reset_token', str_repeat( 'a', 64 ) );
		$request->set_param( 'email', 'user@example.com' );
		$request->set_param( 'new_password', 'abc' );

		$response = $controller->resetPassword( $request );

		self::assertSame( 400, $response->status );
		self::assertSame( 'password_too_short', $response->data['error']['code'] );
	}

	public function testResetPasswordFailsForInvalidToken(): void {
		$controller = new FakeAppPasswordResetController();
		// validToken is null → token not found / expired.
		$request = new WP_REST_Request();
		$request->set_param( 'reset_token', str_repeat( 'a', 64 ) );
		$request->set_param( 'email', 'user@example.com' );
		$request->set_param( 'new_password', 'NewPass123' );

		$response = $controller->resetPassword( $request );

		self::assertSame( 400, $response->status );
		self::assertSame( 'invalid_token', $response->data['error']['code'] );
	}

	public function testResetPasswordSucceedsAndDeletesRecord(): void {
		$controller = new FakeAppPasswordResetController();
		$controller->setValidToken( array( 'id' => 7 ) );

		$request = new WP_REST_Request();
		$request->set_param( 'reset_token', str_repeat( 'a', 64 ) );
		$request->set_param( 'email', 'user@example.com' );
		$request->set_param( 'new_password', 'NewPass123' );

		$response = $controller->resetPassword( $request );

		self::assertSame( 200, $response->status );
		self::assertTrue( $response->data['success'] );
		self::assertSame( 'Password reset successfully', $response->data['message'] );
		self::assertSame( 7, $controller->deletedId );
	}

	// =========================================================================
	// Public access (permission_callback = __return_true)
	// =========================================================================

	public function testEndpointsArePublicNoAuthRequired(): void {
		// All three controller methods must be callable without any logged-in
		// user. We verify by calling them without setting logged-in globals.
		$GLOBALS['wp_current_user_logged_in'] = false;
		$GLOBALS['wp_current_user_caps']      = array();

		$controller = new FakeAppPasswordResetController();
		$controller->setValidCode( array( 'id' => 1, 'attempts' => 0 ) );
		$controller->setValidToken( array( 'id' => 1 ) );

		$req1 = new WP_REST_Request();
		$req1->set_param( 'email', 'user@example.com' );
		self::assertSame( 200, $controller->requestResetCode( $req1 )->status );

		$req2 = new WP_REST_Request();
		$req2->set_param( 'email', 'user@example.com' );
		$req2->set_param( 'code', '12345678' );
		self::assertSame( 200, $controller->verifyResetCode( $req2 )->status );

		$req3 = new WP_REST_Request();
		$req3->set_param( 'reset_token', str_repeat( 'a', 64 ) );
		$req3->set_param( 'email', 'user@example.com' );
		$req3->set_param( 'new_password', 'ValidPass1' );
		self::assertSame( 200, $controller->resetPassword( $req3 )->status );
	}
}
