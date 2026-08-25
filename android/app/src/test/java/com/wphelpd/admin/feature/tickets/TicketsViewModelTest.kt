package com.wphelpd.admin.feature.tickets

import com.google.common.truth.Truth.assertThat
import com.google.gson.JsonParser
import com.wphelpd.admin.core.config.ServerConfigRepository
import com.wphelpd.admin.core.network.AuthConfig
import com.wphelpd.admin.data.api.HelpdeskAdminApi
import com.wphelpd.admin.data.api.dto.AuthCheckResponseDto
import com.wphelpd.admin.data.api.dto.AppearanceColorsDto
import com.wphelpd.admin.data.api.dto.NotificationsSinceResponseDto
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
import com.wphelpd.admin.domain.model.AppearanceColors
import com.wphelpd.admin.domain.model.statusLabel
import com.wphelpd.admin.domain.model.Ticket as TicketModel
import java.io.IOException
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.ExperimentalCoroutinesApi
import kotlinx.coroutines.delay
import kotlinx.coroutines.test.StandardTestDispatcher
import kotlinx.coroutines.test.TestDispatcher
import kotlinx.coroutines.test.advanceUntilIdle
import kotlinx.coroutines.test.resetMain
import kotlinx.coroutines.test.runTest
import kotlinx.coroutines.test.setMain
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.ResponseBody.Companion.toResponseBody
import org.junit.Rule
import org.junit.Test
import org.junit.rules.TestWatcher
import org.junit.runner.Description
import retrofit2.HttpException
import retrofit2.Response

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
    fun selectTicket_loadsThreadFromMessagesEndpointWhenDetailResponseHasNoMessages() = runTest {
        // Simulate the production scenario: getTicket returns a flat response with no embedded
        // messages, so the repository falls back to the dedicated messages endpoint.
        val viewModel = TicketsViewModel(
            repository = HelpdeskRepository {
                FakeHelpdeskAdminApi(
                    ticketDetailResponse = TicketDetailResponseDto(
                        id = 202,
                        ticketNo = "HD-000202",
                        subject = "Password reset",
                        status = "open"
                    ),
                    ticketMessagesResponse = TicketMessagesResponseDto(
                        items = listOf(
                            TicketThreadEntryDto(
                                id = 7001,
                                authorType = "customer",
                                authorName = "Bob Smith",
                                body = "I need a password reset.",
                                createdAt = "2026-08-22T11:00:00Z"
                            ),
                            TicketThreadEntryDto(
                                id = 7002,
                                authorType = "agent",
                                authorName = "Support Agent",
                                body = "Reset link sent.",
                                createdAt = "2026-08-22T11:05:00Z"
                            )
                        )
                    )
                )
            }
        )

        viewModel.selectTicket(202)
        advanceUntilIdle()

        val state = viewModel.uiState.value
        assertThat(state.selectedTicketId).isEqualTo(202)
        assertThat(state.ticketDetail?.thread).hasSize(2)
        assertThat(state.ticketDetail?.thread?.first()?.body).isEqualTo("I need a password reset.")
        assertThat(state.ticketDetail?.thread?.last()?.authorType).isEqualTo("agent")
        assertThat(state.detailErrorMessage).isNull()
        assertThat(state.isDetailLoading).isFalse()
    }

    @Test
    fun selectTicket_loadsThreadFromFlatDetailResponseMessages() = runTest {
        val viewModel = TicketsViewModel(
            repository = HelpdeskRepository {
                FakeHelpdeskAdminApi(
                    ticketDetailResponse = TicketDetailResponseDto(
                        success = true,
                        id = 202,
                        ticketNo = "HD-000202",
                        subject = "Password reset",
                        status = "open",
                        messages = listOf(
                            TicketThreadEntryDto(
                                id = 7001,
                                authorType = "customer",
                                authorName = "Bob Smith",
                                body = "I need a password reset.",
                                createdAt = "2026-08-22T11:00:00Z"
                            )
                        )
                    )
                )
            }
        )

        viewModel.selectTicket(202)
        advanceUntilIdle()

        val state = viewModel.uiState.value
        assertThat(state.selectedTicketId).isEqualTo(202)
        assertThat(state.ticketDetail?.thread).hasSize(1)
        assertThat(state.ticketDetail?.thread?.single()?.body).isEqualTo("I need a password reset.")
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

    @Test
    fun viewModel_restoresSavedConfigIntoUiStateOnInit() {
        val saved = AuthConfig(
            siteUrl = "https://saved.example.com",
            username = "savedUser",
            applicationPassword = "savedPass",
            wpNonce = "savedNonce"
        )
        val fakeConfigRepo = FakeServerConfigRepository(initial = saved)

        val viewModel = TicketsViewModel(
            repository = HelpdeskRepository { FakeHelpdeskAdminApi() },
            serverConfigRepository = fakeConfigRepo
        )

        val state = viewModel.uiState.value
        assertThat(state.siteUrl).isEqualTo("https://saved.example.com")
        assertThat(state.username).isEqualTo("savedUser")
        assertThat(state.applicationPassword).isEqualTo("savedPass")
        assertThat(state.wpNonce).isEqualTo("savedNonce")
        assertThat(state.isBootstrapping).isTrue()
    }

    @Test
    fun connectAndLoadTickets_savesConfigWhenAuthSucceeds() = runTest {
        val fakeConfigRepo = FakeServerConfigRepository()

        val viewModel = TicketsViewModel(
            repository = HelpdeskRepository { FakeHelpdeskAdminApi() },
            serverConfigRepository = fakeConfigRepo
        )

        viewModel.updateSiteUrl("https://example.com")
        viewModel.updateUsername("admin")
        viewModel.updateApplicationPassword("secret")
        viewModel.connectAndLoadTickets()
        advanceUntilIdle()

        val saved = fakeConfigRepo.load()
        assertThat(saved).isNotNull()
        assertThat(saved!!.siteUrl).isEqualTo("https://example.com")
        assertThat(saved.username).isEqualTo("admin")
        assertThat(saved.applicationPassword).isEqualTo("secret")
        assertThat(viewModel.uiState.value.requiresSetup).isFalse()
    }

    @Test
    fun connectAndLoadTickets_doesNotSaveConfigWhenAuthFails() = runTest {
        val fakeConfigRepo = FakeServerConfigRepository()

        val viewModel = TicketsViewModel(
            repository = HelpdeskRepository { FakeHelpdeskAdminApi(authThrowable = IOException("auth failed")) },
            serverConfigRepository = fakeConfigRepo
        )

        viewModel.updateSiteUrl("https://example.com")
        viewModel.updateUsername("admin")
        viewModel.updateApplicationPassword("wrong")
        viewModel.connectAndLoadTickets()
        advanceUntilIdle()

        assertThat(fakeConfigRepo.load()).isNull()
    }

    @Test
    fun connectAndLoadTickets_storesAppearanceColorsFromAuthResponse() = runTest {
        val viewModel = TicketsViewModel(
            repository = HelpdeskRepository {
                FakeHelpdeskAdminApi(
                    authAppearance = AppearanceColorsDto(
                        adminReplyColor = "#1a73e8",
                        clientReplyColor = "#34a853",
                        statusNewColor = "#ea4335",
                        statusResolvedColor = "#fbbc04"
                    )
                )
            }
        )

        viewModel.updateSiteUrl("https://example.com")
        viewModel.updateUsername("admin")
        viewModel.updateApplicationPassword("secret")
        viewModel.connectAndLoadTickets()
        advanceUntilIdle()

        val colors = viewModel.uiState.value.appearanceColors
        assertThat(colors.adminReplyColor).isEqualTo("#1a73e8")
        assertThat(colors.clientReplyColor).isEqualTo("#34a853")
        assertThat(colors.statusNewColor).isEqualTo("#ea4335")
        assertThat(colors.statusResolvedColor).isEqualTo("#fbbc04")
    }

    @Test
    fun viewModel_handlesNoSavedConfigGracefully() {
        val fakeConfigRepo = FakeServerConfigRepository(initial = null)
        val viewModel = TicketsViewModel(
            repository = HelpdeskRepository { FakeHelpdeskAdminApi() },
            serverConfigRepository = fakeConfigRepo
        )
        val state = viewModel.uiState.value
        assertThat(state.siteUrl).isEmpty()
        assertThat(state.username).isEmpty()
        assertThat(state.applicationPassword).isEmpty()
        assertThat(state.isBootstrapping).isFalse()
        assertThat(state.requiresSetup).isTrue()
        assertThat(state.errorMessage).contains("Saved server configuration was not found")
    }

    @Test
    fun startupBootstrap_loadsTicketsAutomaticallyWhenSavedConfigIsValid() = runTest {
        val saved = AuthConfig(
            siteUrl = "https://saved.example.com",
            username = "savedUser",
            applicationPassword = "savedPass"
        )
        val viewModel = TicketsViewModel(
            repository = HelpdeskRepository { FakeHelpdeskAdminApi() },
            serverConfigRepository = FakeServerConfigRepository(initial = saved)
        )

        advanceUntilIdle()

        val state = viewModel.uiState.value
        assertThat(state.isBootstrapping).isFalse()
        assertThat(state.requiresSetup).isFalse()
        assertThat(state.currentUser?.name).isEqualTo("Agent")
        assertThat(state.tickets).isNotEmpty()
    }

    @Test
    fun startupBootstrap_routesToSetupWhenSavedCredentialsAreInvalid() = runTest {
        val saved = AuthConfig(
            siteUrl = "https://saved.example.com",
            username = "savedUser",
            applicationPassword = "wrongPass"
        )
        val viewModel = TicketsViewModel(
            repository = HelpdeskRepository {
                FakeHelpdeskAdminApi(authThrowable = unauthorizedHttpException())
            },
            serverConfigRepository = FakeServerConfigRepository(initial = saved)
        )

        advanceUntilIdle()

        val state = viewModel.uiState.value
        assertThat(state.isBootstrapping).isFalse()
        assertThat(state.requiresSetup).isTrue()
        assertThat(state.currentUser).isNull()
        assertThat(state.errorMessage).isEqualTo(
            "Saved credentials are invalid. Please update them and authenticate again."
        )
    }

    @Test
    fun clearSelectedTicket_resetsDetailState() = runTest {
        val viewModel = TicketsViewModel(
            repository = HelpdeskRepository {
                FakeHelpdeskAdminApi(
                    ticketDetailResponse = TicketDetailResponseDto(
                        success = true,
                        data = TicketDetailDto(
                            id = 101,
                            ticketNo = "HD-000101",
                            subject = "Login issue",
                            status = "open"
                        )
                    )
                )
            }
        )

        viewModel.selectTicket(101)
        advanceUntilIdle()
        assertThat(viewModel.uiState.value.selectedTicketId).isEqualTo(101)

        viewModel.clearSelectedTicket()

        val state = viewModel.uiState.value
        assertThat(state.selectedTicketId).isNull()
        assertThat(state.ticketDetail).isNull()
        assertThat(state.isDetailLoading).isFalse()
        assertThat(state.detailErrorMessage).isNull()
        assertThat(state.replyText).isEmpty()
        assertThat(state.noteText).isEmpty()
    }

    @Test
    fun selectTicket_ignoresStaleDetailResultAfterReSelectingSameTicket() = runTest {
        val api = FakeHelpdeskAdminApi(
            ticketDetailResponsesById = mapOf(
                101 to ticketDetailResponse(id = 101, subject = "Fresh ticket")
            ),
            ticketDetailResponseSequenceById = mapOf(
                101 to listOf(
                    ticketDetailResponse(id = 101, subject = "Stale ticket"),
                    ticketDetailResponse(id = 101, subject = "Fresh ticket")
                )
            ),
            ticketDetailDelaySequenceById = mapOf(
                101 to listOf(50L, 0L)
            )
        )
        val viewModel = TicketsViewModel(
            repository = HelpdeskRepository { api }
        )

        viewModel.selectTicket(101)
        runCurrent()
        viewModel.selectTicket(101)
        advanceUntilIdle()

        val state = viewModel.uiState.value
        assertThat(state.selectedTicketId).isEqualTo(101)
        assertThat(state.ticketDetail?.ticket?.subject).isEqualTo("Fresh ticket")
        assertThat(api.ticketDetailRequestsById[101]).isEqualTo(2)
    }

    @Test
    fun submitReply_clearsReplyTextAndRefreshesDetailOnSuccess() = runTest {
        val api = FakeHelpdeskAdminApi(
            ticketDetailResponse = TicketDetailResponseDto(
                success = true,
                data = TicketDetailDto(id = 101, ticketNo = "HD-000101", subject = "Login issue", status = "open")
            ),
            ticketMessagesResponse = TicketMessagesResponseDto(
                items = listOf(
                    TicketThreadEntryDto(
                        id = 9201,
                        authorType = "agent",
                        authorName = "Support Agent",
                        body = "This is my reply."
                    )
                )
            ),
            replyResponse = ReplyResponseDto(success = true)
        )
        val viewModel = TicketsViewModel(
            repository = HelpdeskRepository { api }
        )

        viewModel.selectTicket(101)
        advanceUntilIdle()

        viewModel.updateReplyText("This is my reply.")
        viewModel.submitReply()
        advanceUntilIdle()

        val state = viewModel.uiState.value
        assertThat(state.replyText).isEmpty()
        assertThat(state.isReplying).isFalse()
        assertThat(state.replyError).isNull()
        assertThat(state.selectedTicketId).isEqualTo(101)
        assertThat(state.ticketDetail).isNotNull()
        assertThat(state.ticketDetail?.thread).isNotEmpty()
        assertThat(state.ticketDetail?.thread?.single()?.body).isEqualTo("This is my reply.")
        assertThat(api.ticketDetailRequestsById[101]).isEqualTo(2)
        assertThat(api.ticketMessagesRequestsById[101]).isEqualTo(2)
    }

    @Test
    fun submitReply_setsReplyErrorOnFailure() = runTest {
        val viewModel = TicketsViewModel(
            repository = HelpdeskRepository {
                FakeHelpdeskAdminApi(
                    ticketDetailResponse = TicketDetailResponseDto(
                        success = true,
                        data = TicketDetailDto(id = 101, ticketNo = "HD-000101", subject = "Login issue", status = "open")
                    ),
                    replyThrowable = IOException("network error")
                )
            }
        )

        viewModel.selectTicket(101)
        advanceUntilIdle()

        viewModel.updateReplyText("My reply.")
        viewModel.submitReply()
        advanceUntilIdle()

        val state = viewModel.uiState.value
        assertThat(state.isReplying).isFalse()
        assertThat(state.replyError).isEqualTo("Unable to reach the WP HelpD server.")
    }

    @Test
    fun submitReply_setsErrorWhenMessageIsBlank() = runTest {
        val viewModel = TicketsViewModel(
            repository = HelpdeskRepository { FakeHelpdeskAdminApi() }
        )

        viewModel.selectTicket(101)
        advanceUntilIdle()

        viewModel.updateReplyText("   ")
        viewModel.submitReply()

        assertThat(viewModel.uiState.value.replyError).isEqualTo("Reply cannot be empty.")
    }

    @Test
    fun submitReply_setsLoadingStateAndResetsActionStateWhenSelectionChanges() = runTest {
        val api = FakeHelpdeskAdminApi(
            replyDelayMs = 50,
            replyThrowable = IOException("network error"),
            ticketDetailResponsesById = mapOf(
                101 to ticketDetailResponse(id = 101, subject = "Ticket 101"),
                202 to ticketDetailResponse(id = 202, subject = "Ticket 202")
            )
        )
        val viewModel = TicketsViewModel(
            repository = HelpdeskRepository { api }
        )

        viewModel.selectTicket(101)
        advanceUntilIdle()

        viewModel.updateReplyText("My reply.")
        viewModel.submitReply()

        assertThat(viewModel.uiState.value.isReplying).isTrue()

        viewModel.selectTicket(202)

        val switchingState = viewModel.uiState.value
        assertThat(switchingState.selectedTicketId).isEqualTo(202)
        assertThat(switchingState.replyText).isEmpty()
        assertThat(switchingState.isReplying).isFalse()
        assertThat(switchingState.replyError).isNull()
        assertThat(switchingState.isDetailLoading).isTrue()

        advanceUntilIdle()

        val finalState = viewModel.uiState.value
        assertThat(finalState.selectedTicketId).isEqualTo(202)
        assertThat(finalState.ticketDetail?.ticket?.id).isEqualTo(202)
        assertThat(finalState.replyError).isNull()
    }

    @Test
    fun detailActions_blockOtherMutationsWhileRequestIsInFlight() = runTest {
        val api = FakeHelpdeskAdminApi(replyDelayMs = 50)
        val viewModel = TicketsViewModel(
            repository = HelpdeskRepository { api }
        )

        viewModel.selectTicket(101)
        advanceUntilIdle()

        viewModel.updateReplyText("My reply.")
        viewModel.updateNoteText("My note.")
        viewModel.submitReply()
        viewModel.updateTicketStatus("resolved")
        viewModel.submitNote()
        advanceUntilIdle()

        assertThat(api.replyCallCount).isEqualTo(1)
        assertThat(api.statusUpdateCallCount).isEqualTo(0)
        assertThat(api.noteCallCount).isEqualTo(0)
    }

    @Test
    fun submitReply_blocksDuplicateSubmissionWhileInFlight() = runTest {
        val api = FakeHelpdeskAdminApi(replyDelayMs = 10)
        val viewModel = TicketsViewModel(
            repository = HelpdeskRepository { api }
        )

        viewModel.selectTicket(101)
        advanceUntilIdle()

        viewModel.updateReplyText("My reply.")
        viewModel.submitReply()
        viewModel.submitReply()
        advanceUntilIdle()

        assertThat(api.replyCallCount).isEqualTo(1)
    }

    @Test
    fun submitReply_doesNotRefreshStaleTicketWhenSelectionChanges() = runTest {
        val api = FakeHelpdeskAdminApi(
            replyDelayMs = 10,
            ticketDetailResponsesById = mapOf(
                101 to ticketDetailResponse(id = 101, subject = "Ticket 101"),
                202 to ticketDetailResponse(id = 202, subject = "Ticket 202")
            )
        )
        val viewModel = TicketsViewModel(
            repository = HelpdeskRepository { api }
        )

        viewModel.selectTicket(101)
        advanceUntilIdle()

        viewModel.updateReplyText("My reply.")
        viewModel.submitReply()
        viewModel.selectTicket(202)
        advanceUntilIdle()

        val state = viewModel.uiState.value
        assertThat(state.selectedTicketId).isEqualTo(202)
        assertThat(state.ticketDetail?.ticket?.id).isEqualTo(202)
        assertThat(api.ticketDetailRequestsById[101]).isEqualTo(1)
    }

    @Test
    fun submitReply_doesNotOverwriteCurrentDetailWhenSelectionChangesDuringRefresh() = runTest {
        val api = FakeHelpdeskAdminApi(
            replyDelayMs = 10,
            ticketDetailDelayMsById = mapOf(101 to 50, 202 to 0),
            ticketDetailResponsesById = mapOf(
                101 to ticketDetailResponse(id = 101, subject = "Ticket 101"),
                202 to ticketDetailResponse(id = 202, subject = "Ticket 202")
            )
        )
        val viewModel = TicketsViewModel(
            repository = HelpdeskRepository { api }
        )

        viewModel.selectTicket(101)
        advanceUntilIdle()

        viewModel.updateReplyText("My reply.")
        viewModel.submitReply()
        advanceTimeBy(10)
        runCurrent()
        viewModel.selectTicket(202)
        advanceUntilIdle()

        val state = viewModel.uiState.value
        assertThat(state.selectedTicketId).isEqualTo(202)
        assertThat(state.ticketDetail?.ticket?.id).isEqualTo(202)
        assertThat(api.ticketDetailRequestsById[101]).isEqualTo(2)
    }

    @Test
    fun updateTicketStatus_setsLoadingStateWhileRequestIsInFlight() = runTest {
        val api = FakeHelpdeskAdminApi(statusDelayMs = 50)
        val viewModel = TicketsViewModel(
            repository = HelpdeskRepository { api }
        )

        viewModel.selectTicket(101)
        advanceUntilIdle()

        viewModel.updateTicketStatus("resolved")

        val state = viewModel.uiState.value
        assertThat(state.isUpdatingStatus).isTrue()
        assertThat(state.statusUpdateError).isNull()

        advanceUntilIdle()
        assertThat(viewModel.uiState.value.isUpdatingStatus).isFalse()
    }

    @Test
    fun updateTicketStatus_refreshesDetailOnSuccess() = runTest {
        val viewModel = TicketsViewModel(
            repository = HelpdeskRepository {
                FakeHelpdeskAdminApi(
                    ticketDetailResponse = TicketDetailResponseDto(
                        success = true,
                        data = TicketDetailDto(id = 101, ticketNo = "HD-000101", subject = "Login issue", status = "open")
                    ),
                    statusResponse = StatusUpdateResponseDto(success = true, status = "resolved")
                )
            }
        )

        viewModel.selectTicket(101)
        advanceUntilIdle()

        viewModel.updateTicketStatus("resolved")
        advanceUntilIdle()

        val state = viewModel.uiState.value
        assertThat(state.isUpdatingStatus).isFalse()
        assertThat(state.statusUpdateError).isNull()
        assertThat(state.ticketDetail).isNotNull()
    }

    @Test
    fun updateTicketStatus_setsErrorOnFailure() = runTest {
        val viewModel = TicketsViewModel(
            repository = HelpdeskRepository {
                FakeHelpdeskAdminApi(
                    ticketDetailResponse = TicketDetailResponseDto(
                        success = true,
                        data = TicketDetailDto(id = 101, ticketNo = "HD-000101", subject = "Login issue", status = "open")
                    ),
                    statusThrowable = IOException("server down")
                )
            }
        )

        viewModel.selectTicket(101)
        advanceUntilIdle()

        viewModel.updateTicketStatus("resolved")
        advanceUntilIdle()

        val state = viewModel.uiState.value
        assertThat(state.isUpdatingStatus).isFalse()
        assertThat(state.statusUpdateError).isEqualTo("Unable to reach the WP HelpD server.")
    }

    @Test
    fun updateTicketStatus_blocksDuplicateSubmissionWhileInFlight() = runTest {
        val api = FakeHelpdeskAdminApi(statusDelayMs = 10)
        val viewModel = TicketsViewModel(
            repository = HelpdeskRepository { api }
        )

        viewModel.selectTicket(101)
        advanceUntilIdle()

        viewModel.updateTicketStatus("resolved")
        viewModel.updateTicketStatus("resolved")
        advanceUntilIdle()

        assertThat(api.statusUpdateCallCount).isEqualTo(1)
    }

    @Test
    fun updateTicketStatus_doesNotRefreshStaleTicketWhenSelectionChanges() = runTest {
        val api = FakeHelpdeskAdminApi(
            statusDelayMs = 10,
            ticketDetailResponsesById = mapOf(
                101 to ticketDetailResponse(id = 101, subject = "Ticket 101"),
                202 to ticketDetailResponse(id = 202, subject = "Ticket 202")
            )
        )
        val viewModel = TicketsViewModel(
            repository = HelpdeskRepository { api }
        )

        viewModel.selectTicket(101)
        advanceUntilIdle()

        viewModel.updateTicketStatus("resolved")
        viewModel.selectTicket(202)
        advanceUntilIdle()

        val state = viewModel.uiState.value
        assertThat(state.selectedTicketId).isEqualTo(202)
        assertThat(state.ticketDetail?.ticket?.id).isEqualTo(202)
        assertThat(api.ticketDetailRequestsById[101]).isEqualTo(1)
    }

    @Test
    fun updateTicketStatus_doesNotOverwriteCurrentDetailWhenSelectionChangesDuringRefresh() = runTest {
        val api = FakeHelpdeskAdminApi(
            statusDelayMs = 10,
            ticketDetailDelayMsById = mapOf(101 to 50, 202 to 0),
            ticketDetailResponsesById = mapOf(
                101 to ticketDetailResponse(id = 101, subject = "Ticket 101"),
                202 to ticketDetailResponse(id = 202, subject = "Ticket 202")
            )
        )
        val viewModel = TicketsViewModel(
            repository = HelpdeskRepository { api }
        )

        viewModel.selectTicket(101)
        advanceUntilIdle()

        viewModel.updateTicketStatus("resolved")
        advanceTimeBy(10)
        runCurrent()
        viewModel.selectTicket(202)
        advanceUntilIdle()

        val state = viewModel.uiState.value
        assertThat(state.selectedTicketId).isEqualTo(202)
        assertThat(state.ticketDetail?.ticket?.id).isEqualTo(202)
        assertThat(api.ticketDetailRequestsById[101]).isEqualTo(2)
    }

    @Test
    fun updateTicketStatus_ignoresFailureAfterSelectionChanges() = runTest {
        val api = FakeHelpdeskAdminApi(
            statusDelayMs = 50,
            statusThrowable = IOException("server down"),
            ticketDetailResponsesById = mapOf(
                101 to ticketDetailResponse(id = 101, subject = "Ticket 101"),
                202 to ticketDetailResponse(id = 202, subject = "Ticket 202")
            )
        )
        val viewModel = TicketsViewModel(
            repository = HelpdeskRepository { api }
        )

        viewModel.selectTicket(101)
        advanceUntilIdle()

        viewModel.updateTicketStatus("resolved")
        assertThat(viewModel.uiState.value.isUpdatingStatus).isTrue()

        viewModel.selectTicket(202)
        advanceUntilIdle()

        val state = viewModel.uiState.value
        assertThat(state.selectedTicketId).isEqualTo(202)
        assertThat(state.ticketDetail?.ticket?.id).isEqualTo(202)
        assertThat(state.isUpdatingStatus).isFalse()
        assertThat(state.statusUpdateError).isNull()
    }

    @Test
    fun submitNote_clearsNoteTextAndRefreshesDetailOnSuccess() = runTest {
        val api = FakeHelpdeskAdminApi(
            ticketDetailResponse = TicketDetailResponseDto(
                success = true,
                data = TicketDetailDto(id = 101, ticketNo = "HD-000101", subject = "Login issue", status = "open")
            ),
            ticketMessagesResponse = TicketMessagesResponseDto(
                items = listOf(
                    TicketThreadEntryDto(
                        id = 9301,
                        authorType = "agent",
                        authorName = "Support Agent",
                        body = "Internal note text.",
                        isInternal = JsonParser.parseString("true")
                    )
                )
            ),
            noteResponse = NoteResponseDto(success = true)
        )
        val viewModel = TicketsViewModel(
            repository = HelpdeskRepository { api }
        )

        viewModel.selectTicket(101)
        advanceUntilIdle()

        viewModel.updateNoteText("Internal note text.")
        viewModel.submitNote()
        advanceUntilIdle()

        val state = viewModel.uiState.value
        assertThat(state.noteText).isEmpty()
        assertThat(state.isAddingNote).isFalse()
        assertThat(state.noteError).isNull()
        assertThat(state.selectedTicketId).isEqualTo(101)
        assertThat(state.ticketDetail).isNotNull()
        assertThat(state.ticketDetail?.thread).isNotEmpty()
        assertThat(state.ticketDetail?.thread?.single()?.isInternal).isTrue()
        assertThat(api.ticketDetailRequestsById[101]).isEqualTo(2)
        assertThat(api.ticketMessagesRequestsById[101]).isEqualTo(2)
    }

    @Test
    fun submitNote_setsNoteErrorOnFailure() = runTest {
        val viewModel = TicketsViewModel(
            repository = HelpdeskRepository {
                FakeHelpdeskAdminApi(
                    ticketDetailResponse = TicketDetailResponseDto(
                        success = true,
                        data = TicketDetailDto(id = 101, ticketNo = "HD-000101", subject = "Login issue", status = "open")
                    ),
                    noteThrowable = IOException("offline")
                )
            }
        )

        viewModel.selectTicket(101)
        advanceUntilIdle()

        viewModel.updateNoteText("My note.")
        viewModel.submitNote()
        advanceUntilIdle()

        val state = viewModel.uiState.value
        assertThat(state.isAddingNote).isFalse()
        assertThat(state.noteError).isEqualTo("Unable to reach the WP HelpD server.")
    }

    @Test
    fun submitNote_setsErrorWhenNoteIsBlank() = runTest {
        val viewModel = TicketsViewModel(
            repository = HelpdeskRepository { FakeHelpdeskAdminApi() }
        )

        viewModel.selectTicket(101)
        advanceUntilIdle()

        viewModel.updateNoteText("  ")
        viewModel.submitNote()

        assertThat(viewModel.uiState.value.noteError).isEqualTo("Note cannot be empty.")
    }

    @Test
    fun submitNote_setsLoadingStateWhileRequestIsInFlight() = runTest {
        val api = FakeHelpdeskAdminApi(noteDelayMs = 50)
        val viewModel = TicketsViewModel(
            repository = HelpdeskRepository { api }
        )

        viewModel.selectTicket(101)
        advanceUntilIdle()

        viewModel.updateNoteText("My note.")
        viewModel.submitNote()

        val state = viewModel.uiState.value
        assertThat(state.isAddingNote).isTrue()
        assertThat(state.noteError).isNull()

        advanceUntilIdle()
        assertThat(viewModel.uiState.value.isAddingNote).isFalse()
    }

    @Test
    fun submitNote_blocksDuplicateSubmissionWhileInFlight() = runTest {
        val api = FakeHelpdeskAdminApi(noteDelayMs = 10)
        val viewModel = TicketsViewModel(
            repository = HelpdeskRepository { api }
        )

        viewModel.selectTicket(101)
        advanceUntilIdle()

        viewModel.updateNoteText("My note.")
        viewModel.submitNote()
        viewModel.submitNote()
        advanceUntilIdle()

        assertThat(api.noteCallCount).isEqualTo(1)
    }

    @Test
    fun submitNote_doesNotRefreshStaleTicketWhenSelectionChanges() = runTest {
        val api = FakeHelpdeskAdminApi(
            noteDelayMs = 10,
            ticketDetailResponsesById = mapOf(
                101 to ticketDetailResponse(id = 101, subject = "Ticket 101"),
                202 to ticketDetailResponse(id = 202, subject = "Ticket 202")
            )
        )
        val viewModel = TicketsViewModel(
            repository = HelpdeskRepository { api }
        )

        viewModel.selectTicket(101)
        advanceUntilIdle()

        viewModel.updateNoteText("My note.")
        viewModel.submitNote()
        viewModel.selectTicket(202)
        advanceUntilIdle()

        val state = viewModel.uiState.value
        assertThat(state.selectedTicketId).isEqualTo(202)
        assertThat(state.ticketDetail?.ticket?.id).isEqualTo(202)
        assertThat(api.ticketDetailRequestsById[101]).isEqualTo(1)
    }

    @Test
    fun submitNote_doesNotOverwriteCurrentDetailWhenSelectionChangesDuringRefresh() = runTest {
        val api = FakeHelpdeskAdminApi(
            noteDelayMs = 10,
            ticketDetailDelayMsById = mapOf(101 to 50, 202 to 0),
            ticketDetailResponsesById = mapOf(
                101 to ticketDetailResponse(id = 101, subject = "Ticket 101"),
                202 to ticketDetailResponse(id = 202, subject = "Ticket 202")
            )
        )
        val viewModel = TicketsViewModel(
            repository = HelpdeskRepository { api }
        )

        viewModel.selectTicket(101)
        advanceUntilIdle()

        viewModel.updateNoteText("My note.")
        viewModel.submitNote()
        advanceTimeBy(10)
        runCurrent()
        viewModel.selectTicket(202)
        advanceUntilIdle()

        val state = viewModel.uiState.value
        assertThat(state.selectedTicketId).isEqualTo(202)
        assertThat(state.ticketDetail?.ticket?.id).isEqualTo(202)
        assertThat(api.ticketDetailRequestsById[101]).isEqualTo(2)
    }

    @Test
    fun submitNote_ignoresFailureAfterSelectionChanges() = runTest {
        val api = FakeHelpdeskAdminApi(
            noteDelayMs = 50,
            noteThrowable = IOException("offline"),
            ticketDetailResponsesById = mapOf(
                101 to ticketDetailResponse(id = 101, subject = "Ticket 101"),
                202 to ticketDetailResponse(id = 202, subject = "Ticket 202")
            )
        )
        val viewModel = TicketsViewModel(
            repository = HelpdeskRepository { api }
        )

        viewModel.selectTicket(101)
        advanceUntilIdle()

        viewModel.updateNoteText("My note.")
        viewModel.submitNote()
        assertThat(viewModel.uiState.value.isAddingNote).isTrue()

        viewModel.selectTicket(202)
        advanceUntilIdle()

        val state = viewModel.uiState.value
        assertThat(state.selectedTicketId).isEqualTo(202)
        assertThat(state.ticketDetail?.ticket?.id).isEqualTo(202)
        assertThat(state.isAddingNote).isFalse()
        assertThat(state.noteError).isNull()
    }

    @Test
    fun startupBootstrap_routesToSetupWithRetryMessageWhenServerIsUnreachable() = runTest {
        val saved = AuthConfig(
            siteUrl = "https://saved.example.com",
            username = "savedUser",
            applicationPassword = "savedPass"
        )
        val viewModel = TicketsViewModel(
            repository = HelpdeskRepository {
                FakeHelpdeskAdminApi(authThrowable = IOException("offline"))
            },
            serverConfigRepository = FakeServerConfigRepository(initial = saved)
        )

        advanceUntilIdle()

        val state = viewModel.uiState.value
        assertThat(state.isBootstrapping).isFalse()
        assertThat(state.requiresSetup).isTrue()
        assertThat(state.currentUser).isNull()
        assertThat(state.errorMessage).isEqualTo(
            "Unable to reach the WP HelpD server. Check your connection and retry."
        )
    }

    @Test
    fun connectAndLoadTickets_rejectsHttpUrlWithClearMessage() = runTest {
        val viewModel = TicketsViewModel(
            repository = HelpdeskRepository { FakeHelpdeskAdminApi() }
        )

        viewModel.updateSiteUrl("http://example.com")
        viewModel.updateUsername("admin")
        viewModel.updateApplicationPassword("secret")
        viewModel.connectAndLoadTickets()
        advanceUntilIdle()

        val state = viewModel.uiState.value
        assertThat(state.requiresSetup).isTrue()
        assertThat(state.errorMessage).isEqualTo("Use an HTTPS site URL for WP HelpD.")
        assertThat(state.isLoading).isFalse()
        assertThat(state.currentUser).isNull()
    }

    @Test
    fun startupBootstrap_routesToSetupWhenSavedConfigHasHttpUrl() = runTest {
        val saved = AuthConfig(
            siteUrl = "http://not-https.example.com",
            username = "savedUser",
            applicationPassword = "savedPass"
        )
        // Mirror the real ApiClientFactory behaviour: adminApiUrl() throws
        // IllegalArgumentException with this message when the URL is not HTTPS.
        // Using a single constant here ensures the lambda and the assertion agree.
        val httpsRequiredMsg = "WP HelpD requires an HTTPS site URL."
        val viewModel = TicketsViewModel(
            repository = HelpdeskRepository { config ->
                require(config.siteUrl.trim().startsWith("https://")) { httpsRequiredMsg }
                FakeHelpdeskAdminApi()
            },
            serverConfigRepository = FakeServerConfigRepository(initial = saved)
        )

        advanceUntilIdle()

        val state = viewModel.uiState.value
        assertThat(state.isBootstrapping).isFalse()
        assertThat(state.requiresSetup).isTrue()
        assertThat(state.currentUser).isNull()
        assertThat(state.errorMessage).isEqualTo(httpsRequiredMsg)
    }

    @Test
    fun clearSensitiveSessionState_removesProtectedTicketData() = runTest {
        val viewModel = TicketsViewModel(
            repository = HelpdeskRepository { FakeHelpdeskAdminApi() }
        )

        viewModel.selectTicket(101)
        advanceUntilIdle()
        assertThat(viewModel.uiState.value.ticketDetail).isNotNull()

        viewModel.clearSensitiveSessionState()

        val state = viewModel.uiState.value
        assertThat(state.currentUser).isNull()
        assertThat(state.tickets).isEmpty()
        assertThat(state.selectedTicketId).isNull()
        assertThat(state.ticketDetail).isNull()
        assertThat(state.replyText).isEmpty()
        assertThat(state.noteText).isEmpty()
    }

    @Test
    fun logout_clearsSavedConfigAndSession() = runTest {
        val saved = AuthConfig(
            siteUrl = "https://saved.example.com",
            username = "savedUser",
            applicationPassword = "savedPass",
            wpNonce = "savedNonce"
        )
        val fakeConfigRepo = FakeServerConfigRepository(initial = saved)
        val viewModel = TicketsViewModel(
            repository = HelpdeskRepository { FakeHelpdeskAdminApi() },
            serverConfigRepository = fakeConfigRepo
        )
        advanceUntilIdle()

        viewModel.logout()

        val state = viewModel.uiState.value
        assertThat(fakeConfigRepo.load()).isNull()
        assertThat(state.requiresSetup).isTrue()
        assertThat(state.siteUrl).isEmpty()
        assertThat(state.username).isEmpty()
        assertThat(state.applicationPassword).isEmpty()
        assertThat(state.currentUser).isNull()
        assertThat(state.tickets).isEmpty()
        assertThat(state.ticketDetail).isNull()
    }

    @Test
    fun ticketDetailResponseDto_mapsRequesterNameAndEmailFromFlatResponse() {
        val dto = TicketDetailResponseDto(
            success = true,
            id = 42,
            ticketNo = "HD-000042",
            subject = "Cannot login",
            status = "pending_client_reply",
            requesterName = "Alice Smith",
            requesterEmail = "alice@example.test"
        )
        val detail = dto.toTicketDetail()
        assertThat(detail.ticket.customerName).isEqualTo("Alice Smith")
        assertThat(detail.ticket.customerEmail).isEqualTo("alice@example.test")
    }

    @Test
    fun statusLabel_returnsHumanReadableLabels() {
        val slugs = mapOf(
            "new"                  to "New",
            "open"                 to "New",
            "pending_agent_reply"  to "Pending Agent Reply",
            "pending"              to "Pending Agent Reply",
            "triaged"              to "Pending Agent Reply",
            "in_progress"          to "Pending Agent Reply",
            "pending_client_reply" to "Pending Client Reply",
            "waiting_customer"     to "Pending Client Reply",
            "resolved"             to "Resolved",
            "closed"               to "Closed"
        )
        slugs.forEach { (slug, expected) ->
            val ticket = TicketModel(
                id = 1, ticketNo = "HD-1", subject = "s", status = slug,
                priority = null, customerName = null, customerEmail = null,
                createdAt = null, updatedAt = null, messageCount = 0, lastMessageExcerpt = null
            )
            assertThat(ticket.statusLabel()).isEqualTo(expected)
        }
    }

    @Test
    fun appearanceColors_statusColorSupportsLegacyStatusAliases() {
        val colors = AppearanceColors(
            statusNewColor = "#111111",
            statusPendingAgentColor = "#222222",
            statusPendingClientColor = "#333333",
            statusResolvedColor = "#444444",
            statusClosedColor = "#555555"
        )

        assertThat(colors.statusColor("open")).isEqualTo("#111111")
        assertThat(colors.statusColor("pending")).isEqualTo("#222222")
        assertThat(colors.statusColor("triaged")).isEqualTo("#222222")
        assertThat(colors.statusColor("in_progress")).isEqualTo("#222222")
        assertThat(colors.statusColor("waiting_customer")).isEqualTo("#333333")
    }
}

private fun unauthorizedHttpException(): HttpException = HttpException(
    Response.error<String>(
        401,
        """{"message":"Unauthorized"}""".toResponseBody("application/json".toMediaType())
    )
)

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
    private val ticketDetailResponsesById: Map<Int, TicketDetailResponseDto> = emptyMap(),
    private val ticketDetailResponseSequenceById: Map<Int, List<TicketDetailResponseDto>> = emptyMap(),
    private val ticketDetailDelayMsById: Map<Int, Long> = emptyMap(),
    private val ticketDetailDelaySequenceById: Map<Int, List<Long>> = emptyMap(),
    private val detailThrowable: Throwable? = null,
    private val authThrowable: Throwable? = null,
    private val authAppearance: AppearanceColorsDto? = null,
    private val replyResponse: ReplyResponseDto = ReplyResponseDto(success = true),
    private val replyThrowable: Throwable? = null,
    private val replyDelayMs: Long = 0,
    private val statusResponse: StatusUpdateResponseDto = StatusUpdateResponseDto(success = true),
    private val statusThrowable: Throwable? = null,
    private val statusDelayMs: Long = 0,
    private val ticketMessagesResponse: TicketMessagesResponseDto = TicketMessagesResponseDto(items = emptyList()),
    private val noteResponse: NoteResponseDto = NoteResponseDto(success = true),
    private val noteThrowable: Throwable? = null,
    private val noteDelayMs: Long = 0
) : HelpdeskAdminApi {
    var replyCallCount: Int = 0
        private set
    var statusUpdateCallCount: Int = 0
        private set
    var noteCallCount: Int = 0
        private set
    val ticketDetailRequestsById: MutableMap<Int, Int> = mutableMapOf()
    val ticketMessagesRequestsById: MutableMap<Int, Int> = mutableMapOf()

    override suspend fun authCheck(): AuthCheckResponseDto {
        authThrowable?.let { throw it }
        return AuthCheckResponseDto(
            success = true,
            user = UserDto(id = 1, name = "Agent", email = "agent@example.test"),
            appearance = authAppearance
        )
    }

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
        val requestIndex = ticketDetailRequestsById[id] ?: 0
        ticketDetailRequestsById[id] = requestIndex + 1
        val delayMs = ticketDetailDelaySequenceById[id]?.getOrNull(requestIndex)
            ?: ticketDetailDelayMsById[id]
        if (delayMs != null && delayMs > 0) {
            delay(delayMs)
        }
        detailThrowable?.let { throw it }
        return ticketDetailResponseSequenceById[id]?.getOrNull(requestIndex)
            ?: ticketDetailResponsesById[id]
            ?: ticketDetailResponse
    }

    override suspend fun getTicketMessages(id: Int): TicketMessagesResponseDto {
        ticketMessagesRequestsById[id] = (ticketMessagesRequestsById[id] ?: 0) + 1
        return ticketMessagesResponse
    }

    override suspend fun replyToTicket(id: Int, request: ReplyRequestDto): ReplyResponseDto {
        replyCallCount += 1
        if (replyDelayMs > 0) delay(replyDelayMs)
        replyThrowable?.let { throw it }
        return replyResponse
    }

    override suspend fun updateTicketStatus(id: Int, request: StatusUpdateRequestDto): StatusUpdateResponseDto {
        statusUpdateCallCount += 1
        if (statusDelayMs > 0) delay(statusDelayMs)
        statusThrowable?.let { throw it }
        return statusResponse
    }

    override suspend fun addTicketNote(id: Int, request: NoteRequestDto): NoteResponseDto {
        noteCallCount += 1
        if (noteDelayMs > 0) delay(noteDelayMs)
        noteThrowable?.let { throw it }
        return noteResponse
    }

    override suspend fun getNotificationsSince(sinceTimestamp: Long): NotificationsSinceResponseDto =
        NotificationsSinceResponseDto(success = true, newTickets = emptyList(), newReplies = emptyList())
}

private fun ticketDetailResponse(id: Int, subject: String): TicketDetailResponseDto = TicketDetailResponseDto(
    success = true,
    data = TicketDetailDto(
        id = id,
        ticketNo = "HD-$id",
        subject = subject,
        status = "open"
    )
)

private class FakeServerConfigRepository(initial: AuthConfig? = null) : ServerConfigRepository {
    private var stored: AuthConfig? = initial

    override fun load(): AuthConfig? = stored
    override fun save(config: AuthConfig) { stored = config }
    override fun clear() { stored = null }
}
