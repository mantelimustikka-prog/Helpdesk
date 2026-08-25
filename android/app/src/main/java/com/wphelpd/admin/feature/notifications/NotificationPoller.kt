package com.wphelpd.admin.feature.notifications

import android.content.Context
import android.util.Log
import androidx.work.CoroutineWorker
import androidx.work.WorkerParameters
import com.wphelpd.admin.core.config.SecureServerConfigRepository
import com.wphelpd.admin.core.network.NetworkResult
import com.wphelpd.admin.data.repository.HelpdeskRepository

private const val TAG = "NotificationPoller"

/**
 * WorkManager worker that polls the server for new tickets and replies.
 *
 * Runs every 15 minutes (scheduled by [NotificationScheduler]) as a reliable fallback
 * when FCM push delivery is unavailable or delayed.  When FCM delivers a push message
 * via [com.wphelpd.admin.core.firebase.HelpdeskMessagingService], it advances the
 * last-checked timestamp so this poller will not re-surface the same items as
 * duplicates.
 *
 * Uses the stored auth config to authenticate and the [NotificationPreferences]
 * to track the last-checked timestamp.
 */
class NotificationPoller(
    appContext: Context,
    params: WorkerParameters
) : CoroutineWorker(appContext, params) {

    private val repository = HelpdeskRepository()
    private val prefs = NotificationPreferences(appContext)
    private val serverConfigRepository = SecureServerConfigRepository(appContext)

    override suspend fun doWork(): Result {
        val config = serverConfigRepository.load() ?: run {
            Log.d(TAG, "No auth config stored — skipping poll.")
            return Result.success()
        }

        val sinceTimestamp = prefs.getLastCheckedTimestamp().let { stored ->
            if (stored > 0L) stored else (System.currentTimeMillis() / 1000L) - DEFAULT_LOOKBACK_SECONDS
        }

        Log.d(TAG, "Polling for notifications since $sinceTimestamp (fallback poll — FCM is primary)")

        return when (val result = repository.getNotificationsSince(config, sinceTimestamp)) {
            is NetworkResult.Success -> {
                val response = result.value
                val newTickets = response.newTickets
                val newReplies = response.newReplies

                // Update the last-checked timestamp.
                prefs.setLastCheckedTimestamp(System.currentTimeMillis() / 1000L)

                if (newTickets.isNotEmpty() || newReplies.isNotEmpty()) {
                    Log.i(
                        TAG,
                        "Poll found ${newTickets.size} new ticket(s) and ${newReplies.size} new reply/replies."
                    )
                    // Show system notification visible on lock screen / notification panel.
                    NotificationChannelManager.showNotification(
                        applicationContext,
                        newTickets.size,
                        newReplies.size
                    )
                    // Also post in-app event for when the user is actively viewing the app.
                    NotificationEventBus.post(
                        NotificationEvent(
                            newTicketCount = newTickets.size,
                            newReplyCount = newReplies.size
                        )
                    )
                } else {
                    Log.d(TAG, "Poll found no new items.")
                }

                Result.success()
            }

            is NetworkResult.Failure -> {
                Log.w(TAG, "Poll failed: ${result.message}")
                Result.retry()
            }
        }
    }

    companion object {
        /** Fall-back look-back window when no timestamp is stored (1 hour). */
        private const val DEFAULT_LOOKBACK_SECONDS = 3600L
    }
}
