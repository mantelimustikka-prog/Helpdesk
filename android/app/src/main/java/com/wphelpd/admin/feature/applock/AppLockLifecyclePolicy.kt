package com.wphelpd.admin.feature.applock

object AppLockLifecyclePolicy {
    fun shouldRelockOnProcessStop(
        isUnlocked: Boolean
    ): Boolean = isUnlocked
}
