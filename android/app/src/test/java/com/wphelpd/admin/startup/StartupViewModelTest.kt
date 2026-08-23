package com.wphelpd.admin.startup

import com.google.common.truth.Truth.assertThat
import org.junit.Test

class StartupViewModelTest {

    // ── initial state ─────────────────────────────────────────────────────────

    @Test
    fun `initial startupError is null — normal startup path`() {
        val vm = StartupViewModel()
        assertThat(vm.startupError.value).isNull()
    }

    // ── reportError ───────────────────────────────────────────────────────────

    @Test
    fun `reportError sets startupError to provided message`() {
        val vm = StartupViewModel()

        vm.reportError("Something went wrong")

        assertThat(vm.startupError.value).isEqualTo("Something went wrong")
    }

    @Test
    fun `reportError with initialization failure message surfaces correct text`() {
        val vm = StartupViewModel()
        val message = "The app failed to start. Please try again."

        vm.reportError(message)

        assertThat(vm.startupError.value).isEqualTo(message)
    }

    // ── clearError (retry) ────────────────────────────────────────────────────

    @Test
    fun `clearError after reportError resets startupError to null`() {
        val vm = StartupViewModel()
        vm.reportError("init failed")

        vm.clearError()

        assertThat(vm.startupError.value).isNull()
    }

    @Test
    fun `clearError when no error is set leaves state null`() {
        val vm = StartupViewModel()

        vm.clearError()

        assertThat(vm.startupError.value).isNull()
    }

    // ── multiple report/clear cycles ─────────────────────────────────────────

    @Test
    fun `error can be reported again after retry cycle`() {
        val vm = StartupViewModel()

        vm.reportError("first failure")
        vm.clearError()
        vm.reportError("second failure")

        assertThat(vm.startupError.value).isEqualTo("second failure")
    }

    @Test
    fun `later reportError call overwrites earlier message`() {
        val vm = StartupViewModel()

        vm.reportError("first")
        vm.reportError("second")

        assertThat(vm.startupError.value).isEqualTo("second")
    }
}
