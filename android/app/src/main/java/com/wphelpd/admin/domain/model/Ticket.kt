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

fun Ticket.statusLabel(): String = when (status) {
    "new"                  -> "New"
    "pending_agent_reply"  -> "Pending Agent Reply"
    "pending_client_reply" -> "Pending Client Reply"
    "resolved"             -> "Resolved"
    "closed"               -> "Closed"
    else                   -> status.replace('_', ' ').replaceFirstChar { it.uppercase() }
}
