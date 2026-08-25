package com.wphelpd.admin.data.api.dto

import com.google.gson.annotations.SerializedName

data class NotificationsSinceResponseDto(
    @SerializedName("success") val success: Boolean,
    @SerializedName("new_tickets") val newTickets: List<NotificationTicketDto> = emptyList(),
    @SerializedName("new_replies") val newReplies: List<NotificationReplyDto> = emptyList()
)

data class NotificationTicketDto(
    @SerializedName("id") val id: Int,
    @SerializedName("ticket_no") val ticketNo: String,
    @SerializedName("subject") val subject: String,
    @SerializedName("status") val status: String,
    @SerializedName("created_at") val createdAt: String
)

data class NotificationReplyDto(
    @SerializedName("id") val id: Int,
    @SerializedName("ticket_id") val ticketId: Int,
    @SerializedName("ticket_no") val ticketNo: String,
    @SerializedName("author") val author: String,
    @SerializedName("message_excerpt") val messageExcerpt: String,
    @SerializedName("created_at") val createdAt: String
)
