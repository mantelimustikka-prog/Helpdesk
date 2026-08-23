package com.wphelpd.admin.feature.tickets

import androidx.lifecycle.ViewModel
import androidx.lifecycle.ViewModelProvider
import androidx.lifecycle.viewModelScope
import com.wphelpd.admin.core.config.ServerConfigRepository
import com.wphelpd.admin.core.network.AuthConfig
import com.wphelpd.admin.core.network.NetworkResult
import com.wphelpd.admin.data.repository.HelpdeskRepository
import java.io.IOException
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import retrofit2.HttpException

class TicketsViewModel(
    private val repository: HelpdeskRepository = HelpdeskRepository(),
    private val serverConfigRepository: ServerConfigRepository = NoOpServerConfigRepository
) : ViewModel() {
    private val _uiState = MutableStateFlow(TicketsUiState())
    val uiState: StateFlow<TicketsUiState> = _uiState.asStateFlow()

    init {
        val savedConfig = serverConfigRepository.load()
        if (savedConfig == null) {
            updateState {
                copy(
                    isBootstrapping = false,
                    requiresSetup = true,
                    errorMessage = "Saved server configuration was not found. Enter your WordPress credentials to continue."
                )
            }
        } else {
            updateState {
                copy(
                    siteUrl = savedConfig.siteUrl,
                    username = savedConfig.username,
                    applicationPassword = savedConfig.applicationPassword,
                    wpNonce = savedConfig.wpNonce
                )
            }
            bootstrapFromSavedConfig(savedConfig)
        }
    }

    fun updateSiteUrl(value: String) = updateState { copy(siteUrl = value) }
    fun updateUsername(value: String) = updateState { copy(username = value) }
    fun updateApplicationPassword(value: String) = updateState { copy(applicationPassword = value) }
    fun updateWpNonce(value: String) = updateState { copy(wpNonce = value) }

    fun connectAndLoadTickets() {
        val state = _uiState.value
        if (!state.siteUrl.trim().startsWith("https://")) {
            updateState {
                copy(
                    requiresSetup = true,
                    errorMessage = "Use an HTTPS site URL for WP HelpD."
                )
            }
            return
        }
        authenticateAndLoadTickets(
            config = state.toAuthConfig(),
            saveConfigOnSuccess = true,
            isBootstrap = false
        )
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
                    copy(
                        isBootstrapping = false,
                        requiresSetup = false,
                        isLoading = false,
                        tickets = emptyList(),
                        pagination = null,
                        errorMessage = ticketsResult.message
                    )
                }
            }
            is NetworkResult.Success -> {
                updateState {
                    copy(
                        isBootstrapping = false,
                        requiresSetup = false,
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

    private fun bootstrapFromSavedConfig(config: AuthConfig) {
        authenticateAndLoadTickets(
            config = config,
            saveConfigOnSuccess = false,
            isBootstrap = true
        )
    }

    private fun authenticateAndLoadTickets(
        config: AuthConfig,
        saveConfigOnSuccess: Boolean,
        isBootstrap: Boolean
    ) {
        viewModelScope.launch {
            updateState {
                copy(
                    isBootstrapping = isBootstrap,
                    isLoading = true,
                    requiresSetup = false,
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
                    val state = if (isBootstrap) {
                        classifyBootstrapAuthFailure(authResult)
                    } else {
                        ManualAuthFailure(
                            requiresSetup = true,
                            message = authResult.message
                        )
                    }
                    updateState {
                        copy(
                            isBootstrapping = false,
                            requiresSetup = state.requiresSetup,
                            isLoading = false,
                            currentUser = null,
                            errorMessage = state.message
                        )
                    }
                }
                is NetworkResult.Success -> {
                    if (saveConfigOnSuccess) {
                        serverConfigRepository.save(config)
                    }
                    updateState { copy(currentUser = authResult.value) }
                    refreshTickets(config)
                }
            }
        }
    }

    private fun classifyBootstrapAuthFailure(result: NetworkResult.Failure): ManualAuthFailure {
        val throwable = result.throwable
        return when {
            throwable is HttpException && (throwable.code() == 401 || throwable.code() == 403) ->
                ManualAuthFailure(
                    requiresSetup = true,
                    message = "Saved credentials are invalid. Please update them and authenticate again."
                )

            throwable is IOException ->
                ManualAuthFailure(
                    requiresSetup = true,
                    message = "Unable to reach the WP HelpD server. Check your connection and retry."
                )

            else ->
                ManualAuthFailure(
                    requiresSetup = true,
                    message = result.message
                )
        }
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
            serverConfigRepository: ServerConfigRepository = NoOpServerConfigRepository
        ): ViewModelProvider.Factory = object : ViewModelProvider.Factory {
            @Suppress("UNCHECKED_CAST")
            override fun <T : ViewModel> create(modelClass: Class<T>): T =
                TicketsViewModel(repository, serverConfigRepository) as T
        }
    }
}

private data class ManualAuthFailure(
    val requiresSetup: Boolean,
    val message: String
)

private object NoOpServerConfigRepository : ServerConfigRepository {
    override fun load(): AuthConfig? = null
    override fun save(config: AuthConfig) = Unit
    override fun clear() = Unit
}
