package com.wphelpd.admin.feature.push

import android.content.Context

class PushTokenStateStore(context: Context) {
    private val prefs = context.applicationContext.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)

    fun saveCurrentToken(token: String) {
        prefs.edit().putString(KEY_CURRENT_TOKEN, token.trim()).apply()
    }

    fun currentToken(): String? = prefs.getString(KEY_CURRENT_TOKEN, null)?.takeIf { it.isNotBlank() }

    fun isAlreadyRegistered(token: String, siteUrl: String, username: String): Boolean =
        prefs.getString(KEY_REGISTERED_TOKEN, null) == token &&
            prefs.getString(KEY_REGISTERED_SITE_URL, null) == siteUrl &&
            prefs.getString(KEY_REGISTERED_USERNAME, null) == username

    fun markRegistered(token: String, siteUrl: String, username: String) {
        prefs.edit()
            .putString(KEY_REGISTERED_TOKEN, token)
            .putString(KEY_REGISTERED_SITE_URL, siteUrl)
            .putString(KEY_REGISTERED_USERNAME, username)
            .apply()
    }

    fun clearRegisteredState() {
        prefs.edit()
            .remove(KEY_REGISTERED_TOKEN)
            .remove(KEY_REGISTERED_SITE_URL)
            .remove(KEY_REGISTERED_USERNAME)
            .apply()
    }

    fun wasNotificationHandled(notificationId: String): Boolean =
        prefs.getString(KEY_LAST_HANDLED_NOTIFICATION, null) == notificationId

    fun markNotificationHandled(notificationId: String) {
        prefs.edit().putString(KEY_LAST_HANDLED_NOTIFICATION, notificationId).apply()
    }

    companion object {
        private const val PREFS_NAME = "push_state_prefs"
        private const val KEY_CURRENT_TOKEN = "current_token"
        private const val KEY_REGISTERED_TOKEN = "registered_token"
        private const val KEY_REGISTERED_SITE_URL = "registered_site_url"
        private const val KEY_REGISTERED_USERNAME = "registered_username"
        private const val KEY_LAST_HANDLED_NOTIFICATION = "last_handled_notification"
    }
}
