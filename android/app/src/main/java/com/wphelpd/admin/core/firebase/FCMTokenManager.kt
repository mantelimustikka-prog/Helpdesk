package com.wphelpd.admin.core.firebase

import android.content.Context
import android.util.Log
import com.google.firebase.messaging.FirebaseMessaging

private const val TAG = "FCMTokenManager"
private const val PREFS_NAME = "hd_fcm_prefs"
private const val KEY_FCM_TOKEN = "fcm_token"

/**
 * Manages the FCM device registration token.
 *
 * Retrieves the current token from Firebase, caches it in private [SharedPreferences],
 * and exposes it so the app can register the token with the backend after login.
 * The token is automatically refreshed by [HelpdeskMessagingService.onNewToken].
 */
object FCMTokenManager {

    /**
     * Fetches the current FCM token from Firebase and stores it locally.
     *
     * Calls [onToken] with the token string on success, or null on failure.
     * Should be called after a successful login so the latest token is always
     * registered with the backend.
     */
    fun fetchAndStore(context: Context, onToken: (String?) -> Unit) {
        FirebaseMessaging.getInstance().token
            .addOnSuccessListener { token ->
                Log.d(TAG, "FCM token fetched successfully.")
                storeToken(context, token)
                onToken(token)
            }
            .addOnFailureListener { e ->
                Log.w(TAG, "Failed to fetch FCM token: ${e.message}")
                onToken(null)
            }
    }

    /**
     * Returns the last token cached in local storage, or null if none is stored.
     */
    fun getStoredToken(context: Context): String? {
        return context.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)
            .getString(KEY_FCM_TOKEN, null)
    }

    /**
     * Persists [token] to private SharedPreferences. Called automatically when
     * the token is fetched or refreshed.
     */
    fun storeToken(context: Context, token: String) {
        context.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)
            .edit()
            .putString(KEY_FCM_TOKEN, token)
            .apply()
        Log.d(TAG, "FCM token stored.")
    }

    /**
     * Clears the stored token (e.g. on logout so the old token is not reused).
     */
    fun clearToken(context: Context) {
        context.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)
            .edit()
            .remove(KEY_FCM_TOKEN)
            .apply()
        Log.d(TAG, "FCM token cleared.")
    }
}
