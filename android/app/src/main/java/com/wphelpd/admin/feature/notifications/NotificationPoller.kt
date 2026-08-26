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
 * Runs every 15 minutes (scheduled by [NotificationScheduler]).
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
        Log.d(TAG, "NotificationPoller.doWork() started (attempt ${runAttemptCount + 1})")

        val config = serverConfigRepository.load() ?: run {
            Log.d(TAG, "No auth config stored — skipping poll.")
            return Result.success()
        }

        val sinceTimestamp = prefs.getLastCheckedTimestamp().let { stored ->
            if (stored > 0L) stored else (System.currentTimeMillis() / 1000L) - DEFAULT_LOOKBACK_SECONDS
        }

        Log.d(TAG, "Polling for notifications since $sinceTimestamp")

        return when (val result = repository.getNotificationsSince(config, sinceTimestamp)) {
            is NetworkResult.Success -> {
                val response = result.value
                val newTickets = response.newTickets
                val newReplies = response.newReplies

                if (newTickets.isNotEmpty() || newReplies.isNotEmpty()) {
                    Log.i(
                        TAG,
                        "Poll found ${newTickets.size} new ticket(s) and ${newReplies.size} new reply/replies."
                    )
                    try {
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
                    } catch (e: Exception) {
                        Log.e(TAG, "Failed while publishing notification event: ${e.message}", e)
                    }
                } else {
                    Log.d(TAG, "Poll found no new items.")
                }

                // Update the last-checked and last-successful-poll timestamps.
                prefs.setLastCheckedTimestamp(System.currentTimeMillis() / 1000L)
                prefs.setLastSuccessfulPollTime(System.currentTimeMillis())
                NotificationScheduler.scheduleNext(applicationContext)
                Log.d(TAG, "NotificationPoller completed successfully.")

                Result.success()
            }

            is NetworkResult.Failure -> {
                Log.w(TAG, "Poll failed: ${result.message} (attempt ${runAttemptCount + 1})")
                result.throwable?.let { Log.w(TAG, "Poll throwable: ${it.javaClass.simpleName}", it) }
                if (runAttemptCount < MAX_INLINE_RETRY_ATTEMPTS) {
                    Result.retry()
                } else {
                    Log.w(TAG, "Retry limit reached for this run — scheduling next poll window.")
                    NotificationScheduler.scheduleNext(applicationContext)
                    Result.failure()
                }
            }
        }
    }

    companion object {
        /** Fall-back look-back window when no timestamp is stored (1 hour). */
        private const val DEFAULT_LOOKBACK_SECONDS = 3600L
        /** Number of WorkManager retry attempts before scheduling the next poll window manually. */
        private const val MAX_INLINE_RETRY_ATTEMPTS = 2
    }
}
