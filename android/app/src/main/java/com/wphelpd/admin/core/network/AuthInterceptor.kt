package com.wphelpd.admin.core.network

import okhttp3.Credentials
import okhttp3.Interceptor
import okhttp3.Response

class AuthInterceptor(
    private val config: AuthConfig
) : Interceptor {
    override fun intercept(chain: Interceptor.Chain): Response {
        val requestBuilder = chain.request()
            .newBuilder()
            .header("Accept", "application/json")
            .header(
                "Authorization",
                Credentials.basic(config.username.trim(), config.applicationPassword)
            )

        config.wpNonce.trim()
            .takeIf(String::isNotEmpty)
            ?.let { requestBuilder.header("X-WP-Nonce", it) }

        return chain.proceed(requestBuilder.build())
    }
}
