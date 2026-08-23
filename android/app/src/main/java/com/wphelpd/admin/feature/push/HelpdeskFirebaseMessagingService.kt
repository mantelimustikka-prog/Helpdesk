package com.wphelpd.admin.feature.push

import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.content.Intent
import android.net.Uri
import android.os.Build
import androidx.core.app.NotificationCompat
import androidx.core.app.NotificationManagerCompat
import com.google.firebase.messaging.FirebaseMessagingService
import com.google.firebase.messaging.RemoteMessage
import com.wphelpd.admin.MainActivity
import com.wphelpd.admin.R
import com.wphelpd.admin.core.config.SecureServerConfigRepository
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.SupervisorJob
import kotlinx.coroutines.cancel
import kotlinx.coroutines.launch

class HelpdeskFirebaseMessagingService : FirebaseMessagingService() {
    private val serviceScope = CoroutineScope(SupervisorJob() + Dispatchers.IO)

    override fun onDestroy() {
        serviceScope.cancel()
        super.onDestroy()
    }

    override fun onNewToken(token: String) {
        val stateStore = PushTokenStateStore(applicationContext)
        stateStore.saveCurrentToken(token)
        val config = SecureServerConfigRepository(applicationContext).load() ?: return
        serviceScope.launch {
            PushTokenSyncManager(stateStore = stateStore).registerIfNeeded(config)
        }
    }

    override fun onMessageReceived(message: RemoteMessage) {
        val payload = PushNotificationPayloadParser.parse(message.data) ?: return
        val stateStore = PushTokenStateStore(applicationContext)
        if (stateStore.wasNotificationHandled(payload.notificationId)) return
        stateStore.markNotificationHandled(payload.notificationId)
        ensureNotificationChannel()
        val intent = Intent(this, MainActivity::class.java).apply {
            action = MainActivity.ACTION_OPEN_TICKET
            putExtra(MainActivity.EXTRA_TICKET_ID, payload.ticketId)
            data = Uri.parse(payload.deepLink)
            flags = Intent.FLAG_ACTIVITY_CLEAR_TOP or Intent.FLAG_ACTIVITY_SINGLE_TOP
        }
        val pendingIntent = PendingIntent.getActivity(
            this,
            payload.notificationId.hashCode(),
            intent,
            PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
        )
        val title = message.notification?.title ?: "WP HelpD update"
        val body = message.notification?.body ?: "A ticket needs your attention."
        val notification = NotificationCompat.Builder(this, CHANNEL_ID)
            .setSmallIcon(R.mipmap.ic_launcher)
            .setContentTitle(title)
            .setContentText(body)
            .setPriority(NotificationCompat.PRIORITY_HIGH)
            .setAutoCancel(true)
            .setContentIntent(pendingIntent)
            .build()
        NotificationManagerCompat.from(this).notify(payload.notificationId.hashCode(), notification)
    }

    private fun ensureNotificationChannel() {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.O) return
        val manager = getSystemService(NotificationManager::class.java)
        if (manager.getNotificationChannel(CHANNEL_ID) != null) return
        manager.createNotificationChannel(
            NotificationChannel(
                CHANNEL_ID,
                "WP HelpD Tickets",
                NotificationManager.IMPORTANCE_HIGH
            ).apply {
                description = "Alerts for new tickets and customer replies."
            }
        )
    }

    companion object {
        const val CHANNEL_ID = "wphelpd_tickets"
    }
}
