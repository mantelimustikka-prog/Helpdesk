package com.wphelpd.admin.feature.notifications

import android.content.Context
import android.os.Build
import android.util.Log
import androidx.work.BackoffPolicy
import androidx.work.Constraints
import androidx.work.ExistingWorkPolicy
import androidx.work.NetworkType
import androidx.work.OutOfQuotaPolicy
import androidx.work.OneTimeWorkRequestBuilder
import androidx.work.WorkInfo
import androidx.work.WorkManager
import java.util.concurrent.TimeUnit

private const val TAG = "NotificationScheduler"
private const val WORK_NAME = "hd_notification_poll"

/**
 * Manages scheduling and cancellation of the [NotificationPoller] WorkManager job.
 */
object NotificationScheduler {

    /**
     * Schedules the initial poll immediately. The poll worker then chains itself
     * for [POLL_INTERVAL_MINUTES]-minute delayed execution.
     */
    fun schedule(context: Context) {
        enqueueOneTimeWork(context, delayMinutes = 0L, policy = ExistingWorkPolicy.REPLACE)
        Log.d(TAG, "Notification polling scheduled (immediate bootstrap).")
    }

    /**
     * Schedules the next delayed poll run. Called by [NotificationPoller] after success.
     */
    fun scheduleNext(context: Context) {
        enqueueOneTimeWork(
            context = context,
            delayMinutes = POLL_INTERVAL_MINUTES,
            policy = ExistingWorkPolicy.KEEP
        )
        Log.d(TAG, "Next notification poll scheduled in ${POLL_INTERVAL_MINUTES} minute(s).")
    }

    private fun enqueueOneTimeWork(
        context: Context,
        delayMinutes: Long,
        policy: ExistingWorkPolicy
    ) {
        val request = OneTimeWorkRequestBuilder<NotificationPoller>()
            .setInitialDelay(delayMinutes, TimeUnit.MINUTES)
            .setConstraints(
                Constraints.Builder()
                    .setRequiredNetworkType(NetworkType.CONNECTED)
                    .setRequiresCharging(false)
                    .setRequiresDeviceIdle(false)
                    .build()
            )
            .setBackoffCriteria(
                BackoffPolicy.EXPONENTIAL,
                INITIAL_BACKOFF_DELAY_SECONDS,
                TimeUnit.SECONDS
            )
            .apply {
                if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S && delayMinutes == 0L) {
                    setExpedited(OutOfQuotaPolicy.RUN_AS_NON_EXPEDITED_WORK_REQUEST)
                }
            }
            .build()

        WorkManager.getInstance(context).enqueueUniqueWork(WORK_NAME, policy, request)
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

    /** Cancels the poll job (e.g. on logout). */
    fun cancel(context: Context) {
        WorkManager.getInstance(context).cancelUniqueWork(WORK_NAME)
        Log.d(TAG, "Notification polling cancelled.")
    }

    /** Polling interval target — 5 minutes for near-real-time ticket/reply visibility. */
    private const val POLL_INTERVAL_MINUTES = 5L
    private const val INITIAL_BACKOFF_DELAY_SECONDS = 60L
}
