package com.wphelpd.admin.feature.applock

import com.google.common.truth.Truth.assertThat
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.ExperimentalCoroutinesApi
import kotlinx.coroutines.test.StandardTestDispatcher
import kotlinx.coroutines.test.TestDispatcher
import kotlinx.coroutines.test.advanceUntilIdle
import kotlinx.coroutines.test.resetMain
import kotlinx.coroutines.test.runTest
import kotlinx.coroutines.test.setMain
import org.junit.Rule
import org.junit.Test
import org.junit.rules.TestWatcher
import org.junit.runner.Description

@OptIn(ExperimentalCoroutinesApi::class)
class AppLockViewModelTest {

    @get:Rule
    val mainDispatcherRule = MainDispatcherRule()

    // ── init routing ─────────────────────────────────────────────────────────

    @Test
    fun init_noPasswordSet_setsIsFirstRunTrue() = runTest {
        val vm = AppLockViewModel(FakeAppLockRepository(hasPassword = false))
        advanceUntilIdle()
        assertThat(vm.uiState.value.isInitialising).isFalse()
        assertThat(vm.uiState.value.isFirstRun).isTrue()
    }

    @Test
    fun init_passwordAlreadySet_setsIsFirstRunFalse() = runTest {
        val vm = AppLockViewModel(FakeAppLockRepository(hasPassword = true))
        advanceUntilIdle()
        assertThat(vm.uiState.value.isInitialising).isFalse()
        assertThat(vm.uiState.value.isFirstRun).isFalse()
    }

    // ── createPassword ───────────────────────────────────────────────────────

    @Test
    fun createPassword_valid_setsUnlocked() = runTest {
        val repo = FakeAppLockRepository(hasPassword = false)
        val vm = AppLockViewModel(repo)
        advanceUntilIdle()
        vm.createPassword("abcd", "abcd")
        advanceUntilIdle()
        assertThat(vm.uiState.value.isUnlocked).isTrue()
        assertThat(vm.uiState.value.isFirstRun).isFalse()
        assertThat(vm.uiState.value.errorMessage).isNull()
    }

    @Test
    fun createPassword_mismatch_setsError() = runTest {
        val vm = AppLockViewModel(FakeAppLockRepository(hasPassword = false))
        advanceUntilIdle()
        vm.createPassword("abcd", "wxyz")
        advanceUntilIdle()
        assertThat(vm.uiState.value.isUnlocked).isFalse()
        assertThat(vm.uiState.value.errorMessage).isNotNull()
    }

    @Test
    fun createPassword_tooShort_setsError() = runTest {
        val vm = AppLockViewModel(FakeAppLockRepository(hasPassword = false))
        advanceUntilIdle()
        vm.createPassword("abc", "abc")
        advanceUntilIdle()
        assertThat(vm.uiState.value.isUnlocked).isFalse()
        assertThat(vm.uiState.value.errorMessage).isNotNull()
    }

    // ── unlock ───────────────────────────────────────────────────────────────

    @Test
    fun unlock_correctPassword_setsUnlocked() = runTest {
        val repo = FakeAppLockRepository(hasPassword = true, correctPassword = "secret")
        val vm = AppLockViewModel(repo)
        advanceUntilIdle()
        vm.unlock("secret")
        advanceUntilIdle()
        assertThat(vm.uiState.value.isUnlocked).isTrue()
        assertThat(vm.uiState.value.errorMessage).isNull()
    }

    @Test
    fun unlock_wrongPassword_setsError() = runTest {
        val repo = FakeAppLockRepository(hasPassword = true, correctPassword = "secret")
        val vm = AppLockViewModel(repo)
        advanceUntilIdle()
        vm.unlock("wrong")
        advanceUntilIdle()
        assertThat(vm.uiState.value.isUnlocked).isFalse()
        assertThat(vm.uiState.value.errorMessage).isNotNull()
    }

    @Test
    fun onAppBackgrounded_whenUnlocked_relocksImmediately() = runTest {
        val repo = FakeAppLockRepository(hasPassword = true, correctPassword = "secret")
        val vm = AppLockViewModel(repo)
        advanceUntilIdle()
        vm.unlock("secret")
        advanceUntilIdle()

        vm.onAppBackgrounded()

        assertThat(vm.uiState.value.isUnlocked).isFalse()
    }

    @Test
    fun timeoutRelock_locksAfterElapsedThreshold_whenBackgroundLockDisabled() = runTest {
        var now = 1_000L
        val repo = FakeAppLockRepository(hasPassword = true, correctPassword = "secret")
        val vm = AppLockViewModel(
            repository = repo,
            relockTimeoutMillis = 5_000L,
            lockOnBackground = false,
            currentTimeMillis = { now }
        )
        advanceUntilIdle()
        vm.unlock("secret")
        advanceUntilIdle()

        vm.onAppBackgrounded()
        now += 4_000L
        vm.onAppForegrounded()
        assertThat(vm.uiState.value.isUnlocked).isTrue()

        vm.onAppBackgrounded()
        now += 5_000L
        vm.onAppForegrounded()
        assertThat(vm.uiState.value.isUnlocked).isFalse()
    }

    @Test
    fun onAppForegrounded_doesNotRelock_whenTimeoutDisabledAndBackgroundLockDisabled() = runTest {
        var now = 1_000L
        val repo = FakeAppLockRepository(hasPassword = true, correctPassword = "secret")
        val vm = AppLockViewModel(
            repository = repo,
            relockTimeoutMillis = 0L,
            lockOnBackground = false,
            currentTimeMillis = { now }
        )
        advanceUntilIdle()
        vm.unlock("secret")
        advanceUntilIdle()

        vm.onAppBackgrounded()
        now += 60_000L
        vm.onAppForegrounded()

        assertThat(vm.uiState.value.isUnlocked).isTrue()
    }

    // ── lifecycle guard edge cases ────────────────────────────────────────────

    /** ON_START fires on first launch before any ON_STOP — must be a no-op. */
    @Test
    fun onAppForegrounded_withoutPriorBackgrounded_isNoOp() = runTest {
        val repo = FakeAppLockRepository(hasPassword = true, correctPassword = "secret")
        val vm = AppLockViewModel(repo)
        advanceUntilIdle()
        vm.unlock("secret")
        advanceUntilIdle()

        // Call foregrounded without any prior backgrounded call
        vm.onAppForegrounded()

        assertThat(vm.uiState.value.isUnlocked).isTrue()
    }

    /** ON_STOP fires while app is still locking/locked — must not double-lock or set a stale timestamp. */
    @Test
    fun onAppBackgrounded_whenAlreadyLocked_isNoOp() = runTest {
        val repo = FakeAppLockRepository(hasPassword = true, correctPassword = "secret")
        val vm = AppLockViewModel(repo)
        advanceUntilIdle()
        // App is locked (isUnlocked = false); calling backgrounded should have no effect.
        vm.onAppBackgrounded()
        vm.onAppBackgrounded()

        assertThat(vm.uiState.value.isUnlocked).isFalse()
        // Subsequent foregrounded must also be a no-op (backgroundedAtMillis not set).
        vm.onAppForegrounded()
        assertThat(vm.uiState.value.isUnlocked).isFalse()
    }

    /** ON_STOP fires during the init window (isInitialising=true, isUnlocked=false) — must be a no-op. */
    @Test
    fun onAppBackgrounded_duringInitialising_isNoOp() = runTest {
        val repo = FakeAppLockRepository(hasPassword = true, correctPassword = "secret")
        // Don't advance — init coroutine hasn't completed, so isInitialising=true
        val vm = AppLockViewModel(repo)

        vm.onAppBackgrounded()

        // State is still initialising; no timestamp stored, no lock change.
        assertThat(vm.uiState.value.isInitialising).isTrue()
        assertThat(vm.uiState.value.isUnlocked).isFalse()

        // Foregrounded afterwards must also be a no-op.
        vm.onAppForegrounded()
        assertThat(vm.uiState.value.isUnlocked).isFalse()
    }

    /**
     * Config-change scenario: ON_STOP on old activity → ViewModel locked; new activity
     * gets ON_START → onAppForegrounded clears the stale timestamp and stays locked.
     */
    @Test
    fun configChange_backgroundedThenForegrounded_staysLocked() = runTest {
        val repo = FakeAppLockRepository(hasPassword = true, correctPassword = "secret")
        val vm = AppLockViewModel(repo)
        advanceUntilIdle()
        vm.unlock("secret")
        advanceUntilIdle()

        // Simulate configuration-change lifecycle: ON_STOP then ON_START on the new instance.
        vm.onAppBackgrounded()
        assertThat(vm.uiState.value.isUnlocked).isFalse()

        vm.onAppForegrounded()
        // App must remain locked; backgroundedAtMillis cleared so no re-relock attempt later.
        assertThat(vm.uiState.value.isUnlocked).isFalse()

        // A second foregrounded call (e.g., double-fire) must still be a no-op.
        vm.onAppForegrounded()
        assertThat(vm.uiState.value.isUnlocked).isFalse()
    }

    // ── clearError ───────────────────────────────────────────────────────────

    @Test
    fun clearError_removesExistingErrorMessage() = runTest {
        val vm = AppLockViewModel(FakeAppLockRepository(hasPassword = false))
        advanceUntilIdle()
        // Trigger a mismatch error so that errorMessage is non-null.
        vm.createPassword("abcd", "wxyz")
        advanceUntilIdle()
        assertThat(vm.uiState.value.errorMessage).isNotNull()

        vm.clearError()

        assertThat(vm.uiState.value.errorMessage).isNull()
    }
}

// ── helpers ──────────────────────────────────────────────────────────────────

private class FakeAppLockRepository(
    private val hasPassword: Boolean,
    private val correctPassword: String = ""
) : AppLockRepository {
    override fun isPasswordSet(): Boolean = hasPassword
    override fun setPassword(password: String) { /* no-op in tests */ }
    override fun verifyPassword(password: String): Boolean = password == correctPassword
}

private class MainDispatcherRule(
    private val dispatcher: TestDispatcher = StandardTestDispatcher()
) : TestWatcher() {
    override fun starting(description: Description) { Dispatchers.setMain(dispatcher) }
    override fun finished(description: Description) { Dispatchers.resetMain() }
}
