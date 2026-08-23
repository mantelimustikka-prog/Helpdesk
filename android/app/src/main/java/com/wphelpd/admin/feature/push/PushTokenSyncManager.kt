package com.wphelpd.admin.feature.push

import com.wphelpd.admin.BuildConfig
import com.wphelpd.admin.core.network.AuthConfig
import com.wphelpd.admin.core.network.NetworkResult
import com.wphelpd.admin.data.repository.HelpdeskRepository

class PushTokenSyncManager(
    private val repository: HelpdeskRepository = HelpdeskRepository(),
    private val stateStore: PushTokenStorage
) {
    suspend fun registerIfNeeded(config: AuthConfig): Boolean {
        val token = stateStore.currentToken() ?: return false
        if (stateStore.isAlreadyRegistered(token, config.siteUrl, config.username)) {
            return true
        }
        return when (
            repository.registerDeviceToken(
                config = config,
                deviceToken = token,
                appVersion = BuildConfig.VERSION_NAME
            )
        ) {
            is NetworkResult.Success -> {
                stateStore.markRegistered(token, config.siteUrl, config.username)
                true
            }

            is NetworkResult.Failure -> false
        }
    }

    suspend fun unregisterIfNeeded(config: AuthConfig): Boolean {
        val token = stateStore.currentToken() ?: return false
        val result = repository.unregisterDeviceToken(
            config = config,
            deviceToken = token,
            appVersion = BuildConfig.VERSION_NAME
        )
        stateStore.clearRegisteredState()
        return result is NetworkResult.Success
    }
}
