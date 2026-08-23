package com.wphelpd.admin.startup

import androidx.lifecycle.ViewModel
import androidx.lifecycle.ViewModelProvider
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow

/**
 * Holds the startup error state so that initialization failures in [com.wphelpd.admin.MainActivity]
 * can be surfaced as observable UI state rather than hard-crashing the process.
 *
 * Call [reportError] when a startup step fails, and [clearError] when the user retries.
 */
class StartupViewModel : ViewModel() {

    private val _startupError = MutableStateFlow<String?>(null)

    /** Non-null when startup initialization has failed; null during normal operation. */
    val startupError: StateFlow<String?> = _startupError.asStateFlow()

    /** Records a startup failure message to be shown to the user. */
    fun reportError(message: String) {
        _startupError.value = message
    }

    /** Clears any recorded startup error so the UI can return to normal (e.g., after retry). */
    fun clearError() {
        _startupError.value = null
    }

    companion object {
        val factory: ViewModelProvider.Factory = object : ViewModelProvider.Factory {
            @Suppress("UNCHECKED_CAST")
            override fun <T : ViewModel> create(modelClass: Class<T>): T = StartupViewModel() as T
        }
    }
}
