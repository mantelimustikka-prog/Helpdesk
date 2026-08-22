package com.wphelpd.admin.feature.tickets

import com.google.common.truth.Truth.assertThat
import com.wphelpd.admin.data.api.HelpdeskAdminApi
import com.wphelpd.admin.data.api.dto.AuthCheckResponseDto
import com.wphelpd.admin.data.api.dto.NoteRequestDto
import com.wphelpd.admin.data.api.dto.NoteResponseDto
import com.wphelpd.admin.data.api.dto.PaginationDto
import com.wphelpd.admin.data.api.dto.ReplyRequestDto
import com.wphelpd.admin.data.api.dto.ReplyResponseDto
import com.wphelpd.admin.data.api.dto.StatusUpdateRequestDto
import com.wphelpd.admin.data.api.dto.StatusUpdateResponseDto
import com.wphelpd.admin.data.api.dto.TicketCustomerDto
import com.wphelpd.admin.data.api.dto.TicketDetailDto
import com.wphelpd.admin.data.api.dto.TicketDetailResponseDto
import com.wphelpd.admin.data.api.dto.TicketDto
import com.wphelpd.admin.data.api.dto.TicketListResponseDto
import com.wphelpd.admin.data.api.dto.TicketMessagesResponseDto
import com.wphelpd.admin.data.api.dto.TicketThreadEntryDto
import com.wphelpd.admin.data.api.dto.UserDto
import com.wphelpd.admin.data.repository.HelpdeskRepository
import java.io.IOException
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.ExperimentalCoroutinesApi
import kotlinx.coroutines.test.StandardTestDispatcher
import kotlinx.coroutines.test.TestDispatcher
import kotlinx.coroutines.test.advanceUntilIdle
import kotlinx.coroutines.test.resetMain
import kotlinx.coroutines.test.runTest
import kotlinx.coroutines.test.setMain
import org.junit.Rule
import org.junit.Test
import org.junit.rules.TestWatcher
import org.junit.runner.Description

@OptIn(ExperimentalCoroutinesApi::class)
class TicketsViewModelTest {
    @get:Rule
    val mainDispatcherRule = MainDispatcherRule()

    @Test
    fun selectTicket_loadsTicketDetailIntoUiState() = runTest {
        val viewModel = TicketsViewModel(
            repository = HelpdeskRepository {
                FakeHelpdeskAdminApi(
                    ticketDetailResponse = TicketDetailResponseDto(
                        success = true,
                        data = TicketDetailDto(
                            id = 101,
                            ticketNo = "HD-000101",
                            subject = "Login issue",
                            status = "open",
                            priority = "normal",
                            customer = TicketCustomerDto(name = "Jane Smith", email = "jane@example.test"),
                            messages = listOf(
                                TicketThreadEntryDto(
                                    id = 9001,
                                    authorType = "customer",
                                    authorName = "Jane Smith",
                                    body = "I cannot sign in.",
                                    createdAt = "2026-08-22T10:15:00Z"
                                )
                            )
                        )
                    )
                )
            }
        )

        viewModel.selectTicket(101)
        advanceUntilIdle()

        val state = viewModel.uiState.value
        assertThat(state.selectedTicketId).isEqualTo(101)
        assertThat(state.ticketDetail?.ticket?.subject).isEqualTo("Login issue")
        assertThat(state.ticketDetail?.thread).hasSize(1)
        assertThat(state.detailErrorMessage).isNull()
        assertThat(state.isDetailLoading).isFalse()
    }

    @Test
    fun selectTicket_setsReadableErrorWhenDetailFetchFails() = runTest {
        val viewModel = TicketsViewModel(
            repository = HelpdeskRepository {
                FakeHelpdeskAdminApi(
                    detailThrowable = IOException("boom")
                )
            }
        )

        viewModel.selectTicket(101)
        advanceUntilIdle()

        val state = viewModel.uiState.value
        assertThat(state.selectedTicketId).isEqualTo(101)
        assertThat(state.ticketDetail).isNull()
        assertThat(state.detailErrorMessage).isEqualTo("Unable to reach the WP HelpD server.")
        assertThat(state.isDetailLoading).isFalse()
    }
}

@OptIn(ExperimentalCoroutinesApi::class)
private class MainDispatcherRule(
    private val dispatcher: TestDispatcher = StandardTestDispatcher()
) : TestWatcher() {
    override fun starting(description: Description) {
        Dispatchers.setMain(dispatcher)
    }

    override fun finished(description: Description) {
        Dispatchers.resetMain()
    }
}

private class FakeHelpdeskAdminApi(
    private val ticketDetailResponse: TicketDetailResponseDto = TicketDetailResponseDto(
        success = true,
        data = TicketDetailDto(
            id = 101,
            ticketNo = "HD-000101",
            subject = "Login issue",
            status = "open"
        )
    ),
    private val detailThrowable: Throwable? = null
) : HelpdeskAdminApi {
    override suspend fun authCheck(): AuthCheckResponseDto = AuthCheckResponseDto(
        success = true,
        user = UserDto(id = 1, name = "Agent", email = "agent@example.test")
    )

    override suspend fun getTickets(
        page: Int,
        perPage: Int,
        status: String?,
        search: String?
    ): TicketListResponseDto = TicketListResponseDto(
        success = true,
        data = listOf(TicketDto(id = 101, ticketNo = "HD-000101", subject = "Login issue", status = "open")),
        pagination = PaginationDto(page = 1, perPage = 20, total = 1, totalPages = 1)
    )

    override suspend fun getTicket(id: Int): TicketDetailResponseDto {
        detailThrowable?.let { throw it }
        return ticketDetailResponse
    }

    override suspend fun getTicketMessages(id: Int): TicketMessagesResponseDto = TicketMessagesResponseDto(items = emptyList())

    override suspend fun replyToTicket(id: Int, request: ReplyRequestDto): ReplyResponseDto = ReplyResponseDto(success = true)

    override suspend fun updateTicketStatus(id: Int, request: StatusUpdateRequestDto): StatusUpdateResponseDto =
        StatusUpdateResponseDto(success = true)

    override suspend fun addTicketNote(id: Int, request: NoteRequestDto): NoteResponseDto = NoteResponseDto(success = true)
}
