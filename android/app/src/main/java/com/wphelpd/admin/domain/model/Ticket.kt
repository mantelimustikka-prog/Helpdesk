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

fun String.canonicalTicketStatus(): String {
    val normalizedStatus = lowercase()
    return when (normalizedStatus) {
        "open"             -> "new"
        "pending",
        "triaged",
        "in_progress"      -> "pending_agent_reply"
        "waiting_customer" -> "pending_client_reply"
        else               -> normalizedStatus
    }
}

fun String.ticketStatusLabel(): String {
    val canonicalStatus = canonicalTicketStatus()
    return when (canonicalStatus) {
        "new"                  -> "New"
        "pending_agent_reply"  -> "Pending Agent Reply"
        "pending_client_reply" -> "Pending Client Reply"
        "resolved"             -> "Resolved"
        "closed"               -> "Closed"
        else                   -> canonicalStatus.replace('_', ' ').replaceFirstChar { it.uppercase() }
    }
}

fun Ticket.statusLabel(): String = status.ticketStatusLabel()
