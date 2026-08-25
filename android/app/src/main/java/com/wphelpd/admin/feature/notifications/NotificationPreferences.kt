package com.wphelpd.admin.feature.notifications

import android.content.Context
import android.content.SharedPreferences

/**
 * Stores and retrieves the last-checked timestamp for notification polling.
 */
class NotificationPreferences(context: Context) {

    private val prefs: SharedPreferences = context.getSharedPreferences(
        PREFS_NAME,
        Context.MODE_PRIVATE
    )

    /**
     * Returns the Unix timestamp (seconds) of the last successful notification check,
     * or 0 if never checked.
     */
    fun getLastCheckedTimestamp(): Long {
        return prefs.getLong(KEY_LAST_CHECKED, 0L)
    }

    /**
     * Persists the Unix timestamp (seconds) of the last successful notification check.
     */
    fun setLastCheckedTimestamp(timestamp: Long) {
        prefs.edit().putLong(KEY_LAST_CHECKED, timestamp).apply()
    }

    /**
     * Clears the stored timestamp (e.g. on logout).
     */
    fun clear() {
        prefs.edit().remove(KEY_LAST_CHECKED).apply()
    }

    companion object {
        private const val PREFS_NAME = "hd_notification_prefs"
        private const val KEY_LAST_CHECKED = "last_notification_check"
    }
}
