package com.wphelpd.admin.feature.notifications

import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent
import android.util.Log
import com.wphelpd.admin.core.config.SecureServerConfigRepository

private const val TAG = "BootCompletedReceiver"

/**
 * Restarts notification polling after a device reboot.
 *
 * WorkManager periodic jobs are cancelled when the device powers off. This receiver
 * listens for [Intent.ACTION_BOOT_COMPLETED] and re-schedules the poller so that
 * notifications resume automatically without requiring the user to open the app.
 */
class BootCompletedReceiver : BroadcastReceiver() {

    override fun onReceive(context: Context, intent: Intent) {
        if (intent.action != Intent.ACTION_BOOT_COMPLETED &&
            intent.action != "android.intent.action.QUICKBOOT_POWERON"
        ) {
            return
        }

        Log.d(TAG, "Device booted — checking whether user is logged in.")

        // Only reschedule if the user is logged in (auth config exists).
        val serverConfigRepository = SecureServerConfigRepository(context)
        if (serverConfigRepository.load() != null) {
            Log.d(TAG, "User is logged in — rescheduling notification polling.")
            NotificationScheduler.schedule(context)
        } else {
            Log.d(TAG, "No logged-in user — skipping polling reschedule.")
        }
    }
}
