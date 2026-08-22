<?php
/**
 * @package WP Helpdesk
 */

namespace WPHelpdesk\Domain\Notification;

use WPHelpdesk\Support\Constants;

/**
 * Canonical default subjects and HTML bodies for all outbound email templates.
 *
 * This class is the single source of truth for built-in template content.
 * NotificationService uses these as fallbacks when a saved setting is empty,
 * and Activator seeds empty settings from this map on first activation.
 */
class EmailTemplateDefaults {

	/**
	 * All supported template variables and their descriptions.
	 *
	 * @return array<string, string> Map of placeholder => human-readable description.
	 */
	public static function variables(): array {
		return array(
			// Ticket identifiers.
			'{ticket_no}'       => __( 'Ticket number (e.g. HD-001001)', 'wp-helpdesk' ),
			'{ticket_subject}'  => __( 'Ticket subject line', 'wp-helpdesk' ),
			'{ticket_status}'   => __( 'Current ticket status label', 'wp-helpdesk' ),
			'{ticket_link}'     => __( 'URL to view the ticket (guest link when applicable)', 'wp-helpdesk' ),
			// Status change.
			'{old_status}'      => __( 'Previous status label (status-changed emails)', 'wp-helpdesk' ),
			'{new_status}'      => __( 'New status label (status-changed emails)', 'wp-helpdesk' ),
			// Message.
			'{message_body}'    => __( 'Reply message body (reply emails)', 'wp-helpdesk' ),
			// Requester / client.
			'{requester_name}'  => __( 'Requester display name', 'wp-helpdesk' ),
			'{requester_email}' => __( 'Requester email address', 'wp-helpdesk' ),
		);
	}

	/**
	 * Default subject lines keyed by Constants option key.
	 *
	 * @return array<string, string>
	 */
	public static function subjects(): array {
		return array(
			Constants::OPTION_EMAIL_TEMPLATE_TICKET_CREATED_SUBJECT       => 'Ticket created: {ticket_no}',
			Constants::OPTION_EMAIL_TEMPLATE_TICKET_REPLY_SUBJECT         => 'Ticket reply: {ticket_no}',
			Constants::OPTION_EMAIL_TEMPLATE_STATUS_CHANGED_SUBJECT       => 'Ticket status updated: {ticket_no}',
			Constants::OPTION_EMAIL_TEMPLATE_TICKET_CREATED_ADMIN_SUBJECT => 'New helpdesk ticket: {ticket_no}',
		);
	}

	/**
	 * Return the default subject for a single option key.
	 *
	 * @param string $option_key One of the Constants::OPTION_EMAIL_TEMPLATE_*_SUBJECT keys.
	 * @return string Empty string when the key is not found.
	 */
	public static function subject( string $option_key ): string {
		return self::subjects()[ $option_key ] ?? '';
	}

	/**
	 * Default HTML body content keyed by Constants option key.
	 *
	 * Bodies use the same {placeholder} syntax supported by the token renderer.
	 *
	 * @return array<string, string>
	 */
	public static function bodies(): array {
		return array(
			Constants::OPTION_EMAIL_TEMPLATE_TICKET_CREATED_BODY =>
				'<div style="font-family: Arial, sans-serif; color: #1d2327;">'
				. '<h2>Ticket {ticket_no} created</h2>'
				. '<p><strong>Subject:</strong> {ticket_subject}</p>'
				. '<p><strong>Requester:</strong> {requester_name}</p>'
				. '<p>Your support request has been received by WP Helpdesk.</p>'
				. '<p>You can view your ticket, read updates, and add a reply at any time using the link below:</p>'
				. '<p><a href="{ticket_link}" style="display:inline-block;padding:10px 20px;background:#2271b1;color:#fff;text-decoration:none;border-radius:4px;">View your ticket</a></p>'
				. '<p style="font-size:0.85em;color:#646970;">{ticket_link}</p>'
				. '</div>',

			Constants::OPTION_EMAIL_TEMPLATE_TICKET_REPLY_BODY =>
				'<div style="font-family: Arial, sans-serif; color: #1d2327;">'
				. '<h2>New reply for {ticket_no}</h2>'
				. '<p><strong>Subject:</strong> {ticket_subject}</p>'
				. '<p><strong>Reply:</strong></p>'
				. '<div>{message_body}</div>'
				. '<p><a href="{ticket_link}" style="display:inline-block;padding:10px 20px;background:#2271b1;color:#fff;text-decoration:none;border-radius:4px;">View and continue this ticket</a></p>'
				. '<p style="font-size:0.85em;color:#646970;">{ticket_link}</p>'
				. '</div>',

			Constants::OPTION_EMAIL_TEMPLATE_STATUS_CHANGED_BODY =>
				'<div style="font-family: Arial, sans-serif; color: #1d2327;">'
				. '<h2>Ticket {ticket_no} status updated</h2>'
				. '<p><strong>Subject:</strong> {ticket_subject}</p>'
				. '<p><strong>Previous status:</strong> {old_status}</p>'
				. '<p><strong>New status:</strong> {new_status}</p>'
				. '<p>This is an outbound notification. Please log in to reply or review the ticket.</p>'
				. '</div>',

			Constants::OPTION_EMAIL_TEMPLATE_TICKET_CREATED_ADMIN_BODY =>
				'<div style="font-family: Arial, sans-serif; color: #1d2327;">'
				. '<h2>New helpdesk ticket: {ticket_no}</h2>'
				. '<p><strong>Subject:</strong> {ticket_subject}</p>'
				. '<p><strong>Requester:</strong> {requester_name} &lt;{requester_email}&gt;</p>'
				. '<p>A new support ticket has been submitted. Please log in to review and respond.</p>'
				. '</div>',
		);
	}

	/**
	 * Seed each template setting when it is currently empty/unset.
	 * Existing non-empty values are never overwritten.
	 *
	 * @return void
	 */
	public static function seedIfEmpty(): void {
		foreach ( self::subjects() as $option_key => $default_subject ) {
			$current = get_site_option( $option_key, '' );
			if ( '' === trim( (string) $current ) ) {
				update_site_option( $option_key, $default_subject );
			}
		}

		foreach ( self::bodies() as $option_key => $default_body ) {
			$current = get_site_option( $option_key, '' );
			if ( '' === trim( (string) $current ) ) {
				update_site_option( $option_key, $default_body );
			}
		}
	}
}
