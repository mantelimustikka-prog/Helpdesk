package com.wphelpd.admin.feature.applock

/** UI state for the app-lock flow. */
data class AppLockUiState(
    /** Whether the ViewModel has finished reading from storage. */
    val isInitialising: Boolean = true,
    /** True when no password exists yet (first-run creation). */
    val isFirstRun: Boolean = false,
    /** True once the user has successfully unlocked (or first-run setup is done). */
    val isUnlocked: Boolean = false,
    /** Inline error shown on the lock/create screen. */
    val errorMessage: String? = null
)
