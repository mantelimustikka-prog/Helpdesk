package com.wphelpd.admin.feature.applock

object AppLockLifecyclePolicy {
    fun shouldRelockOnStop(
        isUnlocked: Boolean,
        isChangingConfigurations: Boolean
    ): Boolean = isUnlocked && !isChangingConfigurations
}
