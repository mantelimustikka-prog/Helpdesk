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
     * Uses synchronous commit() to guarantee the value is flushed to disk before the
     * caller proceeds — prevents stale re-notifications if the process is killed before
     * an async apply() write completes.
     */
    fun setLastCheckedTimestamp(timestamp: Long) {
        prefs.edit().putLong(KEY_LAST_CHECKED, timestamp).commit()
    }

    /**
     * Returns the wall-clock millisecond timestamp of the last successful poll,
     * or 0 if never recorded.
     */
    fun getLastSuccessfulPollTime(): Long {
        return prefs.getLong(KEY_LAST_SUCCESSFUL_POLL, 0L)
    }

    /**
     * Persists the wall-clock millisecond timestamp of the last successful poll.
     */
    fun setLastSuccessfulPollTime(timestamp: Long) {
        prefs.edit().putLong(KEY_LAST_SUCCESSFUL_POLL, timestamp).apply()
    }

    /**
     * Clears all stored notification state (e.g. on logout).
     */
    fun clear() {
        prefs.edit().clear().apply()
    }

    companion object {
        private const val PREFS_NAME = "hd_notification_prefs"
        private const val KEY_LAST_CHECKED = "last_notification_check"
        private const val KEY_LAST_SUCCESSFUL_POLL = "last_successful_poll"
    }
}
