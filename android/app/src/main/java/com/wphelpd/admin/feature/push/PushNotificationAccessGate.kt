package com.wphelpd.admin.feature.push

object PushNotificationAccessGate {
    fun resolveTicketIdToOpen(
        pendingTicketId: Int?,
        isUnlocked: Boolean,
        isBootstrapping: Boolean,
        requiresSetup: Boolean,
        hasCurrentUser: Boolean
    ): Int? {
        if (pendingTicketId == null || pendingTicketId <= 0) return null
        if (!isUnlocked) return null
        if (isBootstrapping || requiresSetup || !hasCurrentUser) return null
        return pendingTicketId
    }
}
