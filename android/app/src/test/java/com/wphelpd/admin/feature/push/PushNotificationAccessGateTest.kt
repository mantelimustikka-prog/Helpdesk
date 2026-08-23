package com.wphelpd.admin.feature.push

import com.google.common.truth.Truth.assertThat
import org.junit.Test

class PushNotificationAccessGateTest {
    @Test
    fun resolveTicketIdToOpen_allowsWhenSessionIsReady() {
        val result = PushNotificationAccessGate.resolveTicketIdToOpen(
            pendingTicketId = 55,
            isUnlocked = true,
            isBootstrapping = false,
            requiresSetup = false,
            hasCurrentUser = true
        )

        assertThat(result).isEqualTo(55)
    }

    @Test
    fun resolveTicketIdToOpen_blocksWhileLockedOrUnauthorized() {
        val locked = PushNotificationAccessGate.resolveTicketIdToOpen(
            pendingTicketId = 55,
            isUnlocked = false,
            isBootstrapping = false,
            requiresSetup = false,
            hasCurrentUser = true
        )
        val needsSetup = PushNotificationAccessGate.resolveTicketIdToOpen(
            pendingTicketId = 55,
            isUnlocked = true,
            isBootstrapping = false,
            requiresSetup = true,
            hasCurrentUser = false
        )

        assertThat(locked).isNull()
        assertThat(needsSetup).isNull()
    }
}
