package com.wphelpd.admin.domain.model

data class AppearanceColors(
    val adminReplyColor: String = "",
    val clientReplyColor: String = "",
    val statusNewColor: String = "",
    val statusPendingAgentColor: String = "",
    val statusPendingClientColor: String = "",
    val statusResolvedColor: String = "",
    val statusClosedColor: String = ""
) {
    fun statusColor(status: String): String = when (status.lowercase()) {
        "new",
        "open"                  -> statusNewColor
        "pending_agent_reply",
        "pending",
        "triaged",
        "in_progress"           -> statusPendingAgentColor
        "pending_client_reply",
        "waiting_customer"      -> statusPendingClientColor
        "resolved"              -> statusResolvedColor
        "closed"                -> statusClosedColor
        else                    -> ""
    }

    companion object {
        val Empty = AppearanceColors()
    }
}
