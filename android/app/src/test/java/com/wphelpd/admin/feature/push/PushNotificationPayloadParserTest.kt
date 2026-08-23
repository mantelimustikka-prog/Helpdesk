package com.wphelpd.admin.feature.push

import com.google.common.truth.Truth.assertThat
import org.junit.Test

class PushNotificationPayloadParserTest {
    @Test
    fun parse_acceptsSupportedEventAndTicketId() {
        val payload = PushNotificationPayloadParser.parse(
            mapOf(
                "event_type" to "ticket_created",
                "ticket_id" to "101",
                "deep_link" to "wphelpd://ticket/101",
                "notification_id" to "ticket_created:101"
            )
        )

        assertThat(payload).isNotNull()
        assertThat(payload!!.eventType).isEqualTo("ticket_created")
        assertThat(payload.ticketId).isEqualTo(101)
        assertThat(payload.deepLink).isEqualTo("wphelpd://ticket/101")
        assertThat(payload.notificationId).isEqualTo("ticket_created:101")
    }

    @Test
    fun parse_rejectsUnsupportedEventOrInvalidTicketId() {
        val unsupported = PushNotificationPayloadParser.parse(
            mapOf("event_type" to "status_changed", "ticket_id" to "100")
        )
        val invalidId = PushNotificationPayloadParser.parse(
            mapOf("event_type" to "ticket_replied", "ticket_id" to "0")
        )

        assertThat(unsupported).isNull()
        assertThat(invalidId).isNull()
    }

    @Test
    fun parse_fallbackNotificationId_isUniqueAcrossMessages() {
        val payload1 = PushNotificationPayloadParser.parse(
            mapOf("event_type" to "ticket_replied", "ticket_id" to "42")
        )
        val payload2 = PushNotificationPayloadParser.parse(
            mapOf("event_type" to "ticket_replied", "ticket_id" to "42")
        )

        assertThat(payload1).isNotNull()
        assertThat(payload2).isNotNull()
        assertThat(payload1!!.notificationId).isNotEmpty()
        assertThat(payload2!!.notificationId).isNotEmpty()
        assertThat(payload1.notificationId).isNotEqualTo(payload2.notificationId)
    }
}
