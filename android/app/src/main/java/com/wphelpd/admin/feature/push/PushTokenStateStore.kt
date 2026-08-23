package com.wphelpd.admin.feature.push

import android.content.Context

class PushTokenStateStore(context: Context) : PushTokenStorage {
    private val prefs = context.applicationContext.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)

    override fun saveCurrentToken(token: String) {
        prefs.edit().putString(KEY_CURRENT_TOKEN, token.trim()).apply()
    }

    override fun currentToken(): String? = prefs.getString(KEY_CURRENT_TOKEN, null)?.takeIf { it.isNotBlank() }

    override fun isAlreadyRegistered(token: String, siteUrl: String, username: String): Boolean =
        prefs.getString(KEY_REGISTERED_TOKEN, null) == token &&
            prefs.getString(KEY_REGISTERED_SITE_URL, null) == siteUrl &&
            prefs.getString(KEY_REGISTERED_USERNAME, null) == username

    override fun markRegistered(token: String, siteUrl: String, username: String) {
        prefs.edit()
            .putString(KEY_REGISTERED_TOKEN, token)
            .putString(KEY_REGISTERED_SITE_URL, siteUrl)
            .putString(KEY_REGISTERED_USERNAME, username)
            .apply()
    }

    override fun clearRegisteredState() {
        prefs.edit()
            .remove(KEY_REGISTERED_TOKEN)
            .remove(KEY_REGISTERED_SITE_URL)
            .remove(KEY_REGISTERED_USERNAME)
            .apply()
    }

    override fun wasNotificationHandled(notificationId: String): Boolean =
        recentNotificationIds().contains(notificationId)

    override fun markNotificationHandled(notificationId: String) {
        val updated = buildList {
            add(notificationId)
            addAll(recentNotificationIds().filterNot { it == notificationId })
        }.take(MAX_HANDLED_NOTIFICATIONS)
        prefs.edit()
            .putString(KEY_HANDLED_NOTIFICATIONS, updated.joinToString(separator = "\n"))
            .apply()
    }

    private fun recentNotificationIds(): List<String> =
        prefs.getString(KEY_HANDLED_NOTIFICATIONS, null)
            ?.split('\n')
            ?.map { it.trim() }
            ?.filter { it.isNotEmpty() }
            ?: emptyList()

    override fun clearHandledNotifications() {
        prefs.edit().remove(KEY_HANDLED_NOTIFICATIONS).apply()
    }

    companion object {
        private const val PREFS_NAME = "push_state_prefs"
        private const val KEY_CURRENT_TOKEN = "current_token"
        private const val KEY_REGISTERED_TOKEN = "registered_token"
        private const val KEY_REGISTERED_SITE_URL = "registered_site_url"
        private const val KEY_REGISTERED_USERNAME = "registered_username"
        private const val KEY_HANDLED_NOTIFICATIONS = "handled_notifications"
        private const val MAX_HANDLED_NOTIFICATIONS = 20
    }
}
