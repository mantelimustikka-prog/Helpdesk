package com.wphelpd.admin.feature.applock

import com.google.common.truth.Truth.assertThat
import org.junit.Test

class AppLockLifecyclePolicyTest {
    @Test
    fun shouldRelockOnStop_trueWhenUnlockedAndNotConfigurationChange() {
        val result = AppLockLifecyclePolicy.shouldRelockOnStop(
            isUnlocked = true,
            isChangingConfigurations = false
        )

        assertThat(result).isTrue()
    }

    @Test
    fun shouldRelockOnStop_falseWhenLocked() {
        val result = AppLockLifecyclePolicy.shouldRelockOnStop(
            isUnlocked = false,
            isChangingConfigurations = false
        )

        assertThat(result).isFalse()
    }

    @Test
    fun shouldRelockOnStop_falseDuringConfigurationChange() {
        val result = AppLockLifecyclePolicy.shouldRelockOnStop(
            isUnlocked = true,
            isChangingConfigurations = true
        )

        assertThat(result).isFalse()
    }
}
