package com.wphelpd.admin.feature.notifications

import android.app.NotificationChannel
import android.app.NotificationManager
import android.content.Context
import android.os.Build
import androidx.core.app.NotificationCompat
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

    private const val CHANNEL_ID = "hd_notifications"
    private const val CHANNEL_NAME = "WP HelpD Notifications"
    private const val NOTIFICATION_ID = 1001

    fun showNotification(
        context: Context,
        newTicketCount: Int,
        newReplyCount: Int
    ) {
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

        if (message.isEmpty()) return

        val notification = NotificationCompat.Builder(context, CHANNEL_ID)
            .setSmallIcon(R.drawable.ic_notification)
            .setContentTitle("New Helpdesk Activity")
            .setContentText(message)
            .setStyle(NotificationCompat.BigTextStyle().bigText(message))
            .setAutoCancel(true)
            .setPriority(NotificationCompat.PRIORITY_HIGH)
            .build()

        notificationManager.notify(NOTIFICATION_ID, notification)
    }
}
