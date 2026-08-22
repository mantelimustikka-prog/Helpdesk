package com.wphelpd.admin.data.api.dto

import com.google.gson.annotations.SerializedName
import com.wphelpd.admin.domain.model.CurrentUser

data class AuthCheckResponseDto(
    @SerializedName("success") val success: Boolean? = null,
    @SerializedName("user") val user: UserDto? = null
) {
    fun requireUser(): CurrentUser {
        check(success != false) { "Authentication check failed." }
        val currentUser = requireNotNull(user) { "Auth check response did not include a user." }
        return currentUser.toModel()
    }
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
