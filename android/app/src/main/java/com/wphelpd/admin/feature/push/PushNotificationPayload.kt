package com.wphelpd.admin.feature.push

data class PushNotificationPayload(
    val eventType: String,
    val ticketId: Int,
    val deepLink: String,
    val notificationId: String
)

object PushNotificationPayloadParser {
    private val supportedEvents = setOf("ticket_created", "ticket_replied", "status_changed", "ticket_assigned")

    fun parse(data: Map<String, String>): PushNotificationPayload? {
        val eventType = (data["event_type"] ?: data["event"])?.trim()?.lowercase() ?: return null
        if (eventType !in supportedEvents) return null
        val ticketId = data["ticket_id"]?.trim()?.toIntOrNull()?.takeIf { it > 0 } ?: return null
        val deepLink = data["deep_link"]?.trim().takeUnless { it.isNullOrEmpty() }
            ?: "wphelpd://ticket/$ticketId"
        val notificationId = data["notification_id"]?.trim().takeUnless { it.isNullOrEmpty() }
            ?: java.util.UUID.randomUUID().toString()
        return PushNotificationPayload(
            eventType = eventType,
            ticketId = ticketId,
            deepLink = deepLink,
            notificationId = notificationId
        )
    }
}
