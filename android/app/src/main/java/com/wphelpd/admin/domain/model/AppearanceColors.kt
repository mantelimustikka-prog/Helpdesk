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
    fun statusColor(status: String): String = when (status.canonicalTicketStatus()) {
        "new"                  -> statusNewColor
        "pending_agent_reply"  -> statusPendingAgentColor
        "pending_client_reply" -> statusPendingClientColor
        "resolved"             -> statusResolvedColor
        "closed"               -> statusClosedColor
        else                   -> ""
    }

    companion object {
        val Empty = AppearanceColors()
    }
}
