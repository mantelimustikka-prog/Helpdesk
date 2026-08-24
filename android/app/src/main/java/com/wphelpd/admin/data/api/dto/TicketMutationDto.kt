package com.wphelpd.admin.data.api.dto

import android.util.Log

import com.google.gson.JsonElement
import com.google.gson.JsonObject
import com.google.gson.annotations.SerializedName
import com.wphelpd.admin.domain.model.TicketThreadEntry

private const val TEMP_DEBUG_TAG = "HelpdeskTempDebug"

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
    @SerializedName("is_internal") val isInternal: JsonElement? = null
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
            isInternal = isInternal.toInternalFlag()
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
    @SerializedName("is_internal") val isInternal: JsonElement? = null
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
    @SerializedName("is_internal") val isInternal: JsonElement? = null,
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
    @SerializedName("is_internal") val isInternal: JsonElement? = null,
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
        val dataShape = data.debugShape()
        items.toThreadOrNull()?.let { thread ->
            logThreadDebug(
                source = "topLevelItems",
                dataShape = dataShape,
                candidateCount = items?.size ?: 0,
                parsedCount = thread.size
            )
            return thread
        }
        messages.toThreadOrNull()?.let { thread ->
            logThreadDebug(
                source = "topLevelMessages",
                dataShape = dataShape,
                candidateCount = messages?.size ?: 0,
                parsedCount = thread.size
            )
            return thread
        }
        val dataEntries = data.toThreadEntriesOrNull()
        dataEntries.toThreadOrNull()?.let { thread ->
            logThreadDebug(
                source = "nestedData",
                dataShape = dataShape,
                candidateCount = dataEntries?.size ?: 0,
                parsedCount = thread.size
            )
            return thread
        }
        logThreadDebug(
            source = "none",
            dataShape = dataShape,
            candidateCount = dataEntries?.size ?: 0,
            parsedCount = 0
        )
        return emptyList()
    }

    private fun logThreadDebug(
        source: String,
        dataShape: String,
        candidateCount: Int,
        parsedCount: Int
    ) {
        Log.d(
            TEMP_DEBUG_TAG,
            "TEMP DEBUG: TicketMessagesResponseDto.toThread sawItems=${items != null} sawMessages=${messages != null} dataShape=$dataShape parsedSource=$source candidateCount=$candidateCount parsedCount=$parsedCount"
        )
    }
}

private fun List<TicketThreadEntryDto>?.toThreadOrNull(): List<TicketThreadEntry>? {
    val entries = this?.mapNotNull(TicketThreadEntryDto::toModelOrNull).orEmpty()
    return entries.ifEmpty { null }
}

private fun TicketThreadEntryDto.toModelOrNull(): TicketThreadEntry? {
    val normalizedId = id.takeIf { it > 0 } ?: return null
    val normalizedAuthorType = (authorType as? String)?.takeIf { it.isNotBlank() } ?: return null
    val normalizedBody = (body as? String)?.takeIf { it.isNotBlank() } ?: return null
    return TicketThreadEntry(
        id = normalizedId,
        authorType = normalizedAuthorType,
        authorName = authorName,
        body = normalizedBody,
        createdAt = createdAt,
        isInternal = isInternal.toInternalFlag()
    )
}

private fun JsonElement?.toThreadEntriesOrNull(): List<TicketThreadEntryDto>? {
    if (this == null || isJsonNull) return null
    return when {
        isJsonObject -> {
            val objectData = asJsonObject
            parseThreadEntriesFromJson(objectData.get("items"))
                ?: parseThreadEntriesFromJson(objectData.get("messages"))
                ?: parseThreadEntriesFromJson(objectData.get("thread"))
                ?: parseThreadEntriesFromJson(objectData.get("entries"))
                ?: objectData.get("data")?.takeIf { it.isJsonArray }?.let { parseThreadEntriesFromJson(it) }
        }
        isJsonArray -> parseThreadEntriesFromJson(this)
        else -> null
    }
}

private fun parseThreadEntriesFromJson(value: JsonElement?): List<TicketThreadEntryDto>? {
    if (value == null || !value.isJsonArray) return null
    val entries = value.asJsonArray
    return entries.mapNotNull { entry ->
        if (!entry.isJsonObject) return@mapNotNull null
        val payload = entry.asJsonObject
        val id = payload.intOrNull("id")
            ?: payload.intOrNull("message_id")
            ?: return@mapNotNull null
        val authorType = payload.stringOrNull("author_type")
            ?: payload.stringOrNull("authorType")
            // "type" is accepted as an alias when author_type/authorType are absent.
            // Expected values: "agent", "customer".
            ?: payload.stringOrNull("type")
            ?: return@mapNotNull null
        val body = payload.stringOrNull("body")
            ?: payload.stringOrNull("message")
            ?: payload.stringOrNull("content")
            ?: return@mapNotNull null
        TicketThreadEntryDto(
            id = id,
            authorType = authorType,
            authorName = payload.stringOrNull("author_name")
                ?: payload.stringOrNull("authorName"),
            body = body,
            createdAt = payload.stringOrNull("created_at")
                ?: payload.stringOrNull("createdAt"),
            isInternal = payload.primitiveOrNull("is_internal")
                ?: payload.primitiveOrNull("isInternal")
                ?: payload.primitiveOrNull("internal")
        )
    }.ifEmpty { null }
}

private fun JsonObject.stringOrNull(name: String): String? {
    val value = get(name) ?: return null
    if (value.isJsonNull || !value.isJsonPrimitive) return null
    return runCatching { value.asString }.getOrNull()
}

private fun JsonObject.intOrNull(name: String): Int? {
    val value = get(name) ?: return null
    if (value.isJsonNull || !value.isJsonPrimitive) return null
    return when {
        value.asJsonPrimitive.isBoolean -> if (value.asBoolean) 1 else 0
        value.asJsonPrimitive.isString -> when (value.asString.trim().lowercase()) {
            "true" -> 1
            "false" -> 0
            else -> runCatching { value.asInt }.getOrNull()
        }
        else -> runCatching { value.asInt }.getOrNull()
    }
}

private fun JsonObject.primitiveOrNull(name: String): JsonElement? {
    val value = get(name) ?: return null
    if (value.isJsonNull || !value.isJsonPrimitive) return null
    return value
}

private fun JsonElement?.toInternalFlag(): Boolean {
    if (this == null || isJsonNull || !isJsonPrimitive) return false
    val primitive = asJsonPrimitive
    return when {
        primitive.isBoolean -> primitive.asBoolean
        primitive.isNumber -> runCatching { primitive.asInt != 0 }.getOrDefault(false)
        primitive.isString -> {
            val normalized = primitive.asString.trim()
            normalized == "1" || normalized.equals("true", ignoreCase = true)
        }
        else -> false
    }
}

private fun JsonElement?.debugShape(): String = when {
    this == null || isJsonNull -> "none"
    isJsonArray -> "data.array"
    isJsonObject -> when {
        asJsonObject.get("items")?.isJsonArray == true -> "data.items"
        asJsonObject.get("messages")?.isJsonArray == true -> "data.messages"
        asJsonObject.get("thread")?.isJsonArray == true -> "data.thread"
        asJsonObject.get("entries")?.isJsonArray == true -> "data.entries"
        asJsonObject.get("data")?.isJsonArray == true -> "data.data"
        else -> "data.object"
    }
    else -> "data.other"
}
