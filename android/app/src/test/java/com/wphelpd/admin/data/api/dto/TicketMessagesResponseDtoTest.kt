package com.wphelpd.admin.data.api.dto

import com.google.common.truth.Truth.assertThat
import com.google.gson.JsonParser
import org.junit.Test

class TicketMessagesResponseDtoTest {
    @Test
    fun toThread_prefersTopLevelItems() {
        val response = TicketMessagesResponseDto(
            success = true,
            items = listOf(
                TicketThreadEntryDto(
                    id = 1001,
                    authorType = "agent",
                    authorName = "Support Agent",
                    body = "From items."
                )
            ),
            messages = listOf(
                TicketThreadEntryDto(
                    id = 1002,
                    authorType = "customer",
                    authorName = "Customer",
                    body = "From messages."
                )
            ),
            data = JsonParser.parseString(
                """
                [
                  {
                    "id": 1003,
                    "author_type": "agent",
                    "body": "From data array."
                  }
                ]
                """.trimIndent()
            )
        )

        val thread = response.toThread()

        assertThat(thread).hasSize(1)
        assertThat(thread.single().id).isEqualTo(1001)
        assertThat(thread.single().body).isEqualTo("From items.")
    }

    @Test
    fun toThread_usesTopLevelMessagesWhenItemsAreEmpty() {
        val response = TicketMessagesResponseDto(
            success = true,
            items = emptyList(),
            messages = listOf(
                TicketThreadEntryDto(
                    id = 1011,
                    authorType = "customer",
                    authorName = "Customer",
                    body = "From messages."
                )
            )
        )

        val thread = response.toThread()

        assertThat(thread).hasSize(1)
        assertThat(thread.single().id).isEqualTo(1011)
        assertThat(thread.single().body).isEqualTo("From messages.")
    }

    @Test
    fun toThread_parsesNestedDataItemsAliases() {
        val response = TicketMessagesResponseDto(
            success = true,
            data = JsonParser.parseString(
                """
                {
                  "items": [
                    {
                      "message_id": "1021",
                      "authorType": "agent",
                      "authorName": "Support Agent",
                      "message": "From nested items.",
                      "createdAt": "2026-08-22T12:46:00Z",
                      "isInternal": "true"
                    }
                  ]
                }
                """.trimIndent()
            )
        )

        val thread = response.toThread()

        assertThat(thread).hasSize(1)
        val entry = thread.single()
        assertThat(entry.id).isEqualTo(1021)
        assertThat(entry.authorType).isEqualTo("agent")
        assertThat(entry.authorName).isEqualTo("Support Agent")
        assertThat(entry.body).isEqualTo("From nested items.")
        assertThat(entry.createdAt).isEqualTo("2026-08-22T12:46:00Z")
        assertThat(entry.isInternal).isTrue()
    }

    @Test
    fun toThread_parsesNestedDataMessages() {
        val response = TicketMessagesResponseDto(
            success = true,
            data = JsonParser.parseString(
                """
                {
                  "messages": [
                    {
                      "id": 1031,
                      "author_type": "customer",
                      "author_name": "Customer",
                      "body": "From nested messages.",
                      "created_at": "2026-08-22T13:00:00Z",
                      "is_internal": 0
                    }
                  ]
                }
                """.trimIndent()
            )
        )

        val thread = response.toThread()

        assertThat(thread).hasSize(1)
        val entry = thread.single()
        assertThat(entry.id).isEqualTo(1031)
        assertThat(entry.authorType).isEqualTo("customer")
        assertThat(entry.authorName).isEqualTo("Customer")
        assertThat(entry.body).isEqualTo("From nested messages.")
        assertThat(entry.createdAt).isEqualTo("2026-08-22T13:00:00Z")
        assertThat(entry.isInternal).isFalse()
    }

    @Test
    fun toThread_parsesRawArrayPayloadInData() {
        val response = TicketMessagesResponseDto(
            success = true,
            data = JsonParser.parseString(
                """
                [
                  {
                    "message_id": 1041,
                    "author_type": "agent",
                    "author_name": "Support Agent",
                    "message": "From raw data array.",
                    "created_at": "2026-08-22T13:05:00Z",
                    "is_internal": 1
                  }
                ]
                """.trimIndent()
            )
        )

        val thread = response.toThread()

        assertThat(thread).hasSize(1)
        val entry = thread.single()
        assertThat(entry.id).isEqualTo(1041)
        assertThat(entry.authorType).isEqualTo("agent")
        assertThat(entry.authorName).isEqualTo("Support Agent")
        assertThat(entry.body).isEqualTo("From raw data array.")
        assertThat(entry.createdAt).isEqualTo("2026-08-22T13:05:00Z")
        assertThat(entry.isInternal).isTrue()
    }
}
