<?php
/**
 * @package WP Helpdesk
 */

namespace WPHelpdesk\Domain\Notification;

use WPHelpdesk\Infrastructure\Database\Schema;
use WPHelpdesk\Domain\Ticket\TicketStatus;
use WPHelpdesk\Support\Constants;
use WPHelpdesk\Support\Helpers;

class NotificationService {
	public function __construct() {
	}

	/**
	 * Wrap an email body with saved network layout fragments.
	 *
	 * @param string $content Body content.
	 * @return string
	 */
	private function wrapWithLayout( string $content ): string {
		$header = '';
		$footer = '';

		if ( (int) get_site_option( Constants::OPTION_EMAIL_HEADER_ENABLED, 1 ) ) {
			$header = (string) get_site_option( Constants::OPTION_EMAIL_HEADER_HTML, '' );
		}

		if ( (int) get_site_option( Constants::OPTION_EMAIL_FOOTER_ENABLED, 1 ) ) {
			$footer = (string) get_site_option( Constants::OPTION_EMAIL_FOOTER_HTML, '' );
		}

		return $header . $content . $footer;
	}

	/**
	 * Send an outbound HTML email.
	 *
	 * @param string              $to      Recipient.
	 * @param string              $subject Subject line.
	 * @param string              $content Raw content.
	 * @param array<int, string>  $headers Extra headers.
	 * @return bool
	 */
	private function send( string $to, string $subject, string $content, array $headers = array() ): bool {
		$body      = $this->wrapWithLayout( $content );
		$headers[] = 'Content-Type: text/html; charset=UTF-8';

		$from_name = (string) get_site_option( Constants::OPTION_EMAIL_FROM_NAME, '' );
		$from_addr = (string) get_site_option( Constants::OPTION_EMAIL_FROM_ADDRESS, '' );
		$reply_to  = (string) get_site_option( Constants::OPTION_EMAIL_REPLY_TO, '' );

		if ( '' !== $from_addr ) {
			$from_label = '' !== $from_name ? $from_name . ' <' . $from_addr . '>' : $from_addr;
			$headers[]  = 'From: ' . $from_label;
		}

		if ( '' !== $reply_to ) {
			$headers[] = 'Reply-To: ' . $reply_to;
		}

		return wp_mail( $to, $subject, $body, $headers );
	}

	/**
	 * Send a ticket created notification.
	 *
	 * @param array<string, mixed> $ticket          Ticket payload.
	 * @param string               $recipient_email Recipient email.
	 * @return void
	 */
	public function sendTicketCreated( array $ticket, string $recipient_email ): void {
		$this->sendTemplateEmail(
			$recipient_email,
			Constants::OPTION_EMAIL_TEMPLATE_TICKET_CREATED_SUBJECT,
			EmailTemplateDefaults::subject( Constants::OPTION_EMAIL_TEMPLATE_TICKET_CREATED_SUBJECT ),
			Constants::OPTION_EMAIL_TEMPLATE_TICKET_CREATED_BODY,
			Helpers::pluginPath( 'templates/emails/ticket-created.php' ),
			array( 'ticket' => $ticket ),
			$this->buildTokens( $ticket )
		);
	}

	/**
	 * Send a reply notification.
	 *
	 * @param array<string, mixed> $ticket          Ticket payload.
	 * @param array<string, mixed> $message         Message payload.
	 * @param string               $recipient_email Recipient email.
	 * @return void
	 */
	public function sendTicketReply( array $ticket, array $message, string $recipient_email ): void {
		$ticket = $this->ensureGuestTicketLink( $ticket );
		$this->sendTemplateEmail(
			$recipient_email,
			Constants::OPTION_EMAIL_TEMPLATE_TICKET_REPLY_SUBJECT,
			EmailTemplateDefaults::subject( Constants::OPTION_EMAIL_TEMPLATE_TICKET_REPLY_SUBJECT ),
			Constants::OPTION_EMAIL_TEMPLATE_TICKET_REPLY_BODY,
			Helpers::pluginPath( 'templates/emails/ticket-reply.php' ),
			array(
				'ticket'  => $ticket,
				'message' => $message,
			),
			$this->buildTokens( $ticket, $message )
		);
	}

	/**
	 * Ensure a guest-access ticket link exists for guest reply notifications.
	 *
	 * @param array<string, mixed> $ticket Ticket payload.
	 * @return array<string, mixed>
	 */
	protected function ensureGuestTicketLink( array $ticket ): array {
		if ( ! empty( $ticket['ticket_link'] ) || empty( $ticket['ticket_no'] ) ) {
			return $ticket;
		}

		$ticket_no = (string) $ticket['ticket_no'];

		if ( ! empty( $ticket['guest_token'] ) ) {
			$ticket['ticket_link'] = $this->buildGuestTicketUrl( $ticket_no, (string) $ticket['guest_token'] );
			return $ticket;
		}

		$ticket_id = (int) ( $ticket['id'] ?? 0 );
		if ( ! empty( $ticket['user_id'] ) || $ticket_id <= 0 ) {
			return $ticket;
		}

		try {
			$guest_token = bin2hex( random_bytes( 32 ) );
		} catch ( \Exception $e ) {
			return $ticket;
		}

		global $wpdb;
		$table   = Schema::table( Constants::TABLE_TICKETS );
		$updated = $wpdb->update(
			$table,
			array( 'guest_token_hash' => Helpers::hashGuestToken( $guest_token ) ),
			array( 'id' => $ticket_id ),
			array( '%s' ),
			array( '%d' )
		);

		if ( $updated > 0 ) {
			$ticket['ticket_link'] = $this->buildGuestTicketUrl( $ticket_no, $guest_token );
		}

		return $ticket;
	}

	/**
	 * Build a guest ticket URL from ticket number and token.
	 *
	 * @param string $ticket_no   Ticket number.
	 * @param string $guest_token Guest token.
	 * @return string
	 */
	protected function buildGuestTicketUrl( string $ticket_no, string $guest_token ): string {
		return home_url( '/helpdesk/ticket/' . rawurlencode( $ticket_no ) . '/' . rawurlencode( $guest_token ) . '/' );
	}

	/**
	 * Send a status change notification.
	 *
	 * @param array<string, mixed> $ticket          Ticket payload.
	 * @param string               $old_status      Previous status.
	 * @param string               $new_status      New status.
	 * @param string               $recipient_email Recipient email.
	 * @return void
	 */
	public function sendStatusChanged( array $ticket, string $old_status, string $new_status, string $recipient_email ): void {
		$this->sendTemplateEmail(
			$recipient_email,
			Constants::OPTION_EMAIL_TEMPLATE_STATUS_CHANGED_SUBJECT,
			EmailTemplateDefaults::subject( Constants::OPTION_EMAIL_TEMPLATE_STATUS_CHANGED_SUBJECT ),
			Constants::OPTION_EMAIL_TEMPLATE_STATUS_CHANGED_BODY,
			Helpers::pluginPath( 'templates/emails/status-changed.php' ),
			array(
				'ticket'     => $ticket,
				'oldStatus'  => TicketStatus::label( $old_status ),
				'newStatus'  => TicketStatus::label( $new_status ),
			),
			$this->buildTokens( $ticket, array(), $old_status, $new_status )
		);
	}

	/**
	 * Send a network admin notification for a new ticket.
	 *
	 * @param array<string, mixed> $ticket Ticket payload.
	 * @return void
	 */
	public function sendTicketCreatedAdmin( array $ticket ): void {
		$recipient = (string) get_site_option( 'admin_email', get_option( 'admin_email' ) );
		if ( '' === $recipient ) {
			return;
		}

		$this->sendTemplateEmail(
			$recipient,
			Constants::OPTION_EMAIL_TEMPLATE_TICKET_CREATED_ADMIN_SUBJECT,
			EmailTemplateDefaults::subject( Constants::OPTION_EMAIL_TEMPLATE_TICKET_CREATED_ADMIN_SUBJECT ),
			Constants::OPTION_EMAIL_TEMPLATE_TICKET_CREATED_ADMIN_BODY,
			Helpers::pluginPath( 'templates/emails/ticket-created.php' ),
			array( 'ticket' => $ticket ),
			$this->buildTokens( $ticket )
		);
	}

	/**
	 * Send email with template overrides from settings.
	 *
	 * @param string               $recipient       Recipient email.
	 * @param string               $subject_option  Subject option key.
	 * @param string               $default_subject Default subject.
	 * @param string               $body_option     Body option key.
	 * @param string               $default_tpl     Default template path.
	 * @param array<string, mixed> $template_vars   Default template vars.
	 * @param array<string, string> $tokens         Replacement tokens.
	 * @return void
	 */
	private function sendTemplateEmail( string $recipient, string $subject_option, string $default_subject, string $body_option, string $default_tpl, array $template_vars, array $tokens ): void {
		$subject = (string) get_site_option( $subject_option, $default_subject );
		if ( '' === trim( $subject ) ) {
			$subject = $default_subject;
		}

		$subject = $this->replaceTokens( $subject, $tokens );
		$body    = (string) get_site_option( $body_option, '' );
		if ( '' === trim( $body ) ) {
			$body = $this->renderTemplate( $default_tpl, $template_vars );
		} else {
			$body = $this->replaceTokens( $body, $tokens );
		}

		$this->send( $recipient, $subject, $body );
	}

	/**
	 * @param array<string, mixed> $ticket Ticket data.
	 * @param array<string, mixed> $message Optional message data.
	 * @param string               $old_status Optional old status.
	 * @param string               $new_status Optional new status.
	 * @return array<string, string>
	 */
	private function buildTokens( array $ticket, array $message = array(), string $old_status = '', string $new_status = '' ): array {
		return array(
			'{ticket_no}'       => (string) ( $ticket['ticket_no'] ?? '' ),
			'{ticket_subject}'  => (string) ( $ticket['subject'] ?? '' ),
			'{ticket_status}'   => TicketStatus::label( (string) ( $ticket['status'] ?? '' ) ),
			'{old_status}'      => '' !== $old_status ? TicketStatus::label( $old_status ) : '',
			'{new_status}'      => '' !== $new_status ? TicketStatus::label( $new_status ) : '',
			'{message_body}'    => (string) ( $message['body'] ?? '' ),
			'{requester_name}'  => (string) ( $ticket['requester_name'] ?? '' ),
			'{requester_email}' => (string) ( $ticket['requester_email'] ?? '' ),
			'{ticket_link}'     => (string) ( $ticket['ticket_link'] ?? '' ),
		);
	}

	/**
	 * @param string               $content Content with tokens.
	 * @param array<string, string> $tokens Token map.
	 * @return string
	 */
	private function replaceTokens( string $content, array $tokens ): string {
		return strtr( $content, $tokens );
	}

	/**
	 * Render a PHP email template.
	 *
	 * @param string               $template_path Absolute path to template.
	 * @param array<string, mixed> $vars          Template variables.
	 * @return string
	 */
	protected function renderTemplate( string $template_path, array $vars ): string {
		if ( ! file_exists( $template_path ) ) {
			return '';
		}

		ob_start();
		// Variables are passed as $vars and accessed by key inside templates to avoid extract().
		include $template_path;
		return (string) ob_get_clean();
	}
}
