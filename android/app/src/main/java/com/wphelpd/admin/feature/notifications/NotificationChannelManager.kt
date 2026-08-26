package com.wphelpd.admin.feature.notifications

import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.content.Context
import android.content.Intent
import android.os.Build
import android.util.Log
import androidx.core.app.NotificationCompat
import com.wphelpd.admin.MainActivity
import com.wphelpd.admin.R

/**
 * Manages the Android notification channel and shows system notifications.
 *
 * On Android 8.0+ a [NotificationChannel] must be created before posting any
 * notification.  [showNotification] creates the channel lazily on every call so
 * it is safe to call from the background worker without a separate initialization
 * step.
 */
object NotificationChannelManager {

    private const val TAG = "NotificationChannelMgr"
    private const val CHANNEL_ID = "hd_notifications"
    private const val CHANNEL_NAME = "WP HelpD Notifications"
    private const val NOTIFICATION_ID = 1001

    fun showNotification(
        context: Context,
        newTicketCount: Int,
        newReplyCount: Int
    ) {
        try {
            val notificationManager =
                context.getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager

            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
                val channel = NotificationChannel(
                    CHANNEL_ID,
                    CHANNEL_NAME,
                    NotificationManager.IMPORTANCE_HIGH
                ).apply {
                    description = "Notifications for new tickets and replies"
                    enableVibration(true)
                    enableLights(true)
                }
                notificationManager.createNotificationChannel(channel)
            }

            val message = buildString {
                if (newTicketCount > 0) {
                    append("$newTicketCount new ticket${if (newTicketCount > 1) "s" else ""}")
                }
                if (newReplyCount > 0) {
                    if (newTicketCount > 0) append("\n")
                    append("$newReplyCount new repl${if (newReplyCount > 1) "ies" else "y"}")
                }
            }

            if (message.isEmpty()) {
                Log.d(TAG, "Skipping notification: no new items.")
                return
            }

            val intent = Intent(context, MainActivity::class.java).apply {
                flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TOP
            }
            val pendingIntent = PendingIntent.getActivity(
                context,
                0,
                intent,
                PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
            )

            val notification = NotificationCompat.Builder(context, CHANNEL_ID)
                .setSmallIcon(R.drawable.ic_notification)
                .setContentTitle("New Helpdesk Activity")
                .setContentText(message)
                .setStyle(NotificationCompat.BigTextStyle().bigText(message))
                .setAutoCancel(true)
                .setPriority(NotificationCompat.PRIORITY_HIGH)
                .setContentIntent(pendingIntent)
                .build()

            notificationManager.notify(NOTIFICATION_ID, notification)
            Log.i(TAG, "Notification posted (tickets=$newTicketCount, replies=$newReplyCount).")
        } catch (e: Exception) {
            Log.e(TAG, "Failed to post notification: ${e.message}", e)
        }
    }
}
