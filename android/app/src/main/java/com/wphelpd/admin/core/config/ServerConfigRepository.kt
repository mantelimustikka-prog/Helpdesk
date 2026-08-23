package com.wphelpd.admin.core.config

import com.wphelpd.admin.core.network.AuthConfig

/**
 * Contract for persisting and restoring server connection credentials.
 */
interface ServerConfigRepository {
    /** Returns the last saved [AuthConfig], or null if none has been saved yet. */
    fun load(): AuthConfig?

    /** Persists [config] so it can be restored on the next app launch. */
    fun save(config: AuthConfig)

    /** Removes all saved server configuration. */
    fun clear()
}
