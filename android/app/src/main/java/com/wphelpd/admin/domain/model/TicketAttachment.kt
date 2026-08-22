package com.wphelpd.admin.domain.model

data class TicketAttachment(
    val id: Int,
    val name: String,
    val url: String,
    val mimeType: String?
)
