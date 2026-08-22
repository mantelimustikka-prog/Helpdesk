package com.wphelpd.admin.data.api.dto

import com.google.gson.annotations.SerializedName
import com.wphelpd.admin.domain.model.Pagination
import com.wphelpd.admin.domain.model.Ticket
import com.wphelpd.admin.domain.model.TicketPage

data class TicketListResponseDto(
    @SerializedName("success") val success: Boolean? = null,
    @SerializedName("data") val data: List<TicketDto>? = null,
    @SerializedName("items") val items: List<TicketDto>? = null,
    @SerializedName("pagination") val pagination: PaginationDto? = null,
    @SerializedName("page") val page: Int? = null,
    @SerializedName("per_page") val perPage: Int? = null
) {
    fun toTicketPage(): TicketPage {
        check(success != false) { "Ticket list request failed." }

        val ticketItems = (data ?: items).orEmpty().map(TicketDto::toModel)
        val resolvedPagination = pagination?.toModel()
            ?: if (page != null && perPage != null) {
                Pagination(
                    page = page,
                    perPage = perPage,
                    total = ticketItems.size,
                    totalPages = null
                )
            } else {
                null
            }

        return TicketPage(
            tickets = ticketItems,
            pagination = resolvedPagination
        )
    }
}

data class TicketDto(
    @SerializedName("id") val id: Int,
    @SerializedName("ticket_no") val ticketNo: String,
    @SerializedName("subject") val subject: String,
    @SerializedName("status") val status: String,
    @SerializedName("priority") val priority: String? = null,
    @SerializedName("customer_name") val customerName: String? = null,
    @SerializedName("customer_email") val customerEmail: String? = null,
    @SerializedName("created_at") val createdAt: String? = null,
    @SerializedName("updated_at") val updatedAt: String? = null,
    @SerializedName("message_count") val messageCount: Int = 0,
    @SerializedName("last_message_excerpt") val lastMessageExcerpt: String? = null
) {
    fun toModel(): Ticket = Ticket(
        id = id,
        ticketNo = ticketNo,
        subject = subject,
        status = status,
        priority = priority,
        customerName = customerName,
        customerEmail = customerEmail,
        createdAt = createdAt,
        updatedAt = updatedAt,
        messageCount = messageCount,
        lastMessageExcerpt = lastMessageExcerpt
    )
}

data class PaginationDto(
    @SerializedName("page") val page: Int,
    @SerializedName("per_page") val perPage: Int,
    @SerializedName("total") val total: Int,
    @SerializedName("total_pages") val totalPages: Int
) {
    fun toModel(): Pagination = Pagination(
        page = page,
        perPage = perPage,
        total = total,
        totalPages = totalPages
    )
}
