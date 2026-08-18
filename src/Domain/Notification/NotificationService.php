<?php
/**
 * @package WP Helpdesk
 */

namespace WPHelpdesk\Domain\Notification;

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
		$content = $this->renderTemplate(
			Helpers::pluginPath( 'templates/emails/ticket-created.php' ),
			array( 'ticket' => $ticket )
		);

		$this->send(
			$recipient_email,
			sprintf( 'Ticket created: %s', (string) ( $ticket['ticket_no'] ?? '' ) ),
			$content
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
		$content = $this->renderTemplate(
			Helpers::pluginPath( 'templates/emails/ticket-reply.php' ),
			array(
				'ticket'  => $ticket,
				'message' => $message,
			)
		);

		$this->send(
			$recipient_email,
			sprintf( 'Ticket reply: %s', (string) ( $ticket['ticket_no'] ?? '' ) ),
			$content
		);
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
		$content = $this->renderTemplate(
			Helpers::pluginPath( 'templates/emails/status-changed.php' ),
			array(
				'ticket'     => $ticket,
				'oldStatus'  => $old_status,
				'newStatus'  => $new_status,
			)
		);

		$this->send(
			$recipient_email,
			sprintf( 'Ticket status updated: %s', (string) ( $ticket['ticket_no'] ?? '' ) ),
			$content
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

		$content = $this->renderTemplate(
			Helpers::pluginPath( 'templates/emails/ticket-created.php' ),
			array( 'ticket' => $ticket )
		);

		$this->send(
			$recipient,
			sprintf( 'New helpdesk ticket: %s', (string) ( $ticket['ticket_no'] ?? '' ) ),
			$content
		);
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
