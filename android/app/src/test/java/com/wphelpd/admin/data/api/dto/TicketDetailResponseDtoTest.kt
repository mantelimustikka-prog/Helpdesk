package com.wphelpd.admin.data.api.dto

import com.google.common.truth.Truth.assertThat
import com.google.gson.Gson
import org.junit.Test

class TicketDetailResponseDtoTest {
    @Test
    fun toTicketDetail_parsesNestedThreadAlias() {
        val response = Gson().fromJson(
            """
            {
              "success": true,
              "data": {
                "id": 101,
                "ticket_no": "HD-000101",
                "subject": "Login issue",
                "status": "open",
                "thread": [
                  {
                    "id": 9001,
                    "author_type": "guest",
                    "body": "I cannot sign in."
                  }
                ]
              }
            }
            """.trimIndent(),
            TicketDetailResponseDto::class.java
        )

        val detail = response.toTicketDetail()

        assertThat(detail.ticket.id).isEqualTo(101)
        assertThat(detail.thread).hasSize(1)
        assertThat(detail.thread.single().body).isEqualTo("I cannot sign in.")
    }

    @Test
    fun toTicketDetail_parsesNestedConversationAlias() {
        val response = Gson().fromJson(
            """
            {
              "success": true,
              "data": {
                "id": 102,
                "ticket_no": "HD-000102",
                "subject": "Shipping question",
                "status": "pending_client_reply",
                "conversation": [
                  {
                    "id": 9002,
                    "author_type": "agent",
                    "body": "Can you share your order number?"
                  }
                ]
              }
            }
            """.trimIndent(),
            TicketDetailResponseDto::class.java
        )

        val detail = response.toTicketDetail()

        assertThat(detail.ticket.id).isEqualTo(102)
        assertThat(detail.thread).hasSize(1)
        assertThat(detail.thread.single().authorType).isEqualTo("agent")
    }
}
