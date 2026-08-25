package com.wphelpd.admin.data.api.dto

import com.google.gson.annotations.SerializedName

data class RequestResetCodeRequestDto(
    @SerializedName("email") val email: String
)

data class RequestResetCodeResponseDto(
    @SerializedName("success") val success: Boolean,
    @SerializedName("message") val message: String? = null,
    @SerializedName("error") val error: Map<String, String>? = null
)

data class VerifyResetCodeRequestDto(
    @SerializedName("email") val email: String,
    @SerializedName("code") val code: String
)

data class VerifyResetCodeResponseDto(
    @SerializedName("success") val success: Boolean,
    @SerializedName("reset_token") val resetToken: String? = null,
    @SerializedName("error") val error: Map<String, String>? = null
)

data class ResetPasswordRequestDto(
    @SerializedName("reset_token") val resetToken: String,
    @SerializedName("email") val email: String,
    @SerializedName("new_password") val newPassword: String
)

data class ResetPasswordResponseDto(
    @SerializedName("success") val success: Boolean,
    @SerializedName("message") val message: String? = null,
    @SerializedName("error") val error: Map<String, String>? = null
)
