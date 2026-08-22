package com.wphelpd.admin.domain.model

data class TicketThreadEntry(
    val id: Int,
    val authorType: String,
    val authorName: String?,
    val body: String,
    val createdAt: String?,
    val isInternal: Boolean
)
