package com.wphelpd.admin.feature.tickets

import com.wphelpd.admin.domain.model.CurrentUser
import com.wphelpd.admin.domain.model.Pagination
import com.wphelpd.admin.domain.model.Ticket

data class TicketsUiState(
    val siteUrl: String = "",
    val username: String = "",
    val applicationPassword: String = "",
    val wpNonce: String = "",
    val isLoading: Boolean = false,
    val errorMessage: String? = null,
    val currentUser: CurrentUser? = null,
    val tickets: List<Ticket> = emptyList(),
    val pagination: Pagination? = null
) {
    val canSubmit: Boolean = !isLoading && siteUrl.isNotBlank() && username.isNotBlank() && applicationPassword.isNotBlank()
}
