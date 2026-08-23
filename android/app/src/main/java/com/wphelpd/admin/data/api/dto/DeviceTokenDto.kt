package com.wphelpd.admin.data.api.dto

import com.google.gson.annotations.SerializedName

data class DeviceTokenRequestDto(
    @SerializedName("device_token") val deviceToken: String,
    @SerializedName("platform") val platform: String = "android",
    @SerializedName("app_version") val appVersion: String
)

data class DeviceTokenResponseDto(
    @SerializedName("registered") val registered: Boolean = false
)
