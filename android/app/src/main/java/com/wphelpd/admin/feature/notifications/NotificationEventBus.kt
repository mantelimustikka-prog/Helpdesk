package com.wphelpd.admin.feature.notifications

import kotlinx.coroutines.channels.BufferOverflow
import kotlinx.coroutines.flow.MutableSharedFlow
import kotlinx.coroutines.flow.SharedFlow
import kotlinx.coroutines.flow.asSharedFlow

data class NotificationEvent(
    val newTicketCount: Int,
    val newReplyCount: Int
)

/**
 * Application-level event bus for in-app notification events.
 *
 * The [NotificationPoller] worker posts events here; the UI layer collects
 * them to show the [NotificationDialog].
 */
object NotificationEventBus {
    private val _events = MutableSharedFlow<NotificationEvent>(
        replay = 100,
        extraBufferCapacity = 0,
        onBufferOverflow = BufferOverflow.DROP_OLDEST
    )
    val events: SharedFlow<NotificationEvent> = _events.asSharedFlow()

    /**
     * Emits a notification event. Safe to call from any coroutine context.
     */
    fun post(event: NotificationEvent) {
        _events.tryEmit(event)
    }
}
