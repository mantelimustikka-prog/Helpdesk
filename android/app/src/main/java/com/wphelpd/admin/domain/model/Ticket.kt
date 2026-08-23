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

fun Ticket.statusLabel(): String {
    val normalizedStatus = status.lowercase()
    return when (normalizedStatus) {
    "new",
    "open"                  -> "New"
    "pending_agent_reply",
    "pending",
    "triaged",
    "in_progress"           -> "Pending Agent Reply"
    "pending_client_reply",
    "waiting_customer"      -> "Pending Client Reply"
    "resolved"              -> "Resolved"
    "closed"                -> "Closed"
    else                    -> normalizedStatus.replace('_', ' ').replaceFirstChar { it.uppercase() }
    }
}
