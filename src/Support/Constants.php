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

	// General tab option keys.
	public const OPTION_GENERAL_TICKET_NUMBER_START  = 'hd_general_ticket_number_start';
	public const OPTION_GENERAL_TICKET_NUMBER_INC    = 'hd_general_ticket_number_increment';
	public const OPTION_GENERAL_DEFAULT_STATUS       = 'hd_general_default_status';
	public const OPTION_GENERAL_DEFAULT_PRIORITY     = 'hd_general_default_priority';
	public const OPTION_GENERAL_AUTO_RESOLVE_DAYS    = 'hd_general_auto_resolve_days';
	public const OPTION_GENERAL_AUTO_CLOSE_DAYS      = 'hd_general_auto_close_days';
	public const OPTION_GENERAL_ALLOW_GUEST          = 'hd_general_allow_guest_tickets';
	public const OPTION_GENERAL_REQUIRE_TOPIC        = 'hd_general_require_topic';
	public const OPTION_GENERAL_AUTO_ASSIGN_MODE     = 'hd_general_auto_assign_mode';
	public const OPTION_GENERAL_TIMEZONE_MODE        = 'hd_general_timezone_mode';
	public const OPTION_GENERAL_DATE_FORMAT          = 'hd_general_date_format';
	public const OPTION_GENERAL_RETENTION_DAYS       = 'hd_data_retention_days';

	// Integration – Email option keys.
	public const OPTION_EMAIL_FROM_NAME       = 'hd_email_from_name';
	public const OPTION_EMAIL_FROM_ADDRESS    = 'hd_email_from_address';
	public const OPTION_EMAIL_REPLY_TO        = 'hd_email_reply_to_address';
	public const OPTION_EMAIL_HEADER_ENABLED  = 'hd_email_header_enabled';
	public const OPTION_EMAIL_FOOTER_ENABLED  = 'hd_email_footer_enabled';

	// Integration – Push/FCM option keys.
	public const OPTION_PUSH_ENABLED               = 'hd_push_enabled';
	public const OPTION_FCM_PROJECT_ID             = 'hd_fcm_project_id';
	public const OPTION_FCM_SERVICE_ACCOUNT_JSON   = 'hd_fcm_service_account_json';
	public const OPTION_FCM_MODE                   = 'hd_fcm_mode';
	public const OPTION_PUSH_TICKET_EVENTS         = 'hd_push_ticket_events';

	// Integration – Android/API option keys.
	public const OPTION_API_ENABLED                    = 'hd_api_enabled';
	public const OPTION_API_REQUIRE_APP_PASSWORDS      = 'hd_api_require_application_passwords';
	public const OPTION_API_RATE_LIMIT                 = 'hd_api_rate_limit_per_minute';
	public const OPTION_API_LOG_REQUESTS               = 'hd_api_log_requests';
	public const OPTION_API_ALLOWED_ORIGINS            = 'hd_api_allowed_origins';

	// Email template option keys.
	public const OPTION_EMAIL_TEMPLATE_TICKET_CREATED_SUBJECT       = 'hd_email_tpl_ticket_created_subject';
	public const OPTION_EMAIL_TEMPLATE_TICKET_CREATED_BODY          = 'hd_email_tpl_ticket_created_body';
	public const OPTION_EMAIL_TEMPLATE_TICKET_REPLY_SUBJECT         = 'hd_email_tpl_ticket_reply_subject';
	public const OPTION_EMAIL_TEMPLATE_TICKET_REPLY_BODY            = 'hd_email_tpl_ticket_reply_body';
	public const OPTION_EMAIL_TEMPLATE_STATUS_CHANGED_SUBJECT       = 'hd_email_tpl_status_changed_subject';
	public const OPTION_EMAIL_TEMPLATE_STATUS_CHANGED_BODY          = 'hd_email_tpl_status_changed_body';
	public const OPTION_EMAIL_TEMPLATE_TICKET_CREATED_ADMIN_SUBJECT = 'hd_email_tpl_ticket_created_admin_subject';
	public const OPTION_EMAIL_TEMPLATE_TICKET_CREATED_ADMIN_BODY    = 'hd_email_tpl_ticket_created_admin_body';

	// Appearance tab option keys.
	public const OPTION_APPEARANCE_ADMIN_REPLY_COLOR  = 'hd_appearance_admin_reply_color';
	public const OPTION_APPEARANCE_CLIENT_REPLY_COLOR = 'hd_appearance_client_reply_color';

	// Frontend page option keys (per-site, stored in blog options).
	public const OPTION_PAGE_INDEX      = 'hd_page_id_index';
	public const OPTION_PAGE_NEW        = 'hd_page_id_new';
	public const OPTION_PAGE_MEMBER_NEW = 'hd_page_id_member_new';

	// Rewrite flush version — bump to trigger a one-time flush.
	public const REWRITE_VERSION        = '4';
	public const OPTION_REWRITE_VERSION = 'hd_rewrite_version';
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
			// General tab.
			self::OPTION_GENERAL_TICKET_NUMBER_START,
			self::OPTION_GENERAL_TICKET_NUMBER_INC,
			self::OPTION_GENERAL_DEFAULT_STATUS,
			self::OPTION_GENERAL_DEFAULT_PRIORITY,
			self::OPTION_GENERAL_AUTO_RESOLVE_DAYS,
			self::OPTION_GENERAL_AUTO_CLOSE_DAYS,
			self::OPTION_GENERAL_ALLOW_GUEST,
			self::OPTION_GENERAL_REQUIRE_TOPIC,
			self::OPTION_GENERAL_AUTO_ASSIGN_MODE,
			self::OPTION_GENERAL_TIMEZONE_MODE,
			self::OPTION_GENERAL_DATE_FORMAT,
			self::OPTION_GENERAL_RETENTION_DAYS,
			// Integration – Email.
			self::OPTION_EMAIL_FROM_NAME,
			self::OPTION_EMAIL_FROM_ADDRESS,
			self::OPTION_EMAIL_REPLY_TO,
			self::OPTION_EMAIL_HEADER_ENABLED,
			self::OPTION_EMAIL_FOOTER_ENABLED,
			// Integration – Push/FCM.
			self::OPTION_PUSH_ENABLED,
			self::OPTION_FCM_PROJECT_ID,
			self::OPTION_FCM_SERVICE_ACCOUNT_JSON,
			self::OPTION_FCM_MODE,
			self::OPTION_PUSH_TICKET_EVENTS,
			// Integration – API.
			self::OPTION_API_ENABLED,
			self::OPTION_API_REQUIRE_APP_PASSWORDS,
			self::OPTION_API_RATE_LIMIT,
			self::OPTION_API_LOG_REQUESTS,
			self::OPTION_API_ALLOWED_ORIGINS,
			self::OPTION_EMAIL_TEMPLATE_TICKET_CREATED_SUBJECT,
			self::OPTION_EMAIL_TEMPLATE_TICKET_CREATED_BODY,
			self::OPTION_EMAIL_TEMPLATE_TICKET_REPLY_SUBJECT,
			self::OPTION_EMAIL_TEMPLATE_TICKET_REPLY_BODY,
			self::OPTION_EMAIL_TEMPLATE_STATUS_CHANGED_SUBJECT,
			self::OPTION_EMAIL_TEMPLATE_STATUS_CHANGED_BODY,
			self::OPTION_EMAIL_TEMPLATE_TICKET_CREATED_ADMIN_SUBJECT,
			self::OPTION_EMAIL_TEMPLATE_TICKET_CREATED_ADMIN_BODY,
			// Appearance tab.
			self::OPTION_APPEARANCE_ADMIN_REPLY_COLOR,
			self::OPTION_APPEARANCE_CLIENT_REPLY_COLOR,
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
