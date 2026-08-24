package com.wphelpd.admin.data.api.dto
import com.google.gson.annotations.SerializedName
import com.google.gson.JsonElement
import com.google.gson.JsonObject
import com.wphelpd.admin.domain.model.Ticket
import com.wphelpd.admin.domain.model.TicketAttachment
import com.wphelpd.admin.domain.model.TicketDetail
import com.wphelpd.admin.domain.model.TicketThreadEntry

data class TicketDetailResponseDto(
    @SerializedName("success") val success: Boolean? = null,
    @SerializedName("data") val data: TicketDetailDto? = null,
    @SerializedName("id") val id: Int? = null,
    @SerializedName("ticket_no") val ticketNo: String? = null,
    @SerializedName("subject") val subject: String? = null,
    @SerializedName("status") val status: String? = null,
    @SerializedName("priority") val priority: String? = null,
    @SerializedName("customer_name") val customerName: String? = null,
    @SerializedName("customer_email") val customerEmail: String? = null,
    @SerializedName("requester_name") val requesterName: String? = null,
    @SerializedName("requester_email") val requesterEmail: String? = null,
    @SerializedName("customer") val customer: TicketCustomerDto? = null,
    @SerializedName("assigned_to") val assignedTo: JsonElement? = null,
    @SerializedName("created_at") val createdAt: String? = null,
    @SerializedName("updated_at") val updatedAt: String? = null,
    @SerializedName("message_count") val messageCount: Int? = null,
    @SerializedName("messages") val messages: List<TicketThreadEntryDto>? = null,
    @SerializedName("attachments") val attachments: List<TicketAttachmentDto>? = null
) {
    fun toTicketDetail(): TicketDetail {
        check(success != false) { "Ticket detail request failed." }
        return data?.toModel()
            ?: TicketDetailDto(
                id = requireNotNull(id) { "Ticket detail response did not include an id." },
                ticketNo = requireNotNull(ticketNo) { "Ticket detail response did not include ticket_no." },
                subject = requireNotNull(subject) { "Ticket detail response did not include subject." },
                status = requireNotNull(status) { "Ticket detail response did not include status." },
                priority = priority,
                customerName = customerName,
                customerEmail = customerEmail,
                requesterName = requesterName,
                requesterEmail = requesterEmail,
                customer = customer,
                assignedTo = assignedTo,
                createdAt = createdAt,
                updatedAt = updatedAt,
                messageCount = messageCount ?: 0,
                messages = messages,
                attachments = attachments
            ).toModel()
    }
}

data class TicketDetailDto(
    @SerializedName("id") val id: Int,
    @SerializedName("ticket_no") val ticketNo: String,
    @SerializedName("subject") val subject: String,
    @SerializedName("status") val status: String,
    @SerializedName("priority") val priority: String? = null,
    @SerializedName("customer_name") val customerName: String? = null,
    @SerializedName("customer_email") val customerEmail: String? = null,
    @SerializedName("requester_name") val requesterName: String? = null,
    @SerializedName("requester_email") val requesterEmail: String? = null,
    @SerializedName("customer") val customer: TicketCustomerDto? = null,
    @SerializedName("assigned_to") val assignedTo: JsonElement? = null,
    @SerializedName("created_at") val createdAt: String? = null,
    @SerializedName("updated_at") val updatedAt: String? = null,
    @SerializedName("message_count") val messageCount: Int = 0,
    @SerializedName("messages") val messages: List<TicketThreadEntryDto>? = null,
    @SerializedName("attachments") val attachments: List<TicketAttachmentDto>? = null
) {
    fun toModel(): TicketDetail {
        val threadEntries = messages.orEmpty()
        return TicketDetail(
            ticket = Ticket(
                id = id,
                ticketNo = ticketNo,
                subject = subject,
                status = status,
                priority = priority,
                customerName = customer?.name ?: customerName ?: requesterName,
                customerEmail = customer?.email ?: customerEmail ?: requesterEmail,
                createdAt = createdAt,
                updatedAt = updatedAt,
                messageCount = messageCount,
                lastMessageExcerpt = threadEntries.lastOrNull()?.body
            ),
            assignedToName = assignedToName(),
            thread = threadEntries.map(TicketThreadEntryDto::toModel),
            attachments = attachments.orEmpty().map(TicketAttachmentDto::toModel)
        )
    }
}

private fun TicketDetailDto.assignedToName(): String? {
    val value = assignedTo ?: return null
    return when {
        value.isJsonObject -> value.asJsonObject.stringOrNull("name")
        value.isJsonPrimitive -> value.asString
        else -> null
    }
}

private fun JsonObject.stringOrNull(name: String): String? {
    val value = get(name) ?: return null
    return if (value.isJsonNull) null else value.asString
}

data class TicketCustomerDto(
    @SerializedName("id") val id: Int? = null,
    @SerializedName("name") val name: String? = null,
    @SerializedName("email") val email: String? = null
)

data class TicketThreadEntryDto(
    @SerializedName(value = "id", alternate = ["message_id"]) val id: Int,
    // Aliases kept in sync with parseThreadEntriesFromJson in TicketMutationDto.kt.
    @SerializedName(value = "author_type", alternate = ["authorType", "role"]) val authorType: String,
    @SerializedName(value = "author_name", alternate = ["authorName"]) val authorName: String? = null,
    @SerializedName(value = "body", alternate = ["message", "content", "text"]) val body: String,
    @SerializedName(value = "created_at", alternate = ["createdAt"]) val createdAt: String? = null,
    @SerializedName(value = "is_internal", alternate = ["isInternal"]) val isInternal: JsonElement? = null
) {
    fun toModel(): TicketThreadEntry = TicketThreadEntry(
        id = id,
        authorType = authorType,
        authorName = authorName,
        body = body,
        createdAt = createdAt,
        isInternal = isInternal.toInternalFlag()
    )
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

data class TicketAttachmentDto(
    @SerializedName("id") val id: Int,
    @SerializedName("name") val name: String,
    @SerializedName("url") val url: String,
    @SerializedName("mime_type") val mimeType: String? = null
) {
    fun toModel(): TicketAttachment = TicketAttachment(
        id = id,
        name = name,
        url = url,
        mimeType = mimeType
    )
}
