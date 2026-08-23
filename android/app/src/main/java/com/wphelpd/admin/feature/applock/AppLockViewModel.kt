package com.wphelpd.admin.feature.applock

import androidx.lifecycle.ViewModel
import androidx.lifecycle.ViewModelProvider
import androidx.lifecycle.viewModelScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch

class AppLockViewModel(
    private val repository: AppLockRepository,
    private val relockTimeoutMillis: Long = DEFAULT_RELOCK_TIMEOUT_MILLIS,
    private val lockOnBackground: Boolean = true,
    private val currentTimeMillis: () -> Long = { System.currentTimeMillis() }
) : ViewModel() {

    private val _uiState = MutableStateFlow(AppLockUiState())
    val uiState: StateFlow<AppLockUiState> = _uiState.asStateFlow()
    private var backgroundedAtMillis: Long? = null

    init {
        viewModelScope.launch(Dispatchers.IO) {
            val firstRun = !repository.isPasswordSet()
            _uiState.update { it.copy(isInitialising = false, isFirstRun = firstRun) }
        }
    }

    /** Called from the create-password screen. */
    fun createPassword(password: String, confirm: String) {
        if (password.length < MIN_PASSWORD_LENGTH) {
            _uiState.update { it.copy(errorMessage = "Password must be at least $MIN_PASSWORD_LENGTH characters.") }
            return
        }
        if (password != confirm) {
            _uiState.update { it.copy(errorMessage = "Passwords do not match.") }
            return
        }
        viewModelScope.launch(Dispatchers.IO) {
            repository.setPassword(password)
            _uiState.update { it.copy(isFirstRun = false, isUnlocked = true, errorMessage = null) }
        }
    }

    /** Called from the unlock screen. */
    fun unlock(password: String) {
        viewModelScope.launch(Dispatchers.IO) {
            if (repository.verifyPassword(password)) {
                _uiState.update { it.copy(isUnlocked = true, errorMessage = null) }
            } else {
                _uiState.update { it.copy(errorMessage = "Incorrect password.") }
            }
        }
    }

    fun clearError() {
        _uiState.update { it.copy(errorMessage = null) }
    }

    fun onAppBackgrounded() {
        val currentState = _uiState.value
        if (!currentState.isUnlocked || currentState.isFirstRun) return
        backgroundedAtMillis = currentTimeMillis()
        if (lockOnBackground) {
            _uiState.update { it.copy(isUnlocked = false, errorMessage = null) }
        }
    }

    fun onAppForegrounded() {
        val backgroundedAt = backgroundedAtMillis ?: return
        val currentState = _uiState.value
        if (!currentState.isUnlocked || currentState.isFirstRun) return
        backgroundedAtMillis = null
        val elapsed = currentTimeMillis() - backgroundedAt
        if (relockTimeoutMillis > 0L && elapsed >= relockTimeoutMillis) {
            _uiState.update { it.copy(isUnlocked = false, errorMessage = null) }
        }
    }

    companion object {
        /** Minimum password length enforced by product requirements. */
        private const val MIN_PASSWORD_LENGTH = 4
        /** 0 disables timeout-based relock; background relock is handled separately. */
        private const val DEFAULT_RELOCK_TIMEOUT_MILLIS = 0L

        fun factory(repository: AppLockRepository): ViewModelProvider.Factory =
            object : ViewModelProvider.Factory {
                @Suppress("UNCHECKED_CAST")
                override fun <T : ViewModel> create(modelClass: Class<T>): T =
                    AppLockViewModel(repository) as T
            }
    }
}
