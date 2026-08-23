package com.wphelpd.admin.data.api.dto

import com.google.gson.JsonElement
import com.google.gson.JsonObject
import com.google.gson.annotations.SerializedName
import com.wphelpd.admin.domain.model.TicketThreadEntry

data class ReplyRequestDto(
    @SerializedName("message") val message: String,
    // Keep both fields for contract + current plugin compatibility.
    @SerializedName("body") val body: String = message,
    @SerializedName("internal_note") val internalNote: Boolean = false
)

data class StatusUpdateRequestDto(
    @SerializedName("status") val status: String
)

data class NoteRequestDto(
    @SerializedName("note") val note: String,
    // Keep both fields for contract + current plugin compatibility.
    @SerializedName("body") val body: String = note
)

data class ReplyResultDto(
    @SerializedName("message_id") val messageId: Int? = null,
    @SerializedName("ticket_id") val ticketId: Int? = null,
    @SerializedName("created_at") val createdAt: String? = null,
    @SerializedName("id") val id: Int? = null,
    @SerializedName("author_type") val authorType: String? = null,
    @SerializedName("author_name") val authorName: String? = null,
    @SerializedName("body") val body: String? = null,
    @SerializedName("is_internal") val isInternal: Int? = null
) {
    fun toThreadEntryOrNull(): TicketThreadEntry? {
        if (id == null || authorType == null || body == null) {
            return null
        }
        return TicketThreadEntry(
            id = id,
            authorType = authorType,
            authorName = authorName,
            body = body,
            createdAt = createdAt,
            isInternal = isInternal == 1
        )
    }
}

data class ReplyResponseDto(
    @SerializedName("success") val success: Boolean? = null,
    @SerializedName("data") val data: ReplyResultDto? = null,
    @SerializedName("message") val message: String? = null,
    @SerializedName("message_id") val messageId: Int? = null,
    @SerializedName("ticket_id") val ticketId: Int? = null,
    @SerializedName("created_at") val createdAt: String? = null,
    @SerializedName("id") val id: Int? = null,
    @SerializedName("author_type") val authorType: String? = null,
    @SerializedName("author_name") val authorName: String? = null,
    @SerializedName("body") val body: String? = null,
    @SerializedName("is_internal") val isInternal: Int? = null
) {
    fun requireResult(): ReplyResultDto {
        check(success != false) { message ?: "Reply failed." }
        return data ?: ReplyResultDto(
            messageId = messageId,
            ticketId = ticketId,
            createdAt = createdAt,
            id = id,
            authorType = authorType,
            authorName = authorName,
            body = body,
            isInternal = isInternal
        )
    }
}

data class StatusUpdateResultDto(
    @SerializedName("ticket_id") val ticketId: Int? = null,
    @SerializedName("status") val status: String? = null,
    @SerializedName("id") val id: Int? = null
)

data class StatusUpdateResponseDto(
    @SerializedName("success") val success: Boolean? = null,
    @SerializedName("data") val data: StatusUpdateResultDto? = null,
    @SerializedName("message") val message: String? = null,
    @SerializedName("ticket_id") val ticketId: Int? = null,
    @SerializedName("status") val status: String? = null,
    @SerializedName("id") val id: Int? = null
) {
    fun requireResult(): StatusUpdateResultDto {
        check(success != false) { message ?: "Status update failed." }
        return data ?: StatusUpdateResultDto(
            ticketId = ticketId,
            status = status,
            id = id
        )
    }
}

data class NoteResultDto(
    @SerializedName("note_id") val noteId: Int? = null,
    @SerializedName("ticket_id") val ticketId: Int? = null,
    @SerializedName("id") val id: Int? = null,
    @SerializedName("author_type") val authorType: String? = null,
    @SerializedName("author_name") val authorName: String? = null,
    @SerializedName("body") val body: String? = null,
    @SerializedName("is_internal") val isInternal: Int? = null,
    @SerializedName("created_at") val createdAt: String? = null
) {
    fun toThreadEntryOrNull(): TicketThreadEntry? {
        if (id == null || authorType == null || body == null) {
            return null
        }
        return TicketThreadEntry(
            id = id,
            authorType = authorType,
            authorName = authorName,
            body = body,
            createdAt = createdAt,
            isInternal = true
        )
    }
}

data class NoteResponseDto(
    @SerializedName("success") val success: Boolean? = null,
    @SerializedName("data") val data: NoteResultDto? = null,
    @SerializedName("message") val message: String? = null,
    @SerializedName("note_id") val noteId: Int? = null,
    @SerializedName("ticket_id") val ticketId: Int? = null,
    @SerializedName("id") val id: Int? = null,
    @SerializedName("author_type") val authorType: String? = null,
    @SerializedName("author_name") val authorName: String? = null,
    @SerializedName("body") val body: String? = null,
    @SerializedName("is_internal") val isInternal: Int? = null,
    @SerializedName("created_at") val createdAt: String? = null
) {
    fun requireResult(): NoteResultDto {
        check(success != false) { message ?: "Adding note failed." }
        return data ?: NoteResultDto(
            noteId = noteId,
            ticketId = ticketId,
            id = id,
            authorType = authorType,
            authorName = authorName,
            body = body,
            isInternal = isInternal,
            createdAt = createdAt
        )
    }
}

data class TicketMessagesResponseDto(
    @SerializedName("success") val success: Boolean? = null,
    @SerializedName("data") val data: JsonElement? = null,
    @SerializedName("items") val items: List<TicketThreadEntryDto>? = null,
    @SerializedName("messages") val messages: List<TicketThreadEntryDto>? = null
) {
    fun toThread(): List<TicketThreadEntry> {
        check(success != false) { "Ticket messages request failed." }
        val threadEntries = items
            ?: messages
            ?: data.toThreadEntriesOrNull()
        return threadEntries.orEmpty().map(TicketThreadEntryDto::toModel)
    }
}

private fun JsonElement?.toThreadEntriesOrNull(): List<TicketThreadEntryDto>? {
    if (this == null || isJsonNull) return null
    if (isJsonArray) return parseThreadEntriesFromJson(this)
    if (!isJsonObject) return null

    val objectData = asJsonObject
    return parseThreadEntriesFromJson(objectData.get("items"))
        ?: parseThreadEntriesFromJson(objectData.get("messages"))
}

private fun parseThreadEntriesFromJson(value: JsonElement?): List<TicketThreadEntryDto>? {
    if (value == null || !value.isJsonArray) return null
    val entries = value.asJsonArray
    if (entries.size() == 0) return emptyList()
    return entries.mapNotNull { entry ->
        if (!entry.isJsonObject) return@mapNotNull null
        val payload = entry.asJsonObject
        val id = payload.intOrNull("id") ?: return@mapNotNull null
        val authorType = payload.stringOrNull("author_type") ?: return@mapNotNull null
        val body = payload.stringOrNull("body") ?: return@mapNotNull null
        TicketThreadEntryDto(
            id = id,
            authorType = authorType,
            authorName = payload.stringOrNull("author_name"),
            body = body,
            createdAt = payload.stringOrNull("created_at"),
            isInternal = payload.intOrNull("is_internal")
        )
    }.ifEmpty { null }
}

private fun JsonObject.stringOrNull(name: String): String? {
    val value = get(name) ?: return null
    return if (value.isJsonNull) null else value.asString
}

private fun JsonObject.intOrNull(name: String): Int? {
    val value = get(name) ?: return null
    return if (value.isJsonNull) null else value.asInt
}
