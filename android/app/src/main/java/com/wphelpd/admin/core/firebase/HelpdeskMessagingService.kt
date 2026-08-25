package com.wphelpd.admin.core.firebase

import android.util.Log
import com.google.firebase.messaging.FirebaseMessagingService
import com.google.firebase.messaging.RemoteMessage
import com.wphelpd.admin.feature.notifications.NotificationChannelManager
import com.wphelpd.admin.feature.notifications.NotificationEvent
import com.wphelpd.admin.feature.notifications.NotificationEventBus
import com.wphelpd.admin.feature.notifications.NotificationPreferences

private const val TAG = "HelpdeskMsgService"

// FCM payload keys sent by the PHP backend.
private const val KEY_NEW_TICKETS = "new_tickets"
private const val KEY_NEW_REPLIES = "new_replies"

/**
 * FCM push-message receiver for the WP HelpD Admin app.
 *
 * When the WordPress backend sends an FCM data message (new ticket or reply), this
 * service wakes the app from Doze mode, shows a system notification immediately,
 * and posts a [NotificationEvent] on the [NotificationEventBus] for any active
 * in-app UI.
 *
 * It also advances the polling timestamp so the [NotificationPoller] fallback does
 * not show a duplicate notification for the same items.
 *
 * Token lifecycle:
 * - [onNewToken] is called by Firebase whenever the registration token is refreshed.
 *   The new token is stored locally via [FCMTokenManager]; the app will re-register
 *   it with the backend on the next successful login.
 */
class HelpdeskMessagingService : FirebaseMessagingService() {

    override fun onMessageReceived(message: RemoteMessage) {
        Log.d(TAG, "FCM message received from ${message.from}")

        val data = message.data

        // Parse ticket/reply counts from the data payload.
        val newTickets = data[KEY_NEW_TICKETS]?.toIntOrNull() ?: 0
        val newReplies = data[KEY_NEW_REPLIES]?.toIntOrNull() ?: 0

        if (newTickets == 0 && newReplies == 0) {
            Log.d(TAG, "FCM message contained no actionable counts — ignoring.")
            return
        }

        Log.i(TAG, "FCM push: $newTickets new ticket(s), $newReplies new reply/replies.")

        // Show system notification immediately (bypasses Doze polling delay).
        NotificationChannelManager.showNotification(applicationContext, newTickets, newReplies)

        // Emit in-app event for any active UI subscribers.
        NotificationEventBus.post(NotificationEvent(newTicketCount = newTickets, newReplyCount = newReplies))

        // Advance the polling timestamp so the next WorkManager poll does not
        // re-surface the same tickets/replies as duplicates.
        NotificationPreferences(applicationContext)
            .setLastCheckedTimestamp(System.currentTimeMillis() / 1000L)
    }

    override fun onNewToken(token: String) {
        Log.d(TAG, "FCM token refreshed.")
        FCMTokenManager.storeToken(applicationContext, token)
        // The backend will receive the updated token the next time the user's
        // session makes an authenticated request that triggers token registration.
    }
}
