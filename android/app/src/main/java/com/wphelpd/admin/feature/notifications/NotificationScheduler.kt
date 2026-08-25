package com.wphelpd.admin.feature.notifications

import android.content.Context
import android.os.Build
import android.util.Log
import androidx.work.BackoffPolicy
import androidx.work.Constraints
import androidx.work.ExistingPeriodicWorkPolicy
import androidx.work.NetworkType
import androidx.work.OutOfQuotaPolicy
import androidx.work.PeriodicWorkRequestBuilder
import androidx.work.WorkInfo
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
     * Uses a 15-minute interval to align with Android Doze mode maintenance windows,
     * exponential backoff for resilience after transient failures, and expedited
     * execution on API 31+ to avoid being deferred when the job is first enqueued.
     */
    fun schedule(context: Context) {
        val constraints = Constraints.Builder()
            .setRequiredNetworkType(NetworkType.CONNECTED)
            .setRequiresCharging(false)
            .setRequiresDeviceIdle(false)
            .build()

        val request = PeriodicWorkRequestBuilder<NotificationPoller>(
            POLL_INTERVAL_MINUTES, TimeUnit.MINUTES
        )
            .setConstraints(constraints)
            .setBackoffCriteria(
                BackoffPolicy.EXPONENTIAL,
                INITIAL_BACKOFF_DELAY_SECONDS,
                TimeUnit.SECONDS
            )
            .apply {
                if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
                    setExpedited(OutOfQuotaPolicy.RUN_AS_NON_EXPEDITED_WORK_REQUEST)
                }
            }
            .build()

        WorkManager.getInstance(context).enqueueUniquePeriodicWork(
            WORK_NAME,
            ExistingPeriodicWorkPolicy.KEEP,
            request
        )

        Log.d(TAG, "Notification polling scheduled (${POLL_INTERVAL_MINUTES}min interval).")
    }

    /**
     * Returns true if a polling job is currently enqueued or running.
     * Useful as a failsafe to detect if WorkManager dropped the job.
     */
    fun isScheduled(context: Context): Boolean {
        return try {
            val infos = WorkManager.getInstance(context)
                .getWorkInfosForUniqueWork(WORK_NAME)
                .get()
            infos.isNotEmpty() && infos.any { info ->
                info.state == WorkInfo.State.ENQUEUED || info.state == WorkInfo.State.RUNNING
            }
        } catch (e: Exception) {
            Log.w(TAG, "Error checking if polling is scheduled: ${e.message}")
            false
        }
    }

    /**
     * Cancels the periodic poll job (e.g. on logout).
     */
    fun cancel(context: Context) {
        WorkManager.getInstance(context).cancelUniqueWork(WORK_NAME)
        Log.d(TAG, "Notification polling cancelled.")
    }

    /** Polling interval — 15 minutes aligns with Android Doze maintenance windows. */
    private const val POLL_INTERVAL_MINUTES = 15L
    private const val INITIAL_BACKOFF_DELAY_SECONDS = 60L
}
