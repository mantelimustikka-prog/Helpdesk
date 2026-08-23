package com.wphelpd.admin.feature.tickets

import com.google.common.truth.Truth.assertThat
import com.wphelpd.admin.core.config.ServerConfigRepository
import com.wphelpd.admin.core.network.AuthConfig
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
    private val detailThrowable: Throwable? = null,
    private val authThrowable: Throwable? = null
) : HelpdeskAdminApi {
    override suspend fun authCheck(): AuthCheckResponseDto {
        authThrowable?.let { throw it }
        return AuthCheckResponseDto(
            success = true,
            user = UserDto(id = 1, name = "Agent", email = "agent@example.test")
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
        detailThrowable?.let { throw it }
        return ticketDetailResponse
    }

    override suspend fun getTicketMessages(id: Int): TicketMessagesResponseDto = TicketMessagesResponseDto(items = emptyList())

    override suspend fun replyToTicket(id: Int, request: ReplyRequestDto): ReplyResponseDto = ReplyResponseDto(success = true)

    override suspend fun updateTicketStatus(id: Int, request: StatusUpdateRequestDto): StatusUpdateResponseDto =
        StatusUpdateResponseDto(success = true)

    override suspend fun addTicketNote(id: Int, request: NoteRequestDto): NoteResponseDto = NoteResponseDto(success = true)
}

private class FakeServerConfigRepository(initial: AuthConfig? = null) : ServerConfigRepository {
    private var stored: AuthConfig? = initial

    override fun load(): AuthConfig? = stored
    override fun save(config: AuthConfig) { stored = config }
    override fun clear() { stored = null }
}
