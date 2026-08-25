package com.wphelpd.admin.feature.applock

/** Abstraction over local password storage, enabling test fakes. */
interface AppLockRepository {
    fun isPasswordSet(): Boolean
    fun setPassword(password: String)
    fun verifyPassword(password: String): Boolean
    /** Returns the email address stored during first-run setup, or null if not set. */
    fun getEmail(): String?
    /** Persists the email address used for password reset OTP. */
    fun setEmail(email: String)
}
