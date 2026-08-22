package com.wphelpd.admin.data.repository

import com.google.common.truth.Truth.assertThat
import com.wphelpd.admin.core.network.AuthConfig
import com.wphelpd.admin.core.network.NetworkResult
import com.wphelpd.admin.data.api.HelpdeskAdminApi
import com.wphelpd.admin.data.api.dto.AuthCheckResponseDto
import com.wphelpd.admin.data.api.dto.PaginationDto
import com.wphelpd.admin.data.api.dto.TicketDto
import com.wphelpd.admin.data.api.dto.TicketListResponseDto
import com.wphelpd.admin.data.api.dto.UserDto
import kotlinx.coroutines.test.runTest
import org.junit.Test

class HelpdeskRepositoryTest {
    private val config = AuthConfig(
        siteUrl = "https://example.test",
        username = "agent",
        applicationPassword = "app-password"
    )

    @Test
    fun authCheck_mapsCurrentUserFromContractResponse() = runTest {
        val repository = HelpdeskRepository {
            FakeHelpdeskAdminApi(
                authResponse = AuthCheckResponseDto(
                    success = true,
                    user = UserDto(
                        id = 7,
                        name = "Agent Smith",
                        email = "agent@example.test",
                        roles = listOf("administrator")
                    )
                )
            )
        }

        val result = repository.authCheck(config)

        assertThat(result).isInstanceOf(NetworkResult.Success::class.java)
        val user = (result as NetworkResult.Success).value
        assertThat(user.name).isEqualTo("Agent Smith")
        assertThat(user.roles).containsExactly("administrator")
    }

    @Test
    fun fetchTickets_acceptsLegacyItemsPayloadForCurrentPluginScaffold() = runTest {
        val repository = HelpdeskRepository {
            FakeHelpdeskAdminApi(
                ticketResponse = TicketListResponseDto(
                    items = listOf(
                        TicketDto(
                            id = 101,
                            ticketNo = "HD-000101",
                            subject = "Login issue",
                            status = "open",
                            priority = "normal",
                            customerName = "Jane Smith",
                            customerEmail = "jane@example.test",
                            createdAt = "2026-08-22T10:15:00Z",
                            updatedAt = "2026-08-22T11:00:00Z",
                            messageCount = 3,
                            lastMessageExcerpt = "I still cannot sign in..."
                        )
                    ),
                    page = 1,
                    perPage = 20
                )
            )
        }

        val result = repository.fetchTickets(config)

        assertThat(result).isInstanceOf(NetworkResult.Success::class.java)
        val ticketPage = (result as NetworkResult.Success).value
        assertThat(ticketPage.tickets).hasSize(1)
        assertThat(ticketPage.tickets.single().ticketNo).isEqualTo("HD-000101")
        assertThat(ticketPage.pagination?.page).isEqualTo(1)
        assertThat(ticketPage.pagination?.perPage).isEqualTo(20)
    }

    @Test
    fun fetchTickets_mapsContractPaginationPayload() = runTest {
        val repository = HelpdeskRepository {
            FakeHelpdeskAdminApi(
                ticketResponse = TicketListResponseDto(
                    success = true,
                    data = listOf(
                        TicketDto(
                            id = 202,
                            ticketNo = "HD-000202",
                            subject = "Order update",
                            status = "pending"
                        )
                    ),
                    pagination = PaginationDto(
                        page = 2,
                        perPage = 10,
                        total = 12,
                        totalPages = 2
                    )
                )
            )
        }

        val result = repository.fetchTickets(config, page = 2, perPage = 10)

        assertThat(result).isInstanceOf(NetworkResult.Success::class.java)
        val ticketPage = (result as NetworkResult.Success).value
        assertThat(ticketPage.pagination?.totalPages).isEqualTo(2)
        assertThat(ticketPage.tickets.single().status).isEqualTo("pending")
    }
}

private class FakeHelpdeskAdminApi(
    private val authResponse: AuthCheckResponseDto = AuthCheckResponseDto(
        success = true,
        user = UserDto(id = 1, name = "Agent", email = "agent@example.test")
    ),
    private val ticketResponse: TicketListResponseDto = TicketListResponseDto(
        success = true,
        data = emptyList(),
        pagination = PaginationDto(page = 1, perPage = 20, total = 0, totalPages = 0)
    )
) : HelpdeskAdminApi {
    override suspend fun authCheck(): AuthCheckResponseDto = authResponse

    override suspend fun getTickets(
        page: Int,
        perPage: Int,
        status: String?,
        search: String?
    ): TicketListResponseDto = ticketResponse
}
