package com.wphelpd.admin.domain.model

data class Ticket(
    val id: Int,
    val ticketNo: String,
    val subject: String,
    val status: String,
    val priority: String?,
    val customerName: String?,
    val customerEmail: String?,
    val createdAt: String?,
    val updatedAt: String?,
    val messageCount: Int,
    val lastMessageExcerpt: String?
)
