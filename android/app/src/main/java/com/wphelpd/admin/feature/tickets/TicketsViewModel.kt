package com.wphelpd.admin.feature.tickets

import androidx.lifecycle.ViewModel
import androidx.lifecycle.ViewModelProvider
import androidx.lifecycle.viewModelScope
import com.wphelpd.admin.core.network.AuthConfig
import com.wphelpd.admin.core.network.NetworkResult
import com.wphelpd.admin.data.repository.HelpdeskRepository
import com.wphelpd.admin.domain.model.Ticket
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch

class TicketsViewModel(
    private val repository: HelpdeskRepository = HelpdeskRepository()
) : ViewModel() {
    private val _uiState = MutableStateFlow(TicketsUiState())
    val uiState: StateFlow<TicketsUiState> = _uiState.asStateFlow()

    fun updateSiteUrl(value: String) = updateState { copy(siteUrl = value) }
    fun updateUsername(value: String) = updateState { copy(username = value) }
    fun updateApplicationPassword(value: String) = updateState { copy(applicationPassword = value) }
    fun updateWpNonce(value: String) = updateState { copy(wpNonce = value) }

    fun connectAndLoadTickets() {
        val state = _uiState.value
        if (!state.siteUrl.trim().startsWith("https://")) {
            updateState { copy(errorMessage = "Use an HTTPS site URL for WP HelpD.") }
            return
        }

        val config = state.toAuthConfig()

        viewModelScope.launch {
            updateState {
                copy(
                    isLoading = true,
                    errorMessage = null,
                    tickets = emptyList(),
                    pagination = null,
                    selectedTicketId = null,
                    ticketDetail = null,
                    isDetailLoading = false,
                    detailErrorMessage = null,
                    actionMessage = null
                )
            }

            when (val authResult = repository.authCheck(config)) {
                is NetworkResult.Failure -> {
                    updateState {
                        copy(isLoading = false, currentUser = null, errorMessage = authResult.message)
                    }
                }
                is NetworkResult.Success -> {
                    updateState { copy(currentUser = authResult.value) }
                    refreshTickets(config)
                }
            }
        }
    }

    fun refreshTickets() {
        viewModelScope.launch {
            refreshTickets(_uiState.value.toAuthConfig())
        }
    }

    fun selectTicket(ticketId: Int) {
        updateState {
            copy(
                selectedTicketId = ticketId,
                detailErrorMessage = null,
                actionMessage = null
            )
        }
        viewModelScope.launch {
            loadTicketDetail(_uiState.value.toAuthConfig(), ticketId)
        }
    }

    fun refreshSelectedTicket() {
        val ticketId = _uiState.value.selectedTicketId ?: return
        viewModelScope.launch {
            loadTicketDetail(_uiState.value.toAuthConfig(), ticketId)
        }
    }

    fun submitReply(message: String) {
        val ticketId = _uiState.value.selectedTicketId ?: return
        val trimmed = message.trim()
        if (trimmed.isEmpty()) {
            updateState { copy(actionMessage = "Reply message is required.") }
            return
        }
        runMutation {
            when (val result = repository.replyToTicket(_uiState.value.toAuthConfig(), ticketId, trimmed)) {
                is NetworkResult.Failure -> updateState {
                    copy(isMutating = false, actionMessage = "Reply failed: ${result.message}")
                }
                is NetworkResult.Success -> {
                    updateState {
                        copy(isMutating = false, actionMessage = "Reply sent.")
                    }
                    loadTicketDetail(_uiState.value.toAuthConfig(), ticketId, showLoading = false)
                    refreshTickets(_uiState.value.toAuthConfig())
                }
            }
        }
    }

    fun submitStatusUpdate(status: String) {
        val ticketId = _uiState.value.selectedTicketId ?: return
        val trimmed = status.trim().lowercase()
        if (trimmed.isEmpty()) {
            updateState { copy(actionMessage = "Status is required.") }
            return
        }
        runMutation {
            when (val result = repository.updateTicketStatus(_uiState.value.toAuthConfig(), ticketId, trimmed)) {
                is NetworkResult.Failure -> updateState {
                    copy(isMutating = false, actionMessage = "Status update failed: ${result.message}")
                }
                is NetworkResult.Success -> {
                    updateState {
                        copy(
                            isMutating = false,
                            actionMessage = "Status updated to ${result.value}.",
                            ticketDetail = ticketDetail?.copy(ticket = ticketDetail.ticket.copy(status = result.value)),
                            tickets = tickets.updateTicketStatus(ticketId, result.value)
                        )
                    }
                    loadTicketDetail(_uiState.value.toAuthConfig(), ticketId, showLoading = false)
                }
            }
        }
    }

    fun submitInternalNote(note: String) {
        val ticketId = _uiState.value.selectedTicketId ?: return
        val trimmed = note.trim()
        if (trimmed.isEmpty()) {
            updateState { copy(actionMessage = "Internal note is required.") }
            return
        }
        runMutation {
            when (val result = repository.addInternalNote(_uiState.value.toAuthConfig(), ticketId, trimmed)) {
                is NetworkResult.Failure -> updateState {
                    copy(isMutating = false, actionMessage = "Internal note failed: ${result.message}")
                }
                is NetworkResult.Success -> {
                    updateState {
                        copy(isMutating = false, actionMessage = "Internal note added.")
                    }
                    loadTicketDetail(_uiState.value.toAuthConfig(), ticketId, showLoading = false)
                    refreshTickets(_uiState.value.toAuthConfig())
                }
            }
        }
    }

    private suspend fun refreshTickets(config: AuthConfig) {
        when (val ticketsResult = repository.fetchTickets(config)) {
            is NetworkResult.Failure -> {
                updateState {
                    copy(isLoading = false, tickets = emptyList(), pagination = null, errorMessage = ticketsResult.message)
                }
            }
            is NetworkResult.Success -> {
                updateState {
                    copy(
                        isLoading = false,
                        errorMessage = null,
                        tickets = ticketsResult.value.tickets,
                        pagination = ticketsResult.value.pagination
                    )
                }
            }
        }
    }

    private suspend fun loadTicketDetail(
        config: AuthConfig,
        ticketId: Int,
        showLoading: Boolean = true
    ) {
        if (showLoading) {
            updateState { copy(isDetailLoading = true, detailErrorMessage = null) }
        } else {
            updateState { copy(detailErrorMessage = null) }
        }
        when (val detailResult = repository.fetchTicketDetail(config, ticketId)) {
            is NetworkResult.Failure -> updateState {
                copy(
                    isDetailLoading = false,
                    ticketDetail = null,
                    detailErrorMessage = detailResult.message
                )
            }

            is NetworkResult.Success -> updateState {
                copy(
                    isDetailLoading = false,
                    ticketDetail = detailResult.value,
                    detailErrorMessage = null,
                    tickets = tickets.updateTicketStatus(ticketId, detailResult.value.ticket.status)
                )
            }
        }
    }

    private fun runMutation(block: suspend () -> Unit) {
        if (_uiState.value.isMutating) {
            return
        }
        viewModelScope.launch {
            updateState { copy(isMutating = true, actionMessage = null) }
            block()
        }
    }

    private fun updateState(transform: TicketsUiState.() -> TicketsUiState) {
        _uiState.update { it.transform() }
    }

    private fun TicketsUiState.toAuthConfig(): AuthConfig = AuthConfig(
        siteUrl = siteUrl,
        username = username,
        applicationPassword = applicationPassword,
        wpNonce = wpNonce
    )

    companion object {
        fun factory(repository: HelpdeskRepository = HelpdeskRepository()): ViewModelProvider.Factory = object : ViewModelProvider.Factory {
            @Suppress("UNCHECKED_CAST")
            override fun <T : ViewModel> create(modelClass: Class<T>): T = TicketsViewModel(repository) as T
        }
    }
}

private fun List<Ticket>.updateTicketStatus(ticketId: Int, status: String): List<Ticket> = map { ticket ->
    if (ticket.id == ticketId) {
        ticket.copy(status = status)
    } else {
        ticket
    }
}
