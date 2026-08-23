package com.wphelpd.admin.feature.tickets

import androidx.lifecycle.ViewModel
import androidx.lifecycle.ViewModelProvider
import androidx.lifecycle.viewModelScope
import com.wphelpd.admin.core.config.ServerConfigRepository
import com.wphelpd.admin.core.network.AuthConfig
import com.wphelpd.admin.core.network.NetworkResult
import com.wphelpd.admin.data.repository.HelpdeskRepository
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch

class TicketsViewModel(
    private val repository: HelpdeskRepository = HelpdeskRepository(),
    private val serverConfigRepository: ServerConfigRepository? = null
) : ViewModel() {
    private val _uiState = MutableStateFlow(TicketsUiState())
    val uiState: StateFlow<TicketsUiState> = _uiState.asStateFlow()

    init {
        serverConfigRepository?.load()?.let { saved ->
            updateState {
                copy(
                    siteUrl = saved.siteUrl,
                    username = saved.username,
                    applicationPassword = saved.applicationPassword,
                    wpNonce = saved.wpNonce
                )
            }
        }
    }

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
                    detailErrorMessage = null
                )
            }

            when (val authResult = repository.authCheck(config)) {
                is NetworkResult.Failure -> {
                    updateState {
                        copy(isLoading = false, currentUser = null, errorMessage = authResult.message)
                    }
                }
                is NetworkResult.Success -> {
                    serverConfigRepository?.save(config)
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
                ticketDetail = null,
                isDetailLoading = true,
                detailErrorMessage = null
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
                    detailErrorMessage = null
                )
            }
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
        fun factory(
            repository: HelpdeskRepository = HelpdeskRepository(),
            serverConfigRepository: ServerConfigRepository? = null
        ): ViewModelProvider.Factory = object : ViewModelProvider.Factory {
            @Suppress("UNCHECKED_CAST")
            override fun <T : ViewModel> create(modelClass: Class<T>): T =
                TicketsViewModel(repository, serverConfigRepository) as T
        }
    }
}

