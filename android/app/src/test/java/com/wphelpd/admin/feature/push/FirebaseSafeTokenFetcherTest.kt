package com.wphelpd.admin.feature.push

import org.junit.Test
import com.google.common.truth.Truth.assertThat

class FirebaseSafeTokenFetcherTest {

    /**
     * Verifies that [FirebaseSafeTokenFetcher.fetchToken] does not throw even when
     * Firebase is not configured (i.e., when [com.google.firebase.messaging.FirebaseMessaging.getInstance]
     * throws an [IllegalStateException] because no default FirebaseApp exists).
     *
     * In a pure JVM unit-test context, Firebase is never initialised, so `getInstance()` will
     * always throw. This test confirms the fail-soft behaviour holds in that worst-case scenario.
     */
    @Test
    fun `fetchToken does not throw when Firebase is not configured`() {
        var callbackInvoked = false

        // Must not throw even though Firebase has no default app in this JVM process.
        FirebaseSafeTokenFetcher.fetchToken { callbackInvoked = true }

        // The callback must NOT be invoked — there is no token when Firebase is unavailable.
        assertThat(callbackInvoked).isFalse()
    }

    @Test
    fun `fetchToken does not throw when called multiple times without Firebase`() {
        repeat(3) {
            FirebaseSafeTokenFetcher.fetchToken { /* no-op */ }
        }
        // Reaching here means no exception was thrown on any call.
    }
}
