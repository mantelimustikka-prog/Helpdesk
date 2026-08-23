package com.wphelpd.admin.core.config

import android.content.Context
import android.content.SharedPreferences
import androidx.security.crypto.EncryptedSharedPreferences
import androidx.security.crypto.MasterKey
import com.wphelpd.admin.core.network.AuthConfig

/**
 * Persists server credentials using [EncryptedSharedPreferences] so that sensitive
 * values (username, application password, optional nonce) are stored encrypted on
 * the device and never written in plain text.
 */
class SecureServerConfigRepository(context: Context) : ServerConfigRepository {

    private val prefs: SharedPreferences by lazy {
        val masterKey = MasterKey.Builder(context)
            .setKeyScheme(MasterKey.KeyScheme.AES256_GCM)
            .build()

        EncryptedSharedPreferences.create(
            context,
            PREFS_NAME,
            masterKey,
            EncryptedSharedPreferences.PrefKeyEncryptionScheme.AES256_SIV,
            EncryptedSharedPreferences.PrefValueEncryptionScheme.AES256_GCM
        )
    }

    override fun load(): AuthConfig? {
        val siteUrl = prefs.getString(KEY_SITE_URL, null) ?: return null
        val username = prefs.getString(KEY_USERNAME, null) ?: return null
        val appPassword = prefs.getString(KEY_APP_PASSWORD, null) ?: return null
        val wpNonce = prefs.getString(KEY_WP_NONCE, "") ?: ""
        return AuthConfig(
            siteUrl = siteUrl,
            username = username,
            applicationPassword = appPassword,
            wpNonce = wpNonce
        )
    }

    override fun save(config: AuthConfig) {
        prefs.edit()
            .putString(KEY_SITE_URL, config.siteUrl)
            .putString(KEY_USERNAME, config.username)
            .putString(KEY_APP_PASSWORD, config.applicationPassword)
            .putString(KEY_WP_NONCE, config.wpNonce)
            .apply()
    }

    override fun clear() {
        prefs.edit().clear().apply()
    }

    companion object {
        private const val PREFS_NAME = "server_config_prefs"
        private const val KEY_SITE_URL = "site_url"
        private const val KEY_USERNAME = "username"
        private const val KEY_APP_PASSWORD = "app_password"
        private const val KEY_WP_NONCE = "wp_nonce"
    }
}
