package com.wphelpd.admin.data.repository

import com.wphelpd.admin.core.network.ApiClientFactory
import com.wphelpd.admin.core.network.AuthConfig
import com.wphelpd.admin.core.network.NetworkResult
import com.wphelpd.admin.data.api.HelpdeskAdminApi
import com.wphelpd.admin.domain.model.CurrentUser
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
