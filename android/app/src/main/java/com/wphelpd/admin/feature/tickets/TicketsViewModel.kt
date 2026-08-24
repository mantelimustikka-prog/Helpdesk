package com.wphelpd.admin.feature.tickets

import android.util.Log
import androidx.lifecycle.ViewModel
import androidx.lifecycle.ViewModelProvider
import androidx.lifecycle.viewModelScope
import com.wphelpd.admin.core.config.ServerConfigRepository
import com.wphelpd.admin.core.network.AuthConfig
import com.wphelpd.admin.core.network.NetworkResult
import com.wphelpd.admin.data.repository.AuthCheckResult
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
    private var shouldRestoreFromSavedConfig: Boolean = false
    private val _uiState = MutableStateFlow(TicketsUiState())
    val uiState: StateFlow<TicketsUiState> = _uiState.asStateFlow()

    init {
        restoreSessionFromSavedConfig()
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
        val selection = updateSelection(ticketId)
        val config = _uiState.value.toAuthConfig()
        viewModelScope.launch {
            loadTicketDetail(
                config = config,
                selection = selection
            )
        }
    }

    fun refreshSelectedTicket() {
        val state = _uiState.value
        val ticketId = state.selectedTicketId ?: return
        if (state.isDetailLoading || state.isDetailActionInProgress) return
        val selection = currentSelection(ticketId) ?: return
        val config = state.toAuthConfig()
        viewModelScope.launch {
            loadTicketDetail(
                config = config,
                selection = selection
            )
        }
    }

    fun clearSelectedTicket() {
        updateState {
            val nextSessionId = selectionSessionId + 1
            copy(
                selectionSessionId = nextSessionId,
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

    fun clearSensitiveSessionState() {
        shouldRestoreFromSavedConfig = true
        updateState {
            val nextSessionId = selectionSessionId + 1
            copy(
                currentUser = null,
                tickets = emptyList(),
                pagination = null,
                selectionSessionId = nextSessionId,
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
                noteError = null,
                errorMessage = null,
                isLoading = false,
                isBootstrapping = false
            )
        }
    }

    fun logout() {
        serverConfigRepository.clear()
        clearSensitiveSessionState()
        updateState {
            copy(
                siteUrl = "",
                username = "",
                applicationPassword = "",
                wpNonce = "",
                requiresSetup = true,
                errorMessage = "Session cleared. Enter your WordPress credentials to continue."
            )
        }
    }

    fun restoreSessionFromSavedConfig() {
        shouldRestoreFromSavedConfig = false
        val savedConfig = serverConfigRepository.load()
        if (savedConfig == null) {
            updateState {
                copy(
                    isBootstrapping = false,
                    requiresSetup = true,
                    errorMessage = "Saved server configuration was not found. Enter your WordPress credentials to continue."
                )
            }
            return
        }
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

    fun restoreSessionFromSavedConfigIfNeeded() {
        if (shouldRestoreFromSavedConfig) {
            restoreSessionFromSavedConfig()
        }
    }

    fun updateReplyText(value: String) = updateState { copy(replyText = value, replyError = null) }

    fun submitReply() {
        val state = _uiState.value
        val ticketId = state.selectedTicketId ?: return
        val selection = currentSelection(ticketId) ?: return
        if (state.isDetailActionInProgress) return
        val message = state.replyText.trim()
        if (message.isEmpty()) {
            updateState { copy(replyError = "Reply cannot be empty.") }
            return
        }
        var started = false
        updateState {
            if (isDetailActionInProgress) this else {
                started = true
                copy(isReplying = true, replyError = null)
            }
        }
        if (!started) return
        viewModelScope.launch {
            when (val result = repository.replyToTicket(state.toAuthConfig(), ticketId, message)) {
                is NetworkResult.Failure -> updateStateForSelection(selection) {
                    copy(isReplying = false, replyError = result.message)
                }
                is NetworkResult.Success -> {
                    Log.d(TAG, "submitReply: reply succeeded for ticketId=$ticketId — refreshing detail")
                    if (updateStateForSelection(selection) {
                            copy(isReplying = false, replyText = "", replyError = null)
                        }
                    ) {
                        refreshTicketDetailIfStillSelected(state.toAuthConfig(), selection)
                    }
                }
            }
        }
    }

    fun updateTicketStatus(status: String) {
        val state = _uiState.value
        val ticketId = state.selectedTicketId ?: return
        val selection = currentSelection(ticketId) ?: return
        if (state.isDetailActionInProgress) return
        var started = false
        updateState {
            if (isDetailActionInProgress) this else {
                started = true
                copy(isUpdatingStatus = true, statusUpdateError = null)
            }
        }
        if (!started) return
        viewModelScope.launch {
            when (val result = repository.updateTicketStatus(state.toAuthConfig(), ticketId, status)) {
                is NetworkResult.Failure -> updateStateForSelection(selection) {
                    copy(isUpdatingStatus = false, statusUpdateError = result.message)
                }
                is NetworkResult.Success -> {
                    if (updateStateForSelection(selection) {
                            copy(isUpdatingStatus = false, statusUpdateError = null)
                        }
                    ) {
                        refreshTicketDetailIfStillSelected(state.toAuthConfig(), selection)
                    }
                }
            }
        }
    }

    fun updateNoteText(value: String) = updateState { copy(noteText = value, noteError = null) }

    fun submitNote() {
        val state = _uiState.value
        val ticketId = state.selectedTicketId ?: return
        val selection = currentSelection(ticketId) ?: return
        if (state.isDetailActionInProgress) return
        val note = state.noteText.trim()
        if (note.isEmpty()) {
            updateState { copy(noteError = "Note cannot be empty.") }
            return
        }
        var started = false
        updateState {
            if (isDetailActionInProgress) this else {
                started = true
                copy(isAddingNote = true, noteError = null)
            }
        }
        if (!started) return
        viewModelScope.launch {
            when (val result = repository.addInternalNote(state.toAuthConfig(), ticketId, note)) {
                is NetworkResult.Failure -> updateStateForSelection(selection) {
                    copy(isAddingNote = false, noteError = result.message)
                }
                is NetworkResult.Success -> {
                    Log.d(TAG, "submitNote: note succeeded for ticketId=$ticketId — refreshing detail")
                    if (updateStateForSelection(selection) {
                            copy(isAddingNote = false, noteText = "", noteError = null)
                        }
                    ) {
                        refreshTicketDetailIfStillSelected(state.toAuthConfig(), selection)
                    }
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
        selection: TicketSelection,
        showLoading: Boolean = true
    ) {
        val started = updateStateForSelection(selection) {
            if (showLoading) {
                copy(isDetailLoading = true, detailErrorMessage = null)
            } else {
                copy(detailErrorMessage = null)
            }
        }
        if (!started) return
        val detailResult = repository.fetchTicketDetail(config, selection.ticketId)
        when (detailResult) {
            is NetworkResult.Failure -> updateStateForSelection(selection) {
                copy(
                    isDetailLoading = false,
                    ticketDetail = null,
                    detailErrorMessage = detailResult.message
                )
            }

            is NetworkResult.Success -> updateStateForSelection(selection) {
                copy(
                    isDetailLoading = false,
                    ticketDetail = detailResult.value,
                    detailErrorMessage = null
                )
            }
        }
    }

    private suspend fun refreshTicketDetailIfStillSelected(config: AuthConfig, selection: TicketSelection) {
        if (!isSelectionCurrent(selection)) {
            Log.d(TAG, "refreshTicketDetailIfStillSelected: skipped — selection changed (ticketId=${selection.ticketId} sessionId=${selection.sessionId})")
            return
        }
        Log.d(TAG, "refreshTicketDetailIfStillSelected: executing refresh for ticketId=${selection.ticketId} sessionId=${selection.sessionId}")
        loadTicketDetail(
            config = config,
            selection = selection,
            showLoading = false
        )
    }

    private fun updateSelection(ticketId: Int): TicketSelection {
        updateState {
            val nextSessionId = selectionSessionId + 1
            copy(
                selectionSessionId = nextSessionId,
                selectedTicketId = ticketId,
                ticketDetail = null,
                isDetailLoading = true,
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
        val state = _uiState.value
        check(state.selectedTicketId == ticketId)
        return TicketSelection(ticketId = ticketId, sessionId = state.selectionSessionId)
    }

    private fun currentSelection(ticketId: Int): TicketSelection? {
        val state = _uiState.value
        return if (state.selectedTicketId == ticketId) {
            TicketSelection(ticketId = ticketId, sessionId = state.selectionSessionId)
        } else {
            null
        }
    }

    private fun isSelectionCurrent(selection: TicketSelection): Boolean {
        val state = _uiState.value
        return state.matches(selection)
    }

    private fun updateStateForSelection(
        selection: TicketSelection,
        transform: TicketsUiState.() -> TicketsUiState
    ): Boolean {
        _uiState.update { currentState ->
            if (currentState.matches(selection)) {
                currentState.transform()
            } else {
                currentState
            }
        }
        return _uiState.value.matches(selection)
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
        updateState {
            val nextSessionId = selectionSessionId + 1
            copy(
                isBootstrapping = isBootstrap,
                isLoading = true,
                requiresSetup = false,
                errorMessage = null,
                tickets = emptyList(),
                pagination = null,
                selectionSessionId = nextSessionId,
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
        viewModelScope.launch {
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
                    updateState { copy(currentUser = authResult.value.user, appearanceColors = authResult.value.appearanceColors) }
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

    private fun TicketsUiState.matches(selection: TicketSelection): Boolean =
        selectedTicketId == selection.ticketId &&
            selectionSessionId == selection.sessionId

    private data class TicketSelection(
        val ticketId: Int,
        val sessionId: Long
    )

    companion object {
        private const val TAG = "TicketsViewModel"

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
