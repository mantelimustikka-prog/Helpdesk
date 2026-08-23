package com.wphelpd.admin.feature.applock

import com.google.common.truth.Truth.assertThat
import org.junit.Test

class AppLockLifecyclePolicyTest {
    @Test
    fun shouldRelockOnProcessStop_trueWhenUnlocked() {
        val result = AppLockLifecyclePolicy.shouldRelockOnProcessStop(isUnlocked = true)

        assertThat(result).isTrue()
    }

    @Test
    fun shouldRelockOnProcessStop_falseWhenLocked() {
        val result = AppLockLifecyclePolicy.shouldRelockOnProcessStop(isUnlocked = false)

        assertThat(result).isFalse()
    }
}
