package com.wphelpd.admin.domain.model

data class CurrentUser(
    val id: Int,
    val name: String,
    val email: String,
    val roles: List<String>
)
