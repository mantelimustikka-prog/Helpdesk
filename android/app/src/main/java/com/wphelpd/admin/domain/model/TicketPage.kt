package com.wphelpd.admin.domain.model

data class TicketPage(
    val tickets: List<Ticket>,
    val pagination: Pagination?
)
