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

    @Test
    fun lock_afterUnlock_relocksWithoutReturningToFirstRun() = runTest {
        val repo = FakeAppLockRepository(hasPassword = true, correctPassword = "secret")
        val vm = AppLockViewModel(repo)
        advanceUntilIdle()
        vm.unlock("secret")
        advanceUntilIdle()
        assertThat(vm.uiState.value.isUnlocked).isTrue()

        vm.lock()

        assertThat(vm.uiState.value.isUnlocked).isFalse()
        assertThat(vm.uiState.value.isFirstRun).isFalse()
    }
}

// ── helpers ──────────────────────────────────────────────────────────────────

private class FakeAppLockRepository(
    private val hasPassword: Boolean,
    private val correctPassword: String = ""
) : AppLockRepository {
    private var storedEmail: String? = null
    override fun isPasswordSet(): Boolean = hasPassword
    override fun setPassword(password: String) { /* no-op in tests */ }
    override fun verifyPassword(password: String): Boolean = password == correctPassword
    override fun getEmail(): String? = storedEmail
    override fun setEmail(email: String) { storedEmail = email }
}

private class MainDispatcherRule(
    private val dispatcher: TestDispatcher = StandardTestDispatcher()
) : TestWatcher() {
    override fun starting(description: Description) { Dispatchers.setMain(dispatcher) }
    override fun finished(description: Description) { Dispatchers.resetMain() }
}
