package com.wphelpd.admin.domain.model

data class Pagination(
    val page: Int,
    val perPage: Int,
    val total: Int?,
    val totalPages: Int?
)
