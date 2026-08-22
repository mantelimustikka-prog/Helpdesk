package com.wphelpd.admin.data.repository

import com.wphelpd.admin.core.network.ApiClientFactory
import com.wphelpd.admin.core.network.AuthConfig
import com.wphelpd.admin.core.network.NetworkResult
import com.wphelpd.admin.data.api.HelpdeskAdminApi
import com.wphelpd.admin.data.api.dto.NoteRequestDto
import com.wphelpd.admin.data.api.dto.ReplyRequestDto
import com.wphelpd.admin.data.api.dto.StatusUpdateRequestDto
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
    suspend fun authCheck(config: AuthConfig): NetworkResult<CurrentUser> = execute {
        apiProvider(config).authCheck().requireUser()
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
        val detail = api.getTicket(ticketId).toTicketDetail()
        if (detail.thread.isNotEmpty()) {
            detail
        } else {
            val thread = try {
                api.getTicketMessages(ticketId).toThread()
            } catch (throwable: CancellationException) {
                throw throwable
            } catch (_: Throwable) {
                emptyList()
            }
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
