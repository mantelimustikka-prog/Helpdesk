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
                    wpNonce = savedConfig.wpNonce,
                    isBootstrapping = true
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
            loadTicketDetail(
                config = _uiState.value.toAuthConfig(),
                ticketId = ticketId,
                expectedSelectedTicketId = ticketId
            )
        }
    }

    fun refreshSelectedTicket() {
        val ticketId = _uiState.value.selectedTicketId ?: return
        viewModelScope.launch {
            loadTicketDetail(
                config = _uiState.value.toAuthConfig(),
                ticketId = ticketId,
                expectedSelectedTicketId = ticketId
            )
        }
    }

    fun clearSelectedTicket() {
        updateState {
            copy(
                selectedTicketId = null,
                ticketDetail = null,
                isDetailLoading = false,
                detailErrorMessage = null,
                replyText = "",
                isReplying = false,
                replyError = null,
                isUpdatingStatus = false,
                statusUpdateError = null,
                noteText = "",
                isAddingNote = false,
                noteError = null
            )
        }
    }

    fun updateReplyText(value: String) = updateState { copy(replyText = value, replyError = null) }

    fun submitReply() {
        val state = _uiState.value
        val ticketId = state.selectedTicketId ?: return
        if (state.isReplying) return
        val message = state.replyText.trim()
        if (message.isEmpty()) {
            updateState { copy(replyError = "Reply cannot be empty.") }
            return
        }
        var started = false
        updateState {
            if (isReplying) this else {
                started = true
                copy(isReplying = true, replyError = null)
            }
        }
        if (!started) return
        viewModelScope.launch {
            when (val result = repository.replyToTicket(state.toAuthConfig(), ticketId, message)) {
                is NetworkResult.Failure -> updateState {
                    copy(isReplying = false, replyError = result.message)
                }
                is NetworkResult.Success -> {
                    updateState { copy(isReplying = false, replyText = "", replyError = null) }
                    refreshTicketDetailIfStillSelected(state.toAuthConfig(), ticketId)
                }
            }
        }
    }

    fun updateTicketStatus(status: String) {
        val state = _uiState.value
        val ticketId = state.selectedTicketId ?: return
        if (state.isUpdatingStatus) return
        var started = false
        updateState {
            if (isUpdatingStatus) this else {
                started = true
                copy(isUpdatingStatus = true, statusUpdateError = null)
            }
        }
        if (!started) return
        viewModelScope.launch {
            when (val result = repository.updateTicketStatus(state.toAuthConfig(), ticketId, status)) {
                is NetworkResult.Failure -> updateState {
                    copy(isUpdatingStatus = false, statusUpdateError = result.message)
                }
                is NetworkResult.Success -> {
                    updateState { copy(isUpdatingStatus = false, statusUpdateError = null) }
                    refreshTicketDetailIfStillSelected(state.toAuthConfig(), ticketId)
                }
            }
        }
    }

    fun updateNoteText(value: String) = updateState { copy(noteText = value, noteError = null) }

    fun submitNote() {
        val state = _uiState.value
        val ticketId = state.selectedTicketId ?: return
        if (state.isAddingNote) return
        val note = state.noteText.trim()
        if (note.isEmpty()) {
            updateState { copy(noteError = "Note cannot be empty.") }
            return
        }
        var started = false
        updateState {
            if (isAddingNote) this else {
                started = true
                copy(isAddingNote = true, noteError = null)
            }
        }
        if (!started) return
        viewModelScope.launch {
            when (val result = repository.addInternalNote(state.toAuthConfig(), ticketId, note)) {
                is NetworkResult.Failure -> updateState {
                    copy(isAddingNote = false, noteError = result.message)
                }
                is NetworkResult.Success -> {
                    updateState { copy(isAddingNote = false, noteText = "", noteError = null) }
                    refreshTicketDetailIfStillSelected(state.toAuthConfig(), ticketId)
                }
            }
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
        showLoading: Boolean = true,
        expectedSelectedTicketId: Int? = null
    ) {
        if (showLoading) {
            updateState { copy(isDetailLoading = true, detailErrorMessage = null) }
        } else {
            updateState { copy(detailErrorMessage = null) }
        }
        val detailResult = repository.fetchTicketDetail(config, ticketId)
        expectedSelectedTicketId?.let { selectedTicketId ->
            if (_uiState.value.selectedTicketId != selectedTicketId) return
        }
        when (detailResult) {
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

    private suspend fun refreshTicketDetailIfStillSelected(config: AuthConfig, ticketId: Int) {
        if (_uiState.value.selectedTicketId != ticketId) return
        loadTicketDetail(
            config = config,
            ticketId = ticketId,
            showLoading = false,
            expectedSelectedTicketId = ticketId
        )
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
                    val message = if (isBootstrap) {
                        classifyBootstrapAuthFailure(authResult)
                    } else {
                        authResult.message
                    }
                    updateState {
                        copy(
                            isBootstrapping = false,
                            requiresSetup = true,
                            isLoading = false,
                            currentUser = null,
                            errorMessage = message
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

    private fun classifyBootstrapAuthFailure(result: NetworkResult.Failure): String {
        val throwable = result.throwable
        return when {
            throwable is HttpException && (throwable.code() == 401 || throwable.code() == 403) ->
                "Saved credentials are invalid. Please update them and authenticate again."

            throwable is IOException ->
                "Unable to reach the WP HelpD server. Check your connection and retry."

            else ->
                result.message
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

private object NoOpServerConfigRepository : ServerConfigRepository {
    override fun load(): AuthConfig? = null
    override fun save(config: AuthConfig) = Unit
    override fun clear() = Unit
}
