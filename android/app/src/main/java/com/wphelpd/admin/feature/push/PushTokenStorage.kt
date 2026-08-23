package com.wphelpd.admin.feature.push

interface PushTokenStorage {
    fun saveCurrentToken(token: String)
    fun currentToken(): String?
    fun isAlreadyRegistered(token: String, siteUrl: String, username: String): Boolean
    fun markRegistered(token: String, siteUrl: String, username: String)
    fun clearRegisteredState()
    fun wasNotificationHandled(notificationId: String): Boolean
    fun markNotificationHandled(notificationId: String)
    fun clearHandledNotifications()
}
