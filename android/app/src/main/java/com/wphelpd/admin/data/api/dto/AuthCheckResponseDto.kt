package com.wphelpd.admin.data.api.dto

import com.google.gson.annotations.SerializedName
import com.wphelpd.admin.domain.model.AppearanceColors
import com.wphelpd.admin.domain.model.CurrentUser

data class AuthCheckResponseDto(
    @SerializedName("success") val success: Boolean? = null,
    @SerializedName("user") val user: UserDto? = null,
    @SerializedName("appearance") val appearance: AppearanceColorsDto? = null
) {
    fun requireUser(): CurrentUser {
        check(success != false) { "Authentication check failed." }
        val currentUser = requireNotNull(user) { "Auth check response did not include a user." }
        return currentUser.toModel()
    }

    fun toAppearanceColors(): AppearanceColors = appearance?.toModel() ?: AppearanceColors.Empty
}

data class UserDto(
    @SerializedName("id") val id: Int,
    @SerializedName("name") val name: String,
    @SerializedName("email") val email: String,
    @SerializedName("roles") val roles: List<String> = emptyList()
) {
    fun toModel(): CurrentUser = CurrentUser(
        id = id,
        name = name,
        email = email,
        roles = roles
    )
}

data class AppearanceColorsDto(
    @SerializedName("admin_reply_color") val adminReplyColor: String? = null,
    @SerializedName("client_reply_color") val clientReplyColor: String? = null,
    @SerializedName("status_new_color") val statusNewColor: String? = null,
    @SerializedName("status_pending_agent_color") val statusPendingAgentColor: String? = null,
    @SerializedName("status_pending_client_color") val statusPendingClientColor: String? = null,
    @SerializedName("status_resolved_color") val statusResolvedColor: String? = null,
    @SerializedName("status_closed_color") val statusClosedColor: String? = null
) {
    fun toModel(): AppearanceColors = AppearanceColors(
        adminReplyColor = adminReplyColor.orEmpty(),
        clientReplyColor = clientReplyColor.orEmpty(),
        statusNewColor = statusNewColor.orEmpty(),
        statusPendingAgentColor = statusPendingAgentColor.orEmpty(),
        statusPendingClientColor = statusPendingClientColor.orEmpty(),
        statusResolvedColor = statusResolvedColor.orEmpty(),
        statusClosedColor = statusClosedColor.orEmpty()
    )
}

