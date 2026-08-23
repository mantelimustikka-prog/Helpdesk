package com.wphelpd.admin.feature.tickets

import com.wphelpd.admin.domain.model.CurrentUser
import com.wphelpd.admin.domain.model.Pagination
import com.wphelpd.admin.domain.model.TicketDetail
import com.wphelpd.admin.domain.model.Ticket

data class TicketsUiState(
    val siteUrl: String = "",
    val username: String = "",
    val applicationPassword: String = "",
    val wpNonce: String = "",
    val isBootstrapping: Boolean = false,
    val requiresSetup: Boolean = false,
    val isLoading: Boolean = false,
    val errorMessage: String? = null,
    val currentUser: CurrentUser? = null,
    val tickets: List<Ticket> = emptyList(),
    val pagination: Pagination? = null,
    val selectedTicketId: Int? = null,
    val ticketDetail: TicketDetail? = null,
    val isDetailLoading: Boolean = false,
    val detailErrorMessage: String? = null,
    // Reply action
    val replyText: String = "",
    val isReplying: Boolean = false,
    val replyError: String? = null,
    // Status action
    val isUpdatingStatus: Boolean = false,
    val statusUpdateError: String? = null,
    // Internal note action
    val noteText: String = "",
    val isAddingNote: Boolean = false,
    val noteError: String? = null
) {
    val canSubmit: Boolean = !isLoading && siteUrl.isNotBlank() && username.isNotBlank() && applicationPassword.isNotBlank()
    val isDetailActionInProgress: Boolean = isReplying || isUpdatingStatus || isAddingNote
}
