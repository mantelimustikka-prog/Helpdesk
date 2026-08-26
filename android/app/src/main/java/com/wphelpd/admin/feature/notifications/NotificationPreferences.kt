package com.wphelpd.admin.feature.notifications

import android.content.Context
import android.content.SharedPreferences

/**
 * Stores and retrieves the last-checked timestamp for notification polling,
 * acknowledgment hashes for deduplication, and pending notification state.
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

    // --- Acknowledgment tracking ---

    /** Returns the hash of the last notification event the user has seen/dismissed. */
    fun getLastAcknowledgedEventHash(): String =
        prefs.getString(KEY_LAST_ACKNOWLEDGED_EVENT_HASH, "") ?: ""

    /** Persists the hash of the notification event the user just acknowledged. */
    fun setLastAcknowledgedEventHash(hash: String) {
        prefs.edit().putString(KEY_LAST_ACKNOWLEDGED_EVENT_HASH, hash).commit()
    }

    /** Returns the wall-clock ms timestamp of the last notification dismiss, or 0. */
    fun getLastNotificationDismissTime(): Long =
        prefs.getLong(KEY_LAST_NOTIFICATION_DISMISS_TIME, 0L)

    /** Persists the wall-clock ms timestamp when user dismissed the notification. */
    fun setLastNotificationDismissTime(timeMs: Long) {
        prefs.edit().putLong(KEY_LAST_NOTIFICATION_DISMISS_TIME, timeMs).commit()
    }

    // --- Pending notification persistence ---

    /**
     * Returns the last pending [NotificationEvent] that has not yet been acknowledged,
     * or null if none is stored.
     */
    fun getLastPendingNotification(): NotificationEvent? {
        val ticketCount = prefs.getInt(KEY_LAST_PENDING_TICKET_COUNT, -1)
        val replyCount = prefs.getInt(KEY_LAST_PENDING_REPLY_COUNT, -1)
        return if (ticketCount >= 0 && replyCount >= 0 && (ticketCount > 0 || replyCount > 0)) {
            NotificationEvent(ticketCount, replyCount)
        } else {
            null
        }
    }

    /** Persists a pending notification so it survives lock/unlock cycles. */
    fun setLastPendingNotification(event: NotificationEvent) {
        prefs.edit()
            .putInt(KEY_LAST_PENDING_TICKET_COUNT, event.newTicketCount)
            .putInt(KEY_LAST_PENDING_REPLY_COUNT, event.newReplyCount)
            .apply()
    }

    /** Removes the stored pending notification (after user views or dismisses). */
    fun clearLastPendingNotification() {
        prefs.edit()
            .remove(KEY_LAST_PENDING_TICKET_COUNT)
            .remove(KEY_LAST_PENDING_REPLY_COUNT)
            .commit()
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
        private const val KEY_LAST_ACKNOWLEDGED_EVENT_HASH = "last_acknowledged_event_hash"
        private const val KEY_LAST_NOTIFICATION_DISMISS_TIME = "last_notification_dismiss_time_ms"
        private const val KEY_LAST_PENDING_TICKET_COUNT = "last_pending_ticket_count"
        private const val KEY_LAST_PENDING_REPLY_COUNT = "last_pending_reply_count"
    }
}
