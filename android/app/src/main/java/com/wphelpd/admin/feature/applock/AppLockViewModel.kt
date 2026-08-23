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
    private val repository: AppLockRepository
) : ViewModel() {

    private val _uiState = MutableStateFlow(AppLockUiState())
    val uiState: StateFlow<AppLockUiState> = _uiState.asStateFlow()

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
                _uiState.update { it.copy(isFirstRun = false, isUnlocked = true, errorMessage = null) }
            } else {
                _uiState.update { it.copy(errorMessage = "Incorrect password.") }
            }
        }
    }

    fun lock() {
        _uiState.update { it.copy(isUnlocked = false, errorMessage = null) }
    }

    fun clearError() {
        _uiState.update { it.copy(errorMessage = null) }
    }

    companion object {
        /** Minimum password length enforced by product requirements. */
        private const val MIN_PASSWORD_LENGTH = 4

        fun factory(repository: AppLockRepository): ViewModelProvider.Factory =
            object : ViewModelProvider.Factory {
                @Suppress("UNCHECKED_CAST")
                override fun <T : ViewModel> create(modelClass: Class<T>): T =
                    AppLockViewModel(repository) as T
            }
    }
}
