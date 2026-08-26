package com.wphelpd.admin.feature.notifications

import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent
import android.util.Log

private const val TAG = "BootCompletedReceiver"
private const val ACTION_QUICKBOOT_POWERON = "android.intent.action.QUICKBOOT_POWERON"

/**
 * Restarts notification polling after a device reboot.
 *
 * WorkManager jobs are cleared when the device powers off. This receiver listens
 * for [Intent.ACTION_BOOT_COMPLETED] and re-schedules the poller so that
 * notifications resume automatically without requiring the user to open the app.
 */
class BootCompletedReceiver : BroadcastReceiver() {

    override fun onReceive(context: Context, intent: Intent) {
        if (!shouldRescheduleNotificationPolling(intent.action)) {
            return
        }

        try {
            Log.d(TAG, "Device boot completed — rescheduling notification polling.")
            NotificationScheduler.schedule(context)
            Log.d(TAG, "Notification poller rescheduled successfully.")
        } catch (e: Exception) {
            Log.e(TAG, "Failed to reschedule notifications on boot: ${e.message}", e)
        }
    }
}

internal fun shouldRescheduleNotificationPolling(action: String?): Boolean =
    action == Intent.ACTION_BOOT_COMPLETED || action == ACTION_QUICKBOOT_POWERON
