<?php
/**
 * @package WP Helpdesk
 */

namespace WPHelpdesk\Support;

class Constants {
	public const REST_NAMESPACE            = 'helpdesk/v1';
	public const OPTION_DB_VERSION         = 'hd_db_version';
	public const OPTION_APPLIED_MIGRATIONS = 'hd_applied_migrations';
	public const OPTION_EMAIL_HEADER_HTML  = 'hd_email_header_html';
	public const OPTION_EMAIL_FOOTER_HTML  = 'hd_email_footer_html';
	public const OPTION_FCM_SERVER_KEY     = 'hd_fcm_server_key';
	public const OPTION_TICKET_COUNTER     = 'hd_ticket_counter';
	public const OPTION_TICKET_START       = 'hd_ticket_start_number';
	public const OPTION_PAGINATION         = 'hd_pagination_per_page';
	public const OPTION_SLA_FIRST_RESPONSE = 'hd_sla_first_response_hours';
	public const OPTION_SLA_RESOLUTION     = 'hd_sla_resolution_hours';
	public const TABLE_TOPICS              = 'topics';
	public const TABLE_TOPIC_TRANSITIONS   = 'topic_transitions';
	public const TABLE_FORM_SESSIONS       = 'form_sessions';
	public const TABLE_TICKETS             = 'tickets';
	public const TABLE_TICKET_MESSAGES     = 'ticket_messages';
	public const TABLE_TICKET_EVENTS       = 'ticket_events';
	public const TABLE_DEVICE_TOKENS       = 'device_tokens';
	public const TABLE_ATTACHMENTS         = 'attachments';
	public const TABLE_RATE_LIMITS         = 'rate_limits';
	public const TABLE_ROUTING_RULES       = 'routing_rules';

	/**
	 * Get known network option keys.
	 *
	 * @return array<int, string>
	 */
	public static function optionKeys(): array {
		return array(
			self::OPTION_DB_VERSION,
			self::OPTION_EMAIL_HEADER_HTML,
			self::OPTION_EMAIL_FOOTER_HTML,
			self::OPTION_FCM_SERVER_KEY,
			self::OPTION_TICKET_COUNTER,
			self::OPTION_TICKET_START,
			self::OPTION_PAGINATION,
			self::OPTION_SLA_FIRST_RESPONSE,
			self::OPTION_SLA_RESOLUTION,
		);
	}

	/**
	 * Get known helpdesk table suffixes.
	 *
	 * @return array<int, string>
	 */
	public static function tableSuffixes(): array {
		return array(
			self::TABLE_TOPICS,
			self::TABLE_TOPIC_TRANSITIONS,
			self::TABLE_FORM_SESSIONS,
			self::TABLE_TICKETS,
			self::TABLE_TICKET_MESSAGES,
			self::TABLE_TICKET_EVENTS,
			self::TABLE_DEVICE_TOKENS,
			self::TABLE_ATTACHMENTS,
			self::TABLE_RATE_LIMITS,
			self::TABLE_ROUTING_RULES,
		);
	}
}
