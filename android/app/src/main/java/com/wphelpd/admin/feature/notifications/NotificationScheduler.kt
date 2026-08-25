package com.wphelpd.admin.feature.notifications

import android.content.Context
import android.util.Log
import androidx.work.Constraints
import androidx.work.ExistingPeriodicWorkPolicy
import androidx.work.NetworkType
import androidx.work.PeriodicWorkRequestBuilder
import androidx.work.WorkManager
import java.util.concurrent.TimeUnit

private const val TAG = "NotificationScheduler"
private const val WORK_NAME = "hd_notification_poll"

/**
 * Manages scheduling and cancellation of the periodic [NotificationPoller] WorkManager job.
 */
object NotificationScheduler {

    /**
     * Schedules a periodic poll every [POLL_INTERVAL_MINUTES] minutes.
     * Safe to call multiple times — uses [ExistingPeriodicWorkPolicy.KEEP] so an
     * existing job is not replaced.
     *
     * The interval is 15 minutes to align with Android's Doze maintenance windows,
     * acting as a reliable fallback when FCM push delivery is unavailable.
     */
    fun schedule(context: Context) {
        val constraints = Constraints.Builder()
            .setRequiredNetworkType(NetworkType.CONNECTED)
            .build()

        val request = PeriodicWorkRequestBuilder<NotificationPoller>(
            POLL_INTERVAL_MINUTES, TimeUnit.MINUTES
        )
            .setConstraints(constraints)
            .build()

        WorkManager.getInstance(context).enqueueUniquePeriodicWork(
            WORK_NAME,
            ExistingPeriodicWorkPolicy.KEEP,
            request
        )

        Log.d(TAG, "Notification polling scheduled (${POLL_INTERVAL_MINUTES}min interval).")
    }

    /**
     * Cancels the periodic poll job (e.g. on logout).
     */
    fun cancel(context: Context) {
        WorkManager.getInstance(context).cancelUniqueWork(WORK_NAME)
        Log.d(TAG, "Notification polling cancelled.")
    }

    // 15 minutes aligns with Android Doze maintenance windows and acts as a
    // reliable fallback when FCM push delivery is delayed or unavailable.
    private const val POLL_INTERVAL_MINUTES = 15L
}
