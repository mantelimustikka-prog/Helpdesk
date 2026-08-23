package com.wphelpd.admin.feature.push

import com.google.common.truth.Truth.assertThat
import com.wphelpd.admin.core.network.AuthConfig
import com.wphelpd.admin.data.api.HelpdeskAdminApi
import com.wphelpd.admin.data.api.dto.AuthCheckResponseDto
import com.wphelpd.admin.data.api.dto.DeviceTokenRequestDto
import com.wphelpd.admin.data.api.dto.DeviceTokenResponseDto
import com.wphelpd.admin.data.api.dto.NoteRequestDto
import com.wphelpd.admin.data.api.dto.NoteResponseDto
import com.wphelpd.admin.data.api.dto.ReplyRequestDto
import com.wphelpd.admin.data.api.dto.ReplyResponseDto
import com.wphelpd.admin.data.api.dto.StatusUpdateRequestDto
import com.wphelpd.admin.data.api.dto.StatusUpdateResponseDto
import com.wphelpd.admin.data.api.dto.TicketDetailResponseDto
import com.wphelpd.admin.data.api.dto.TicketListResponseDto
import com.wphelpd.admin.data.api.dto.TicketMessagesResponseDto
import com.wphelpd.admin.data.api.dto.UserDto
import com.wphelpd.admin.data.repository.HelpdeskRepository
import kotlinx.coroutines.test.runTest
import org.junit.Test

class PushTokenSyncManagerTest {
    private val config = AuthConfig(
        siteUrl = "https://example.test",
        username = "agent",
        applicationPassword = "app-password"
    )

    @Test
    fun unregisterIfNeeded_clearsRegisteredStateOnSuccess() = runTest {
        val storage = FakePushTokenStorage(currentToken = "fcm-token-abc")
        storage.markRegistered("fcm-token-abc", config.siteUrl, config.username)
        val manager = PushTokenSyncManager(
            repository = HelpdeskRepository { FakeHelpdeskApi(succeedUnregister = true) },
            stateStore = storage
        )

        val result = manager.unregisterIfNeeded(config)

        assertThat(result).isTrue()
        assertThat(storage.isAlreadyRegistered("fcm-token-abc", config.siteUrl, config.username)).isFalse()
    }

    @Test
    fun unregisterIfNeeded_clearsRegisteredStateEvenOnServerFailure() = runTest {
        val storage = FakePushTokenStorage(currentToken = "fcm-token-abc")
        storage.markRegistered("fcm-token-abc", config.siteUrl, config.username)
        val manager = PushTokenSyncManager(
            repository = HelpdeskRepository { FakeHelpdeskApi(succeedUnregister = false) },
            stateStore = storage
        )

        val result = manager.unregisterIfNeeded(config)

        assertThat(result).isFalse()
        assertThat(storage.isAlreadyRegistered("fcm-token-abc", config.siteUrl, config.username)).isFalse()
    }

    @Test
    fun unregisterIfNeeded_returnsFalseWhenNoTokenStored() = runTest {
        val storage = FakePushTokenStorage(currentToken = null)
        val manager = PushTokenSyncManager(
            repository = HelpdeskRepository { FakeHelpdeskApi(succeedUnregister = true) },
            stateStore = storage
        )

        val result = manager.unregisterIfNeeded(config)

        assertThat(result).isFalse()
    }

    @Test
    fun registerIfNeeded_skipsApiCallWhenAlreadyRegistered() = runTest {
        val storage = FakePushTokenStorage(currentToken = "fcm-token-abc")
        storage.markRegistered("fcm-token-abc", config.siteUrl, config.username)
        val api = FakeHelpdeskApi(succeedRegister = true)
        val manager = PushTokenSyncManager(
            repository = HelpdeskRepository { api },
            stateStore = storage
        )

        val result = manager.registerIfNeeded(config)

        assertThat(result).isTrue()
        assertThat(api.registerCallCount).isEqualTo(0)
    }

    @Test
    fun registerIfNeeded_callsApiAndMarksRegisteredOnSuccess() = runTest {
        val storage = FakePushTokenStorage(currentToken = "fcm-token-xyz")
        val api = FakeHelpdeskApi(succeedRegister = true)
        val manager = PushTokenSyncManager(
            repository = HelpdeskRepository { api },
            stateStore = storage
        )

        val result = manager.registerIfNeeded(config)

        assertThat(result).isTrue()
        assertThat(api.registerCallCount).isEqualTo(1)
        assertThat(storage.isAlreadyRegistered("fcm-token-xyz", config.siteUrl, config.username)).isTrue()
    }
}

private class FakePushTokenStorage(private val currentToken: String?) : PushTokenStorage {
    private var storedToken: String? = null
    private var registeredToken: String? = null
    private var registeredSiteUrl: String? = null
    private var registeredUsername: String? = null
    private val handledIds = mutableSetOf<String>()

    override fun saveCurrentToken(token: String) { storedToken = token }
    override fun currentToken(): String? = currentToken ?: storedToken

    override fun isAlreadyRegistered(token: String, siteUrl: String, username: String): Boolean =
        registeredToken == token && registeredSiteUrl == siteUrl && registeredUsername == username

    override fun markRegistered(token: String, siteUrl: String, username: String) {
        registeredToken = token
        registeredSiteUrl = siteUrl
        registeredUsername = username
    }

    override fun clearRegisteredState() {
        registeredToken = null
        registeredSiteUrl = null
        registeredUsername = null
    }

    override fun wasNotificationHandled(notificationId: String): Boolean = notificationId in handledIds
    override fun markNotificationHandled(notificationId: String) { handledIds += notificationId }
    override fun clearHandledNotifications() { handledIds.clear() }
}

private class FakeHelpdeskApi(
    private val succeedUnregister: Boolean = true,
    private val succeedRegister: Boolean = true
) : HelpdeskAdminApi {
    var registerCallCount = 0

    override suspend fun authCheck() = AuthCheckResponseDto(
        success = true,
        user = UserDto(id = 1, name = "Agent", email = "a@b.test")
    )

    override suspend fun getTickets(page: Int, perPage: Int, status: String?, search: String?) =
        TicketListResponseDto(success = true, data = emptyList())

    override suspend fun getTicket(id: Int) =
        TicketDetailResponseDto(id = id, ticketNo = "HD-$id", subject = "s", status = "open")

    override suspend fun getTicketMessages(id: Int) =
        TicketMessagesResponseDto(items = emptyList())

    override suspend fun replyToTicket(id: Int, request: ReplyRequestDto) =
        ReplyResponseDto(success = true)

    override suspend fun updateTicketStatus(id: Int, request: StatusUpdateRequestDto) =
        StatusUpdateResponseDto(success = true)

    override suspend fun addTicketNote(id: Int, request: NoteRequestDto) =
        NoteResponseDto(success = true)

    override suspend fun registerDeviceToken(request: DeviceTokenRequestDto): DeviceTokenResponseDto {
        registerCallCount++
        return if (succeedRegister) DeviceTokenResponseDto(registered = true)
        else throw RuntimeException("register failed")
    }

    override suspend fun unregisterDeviceToken(request: DeviceTokenRequestDto): DeviceTokenResponseDto {
        return if (succeedUnregister) DeviceTokenResponseDto(registered = false)
        else throw RuntimeException("unregister failed")
    }
}
