package com.wphelpd.admin.domain.model

data class TicketDetail(
    val ticket: Ticket,
    val assignedToName: String?,
    val thread: List<TicketThreadEntry>,
    val attachments: List<TicketAttachment>
)
