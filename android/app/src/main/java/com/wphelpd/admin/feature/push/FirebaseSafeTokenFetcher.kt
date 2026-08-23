package com.wphelpd.admin.feature.push

import android.util.Log
import com.google.firebase.messaging.FirebaseMessaging

/**
 * Wraps Firebase token retrieval so that missing or misconfigured Firebase cannot crash startup.
 *
 * If Firebase is not present or the token fetch fails for any reason, the failure is logged
 * and silently ignored. The app continues launching normally.
 */
object FirebaseSafeTokenFetcher {

    private const val TAG = "FirebaseSafeTokenFetcher"

    /**
     * Attempts to fetch the current FCM registration token and passes it to [onToken] on success.
     * Any exception thrown by [FirebaseMessaging.getInstance] or the resulting [com.google.android.gms.tasks.Task]
     * is caught and logged so startup is never interrupted.
     */
    fun fetchToken(onToken: (String) -> Unit) {
        runCatching {
            FirebaseMessaging.getInstance().token
                .addOnSuccessListener { token ->
                    if (token.isNotBlank()) {
                        onToken(token)
                    }
                }
                .addOnFailureListener { e ->
                    Log.w(TAG, "FCM token fetch failed — Firebase may not be configured", e)
                }
        }.onFailure { e ->
            Log.w(TAG, "Firebase is not available — skipping FCM token fetch", e)
        }
    }
}
