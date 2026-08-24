package com.wphelpd.admin.data.repository

import android.util.Log
import com.wphelpd.admin.core.network.ApiClientFactory
import com.wphelpd.admin.core.network.AuthConfig
import com.wphelpd.admin.core.network.NetworkResult
import com.wphelpd.admin.data.api.HelpdeskAdminApi
import com.wphelpd.admin.data.api.dto.DeviceTokenRequestDto
import com.wphelpd.admin.data.api.dto.NoteRequestDto
import com.wphelpd.admin.data.api.dto.ReplyRequestDto
import com.wphelpd.admin.data.api.dto.StatusUpdateRequestDto
import com.wphelpd.admin.domain.model.AppearanceColors
import com.wphelpd.admin.domain.model.CurrentUser
import com.wphelpd.admin.domain.model.TicketDetail
import com.wphelpd.admin.domain.model.TicketThreadEntry
import com.wphelpd.admin.domain.model.TicketPage
import java.io.IOException
import kotlinx.coroutines.CancellationException
import retrofit2.HttpException

class HelpdeskRepository(
    private val apiProvider: (AuthConfig) -> HelpdeskAdminApi = ApiClientFactory::create
) {
    companion object {
        private const val TAG = "HelpdeskRepository"
        val statusOptions: List<String> = listOf(
            "new",
            "pending_agent_reply",
            "pending_client_reply",
            "resolved",
            "closed"
        )
        private val knownStatuses: Set<String> = statusOptions.toSet() + setOf(
            "open",
            "pending",
            "triaged",
            "in_progress",
            "waiting_customer"
        )
    }

    suspend fun authCheck(config: AuthConfig): NetworkResult<AuthCheckResult> = execute {
        val response = apiProvider(config).authCheck()
        AuthCheckResult(
            user = response.requireUser(),
            appearanceColors = response.toAppearanceColors()
        )
    }

    suspend fun fetchTickets(
        config: AuthConfig,
        page: Int = 1,
        perPage: Int = 20,
        status: String? = null,
        search: String? = null
    ): NetworkResult<TicketPage> = execute {
        apiProvider(config).getTickets(
            page = page,
            perPage = perPage,
            status = status,
            search = search
        ).toTicketPage()
    }

    suspend fun fetchTicketDetail(
        config: AuthConfig,
        ticketId: Int
    ): NetworkResult<TicketDetail> = execute {
        val api = apiProvider(config)
        val rawResponse = api.getTicket(ticketId)
        Log.d(TAG, "fetchTicketDetail: getTicket($ticketId) success=${rawResponse.success} hasData=${rawResponse.data != null} topLevelId=${rawResponse.id}")
        val detail = rawResponse.toTicketDetail()
        Log.d(TAG, "fetchTicketDetail: mapped detail ticketNo=${detail.ticket.ticketNo} threadSize=${detail.thread.size}")
        if (detail.thread.isNotEmpty()) {
            detail
        } else {
            Log.d(TAG, "fetchTicketDetail: thread is empty — triggering fallback getTicketMessages($ticketId)")
            val thread = try {
                val rawMessages = api.getTicketMessages(ticketId)
                Log.d(TAG, "fetchTicketDetail: getTicketMessages($ticketId) success=${rawMessages.success} hasData=${rawMessages.data != null} itemsSize=${rawMessages.items?.size} messagesSize=${rawMessages.messages?.size}")
                rawMessages.toThread()
            } catch (throwable: CancellationException) {
                throw throwable
            } catch (_: Throwable) {
                return@execute detail
            }
            Log.d(TAG, "fetchTicketDetail: fallback thread size=${thread.size}")
            detail.copy(thread = thread)
        }
    }

    suspend fun replyToTicket(
        config: AuthConfig,
        ticketId: Int,
        message: String
    ): NetworkResult<TicketThreadEntry?> = execute {
        apiProvider(config)
            .replyToTicket(ticketId, ReplyRequestDto(message = message))
            .requireResult()
            .toThreadEntryOrNull()
    }

    suspend fun updateTicketStatus(
        config: AuthConfig,
        ticketId: Int,
        status: String
    ): NetworkResult<String> = execute {
        require(status in knownStatuses) {
            "Status must be one of: ${knownStatuses.sorted().joinToString()}."
        }
        apiProvider(config)
            .updateTicketStatus(ticketId, StatusUpdateRequestDto(status = status))
            .requireResult()
            .status
            ?: status
    }

    suspend fun addInternalNote(
        config: AuthConfig,
        ticketId: Int,
        note: String
    ): NetworkResult<TicketThreadEntry?> = execute {
        apiProvider(config)
            .addTicketNote(ticketId, NoteRequestDto(note = note))
            .requireResult()
            .toThreadEntryOrNull()
    }

    suspend fun registerDeviceToken(
        config: AuthConfig,
        deviceToken: String,
        appVersion: String,
        platform: String = "android"
    ): NetworkResult<Boolean> = execute {
        apiProvider(config)
            .registerDeviceToken(
                DeviceTokenRequestDto(
                    deviceToken = deviceToken,
                    platform = platform,
                    appVersion = appVersion
                )
            )
            .registered
    }

    suspend fun unregisterDeviceToken(
        config: AuthConfig,
        deviceToken: String,
        appVersion: String,
        platform: String = "android"
    ): NetworkResult<Boolean> = execute {
        apiProvider(config)
            .unregisterDeviceToken(
                DeviceTokenRequestDto(
                    deviceToken = deviceToken,
                    platform = platform,
                    appVersion = appVersion
                )
            )
            .registered
    }

    private suspend fun <T> execute(block: suspend () -> T): NetworkResult<T> = try {
        NetworkResult.Success(block())
    } catch (throwable: CancellationException) {
        throw throwable
    } catch (throwable: Throwable) {
        NetworkResult.Failure(
            message = throwable.toReadableMessage(),
            throwable = throwable
        )
    }
}

private fun Throwable.toReadableMessage(): String = when (this) {
    is IllegalArgumentException -> message ?: "Invalid WP HelpD configuration."
    is HttpException -> "HTTP ${code()} while contacting WP HelpD."
    is IOException -> "Unable to reach the WP HelpD server."
    else -> message ?: "Unexpected WP HelpD error."
}

data class AuthCheckResult(
    val user: CurrentUser,
    val appearanceColors: AppearanceColors
)
