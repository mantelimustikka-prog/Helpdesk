package com.wphelpd.admin.feature.applock

import android.content.Context
import android.content.SharedPreferences
import androidx.security.crypto.EncryptedSharedPreferences
import androidx.security.crypto.MasterKey
import java.security.MessageDigest
import java.security.SecureRandom
import kotlin.io.encoding.Base64
import kotlin.io.encoding.ExperimentalEncodingApi

/**
 * Manages the local app password: hashing, storage, and verification.
 *
 * Stores only a salted SHA-256 hash; the plain password is never persisted.
 * The hash and salt are held in [EncryptedSharedPreferences] so that even the
 * derived values are protected at rest by AES-256-GCM (backed by the Android
 * Keystore) and cannot be extracted by `adb backup` or non-root read access.
 */
class AppLockManager(context: Context) : AppLockRepository {

    private val appContext = context.applicationContext

    private val prefs: SharedPreferences by lazy {
        val masterKey = MasterKey.Builder(appContext)
            .setKeyScheme(MasterKey.KeyScheme.AES256_GCM)
            .build()
        EncryptedSharedPreferences.create(
            appContext,
            PREFS_NAME,
            masterKey,
            EncryptedSharedPreferences.PrefKeyEncryptionScheme.AES256_SIV,
            EncryptedSharedPreferences.PrefValueEncryptionScheme.AES256_GCM
        )
    }

    private val secureRandom = SecureRandom()

    /** Returns true if a local password has already been set. */
    override fun isPasswordSet(): Boolean = prefs.contains(KEY_HASH) && prefs.contains(KEY_SALT)

    /**
     * Creates and stores the app password hash.
     * Overwrites any previously stored hash.
     */
    @OptIn(ExperimentalEncodingApi::class)
    override fun setPassword(password: String) {
        val saltBytes = ByteArray(SALT_LENGTH).also { secureRandom.nextBytes(it) }
        val salt = Base64.encode(saltBytes)
        val hash = hash(password, salt)
        prefs.edit()
            .putString(KEY_SALT, salt)
            .putString(KEY_HASH, hash)
            .apply()
    }

    /** Returns true if [password] matches the stored hash. */
    override fun verifyPassword(password: String): Boolean {
        val salt = prefs.getString(KEY_SALT, null) ?: return false
        val storedHash = prefs.getString(KEY_HASH, null) ?: return false
        return MessageDigest.isEqual(
            hash(password, salt).toByteArray(Charsets.UTF_8),
            storedHash.toByteArray(Charsets.UTF_8)
        )
    }

    @OptIn(ExperimentalEncodingApi::class)
    private fun hash(password: String, salt: String): String {
        val digest = MessageDigest.getInstance("SHA-256")
        digest.update(salt.toByteArray(Charsets.UTF_8))
        val hashBytes = digest.digest(password.toByteArray(Charsets.UTF_8))
        return Base64.encode(hashBytes)
    }

    companion object {
        private const val PREFS_NAME = "app_lock_prefs"
        private const val KEY_HASH = "password_hash"
        private const val KEY_SALT = "password_salt"
        private const val SALT_LENGTH = 16
    }
}
