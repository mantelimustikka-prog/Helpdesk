package com.wphelpd.admin.feature.notifications

import com.google.common.truth.Truth.assertThat
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.test.runTest
import org.junit.Test

class NotificationPollerTest {

    @Test
    fun notificationEventBus_postEmitsEvent() = runTest {
        val event = NotificationEvent(newTicketCount = 2, newReplyCount = 3)

        // We need to set up a collector before posting.
        val collectedEvents = mutableListOf<NotificationEvent>()

        // Launch a coroutine that collects from the bus.
        val job = kotlinx.coroutines.launch {
            NotificationEventBus.events.collect { collectedEvents.add(it) }
        }

        NotificationEventBus.post(event)

        // Give the coroutine time to collect.
        kotlinx.coroutines.delay(50)
        job.cancel()

        assertThat(collectedEvents).contains(event)
    }

    @Test
    fun notificationEventBus_postEmitsMultipleEvents() = runTest {
        val event1 = NotificationEvent(newTicketCount = 1, newReplyCount = 0)
        val event2 = NotificationEvent(newTicketCount = 0, newReplyCount = 5)

        val collectedEvents = mutableListOf<NotificationEvent>()
        val job = kotlinx.coroutines.launch {
            NotificationEventBus.events.collect { collectedEvents.add(it) }
        }

        NotificationEventBus.post(event1)
        NotificationEventBus.post(event2)

        kotlinx.coroutines.delay(50)
        job.cancel()

        assertThat(collectedEvents).containsAtLeast(event1, event2)
    }

    @Test
    fun notificationEvent_dataClass_equalityWorks() {
        val a = NotificationEvent(newTicketCount = 3, newReplyCount = 1)
        val b = NotificationEvent(newTicketCount = 3, newReplyCount = 1)
        val c = NotificationEvent(newTicketCount = 0, newReplyCount = 0)

        assertThat(a).isEqualTo(b)
        assertThat(a).isNotEqualTo(c)
    }

    @Test
    fun notificationEvent_hasExpectedCounts() {
        val event = NotificationEvent(newTicketCount = 5, newReplyCount = 2)

        assertThat(event.newTicketCount).isEqualTo(5)
        assertThat(event.newReplyCount).isEqualTo(2)
    }

    @Test
    fun notificationEvent_zeroCountsAreValid() {
        val event = NotificationEvent(newTicketCount = 0, newReplyCount = 0)

        assertThat(event.newTicketCount).isEqualTo(0)
        assertThat(event.newReplyCount).isEqualTo(0)
    }

    @Test
    fun notificationEventBus_firstEmittedEventIsReceived() = runTest {
        val expected = NotificationEvent(newTicketCount = 1, newReplyCount = 1)

        val deferredFirst = kotlinx.coroutines.async {
            NotificationEventBus.events.first()
        }

        // Give the async coroutine time to subscribe before posting.
        kotlinx.coroutines.delay(10)
        NotificationEventBus.post(expected)

        val received = deferredFirst.await()
        assertThat(received).isEqualTo(expected)
    }

    @Test
    fun notificationEvent_zeroCountsAreNonNegative() {
        // Sanity-check that a zero-count event (the boundary case the poller
        // must never cross) satisfies the non-negative contract.
        val event = NotificationEvent(newTicketCount = 0, newReplyCount = 0)
        assertThat(event.newTicketCount).isAtLeast(0)
        assertThat(event.newReplyCount).isAtLeast(0)
    }
}
