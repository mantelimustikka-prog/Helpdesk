package com.wphelpd.admin.data.api

import com.wphelpd.admin.BuildConfig
import com.wphelpd.admin.data.api.dto.RequestResetCodeRequestDto
import com.wphelpd.admin.data.api.dto.RequestResetCodeResponseDto
import com.wphelpd.admin.data.api.dto.ResetPasswordRequestDto
import com.wphelpd.admin.data.api.dto.ResetPasswordResponseDto
import com.wphelpd.admin.data.api.dto.VerifyResetCodeRequestDto
import com.wphelpd.admin.data.api.dto.VerifyResetCodeResponseDto
import okhttp3.HttpUrl
import okhttp3.HttpUrl.Companion.toHttpUrl
import okhttp3.OkHttpClient
import okhttp3.logging.HttpLoggingInterceptor
import retrofit2.Retrofit
import retrofit2.converter.gson.GsonConverterFactory
import retrofit2.http.Body
import retrofit2.http.POST

/**
 * Retrofit interface for public (unauthenticated) helpdesk endpoints.
 *
 * These endpoints do not require WordPress credentials and are available to
 * anyone who knows the site URL.  They are served under:
 *   /wp-json/helpdesk/v1/public/…
 */
interface HelpdeskPublicApi {

    @POST("app/request-reset-code")
    suspend fun requestPasswordResetCode(
        @Body request: RequestResetCodeRequestDto
    ): RequestResetCodeResponseDto

    @POST("app/verify-reset-code")
    suspend fun verifyPasswordResetCode(
        @Body request: VerifyResetCodeRequestDto
    ): VerifyResetCodeResponseDto

    @POST("app/reset-password")
    suspend fun resetPassword(
        @Body request: ResetPasswordRequestDto
    ): ResetPasswordResponseDto

    companion object {
        /**
         * Create a [HelpdeskPublicApi] backed by [siteUrl].
         *
         * No authentication headers are added – these endpoints are public.
         *
         * @param siteUrl The WordPress site root URL (e.g. "https://example.com").
         */
        fun create(siteUrl: String): HelpdeskPublicApi {
            val baseUrl = publicApiUrl(siteUrl)

            val clientBuilder = OkHttpClient.Builder()
            if (BuildConfig.DEBUG) {
                clientBuilder.addInterceptor(
                    HttpLoggingInterceptor().apply {
                        level = HttpLoggingInterceptor.Level.BASIC
                    }
                )
            }

            return Retrofit.Builder()
                .baseUrl(baseUrl)
                .client(clientBuilder.build())
                .addConverterFactory(GsonConverterFactory.create())
                .build()
                .create(HelpdeskPublicApi::class.java)
        }

        private fun publicApiUrl(siteUrl: String): HttpUrl {
            val normalized = siteUrl.trim().removeSuffix("/")
            return "$normalized/wp-json/helpdesk/v1/public/".toHttpUrl()
        }
    }
}
