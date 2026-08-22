package com.wphelpd.admin.core.network

data class AuthConfig(
    val siteUrl: String,
    val username: String,
    val applicationPassword: String,
    val wpNonce: String = ""
)
