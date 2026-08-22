package com.wphelpd.admin.core.network

import com.wphelpd.admin.BuildConfig
import com.wphelpd.admin.data.api.HelpdeskAdminApi
import okhttp3.HttpUrl
import okhttp3.HttpUrl.Companion.toHttpUrl
import okhttp3.OkHttpClient
import okhttp3.logging.HttpLoggingInterceptor
import retrofit2.Retrofit
import retrofit2.converter.gson.GsonConverterFactory

object ApiClientFactory {
    fun create(config: AuthConfig): HelpdeskAdminApi {
        val baseUrl = config.adminApiUrl()
        val clientBuilder = OkHttpClient.Builder()
            .addInterceptor(AuthInterceptor(config))

        if (BuildConfig.DEBUG) {
            clientBuilder.addInterceptor(
                HttpLoggingInterceptor().apply {
                    level = HttpLoggingInterceptor.Level.BASIC
                }
            )
        }

        val client = clientBuilder.build()

        return Retrofit.Builder()
            .baseUrl(baseUrl)
            .client(client)
            .addConverterFactory(GsonConverterFactory.create())
            .build()
            .create(HelpdeskAdminApi::class.java)
    }
}

fun AuthConfig.adminApiUrl(): HttpUrl {
    val normalized = siteUrl.trim().removeSuffix("/")

    require(normalized.startsWith("https://")) {
        "WP HelpD requires an HTTPS site URL."
    }

    val fullUrl = if (normalized.contains("/wp-json/helpdesk/v1/admin")) {
        "$normalized/"
    } else {
        "$normalized/wp-json/helpdesk/v1/admin/"
    }

    return fullUrl.toHttpUrl()
}
