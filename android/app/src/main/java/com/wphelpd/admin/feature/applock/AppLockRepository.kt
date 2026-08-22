package com.wphelpd.admin.feature.applock

/** Abstraction over local password storage, enabling test fakes. */
interface AppLockRepository {
    fun isPasswordSet(): Boolean
    fun setPassword(password: String)
    fun verifyPassword(password: String): Boolean
}
