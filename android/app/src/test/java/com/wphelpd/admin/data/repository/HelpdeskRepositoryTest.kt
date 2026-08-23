package com.wphelpd.admin.data.repository

import com.google.common.truth.Truth.assertThat
import com.google.gson.JsonParser
import com.wphelpd.admin.core.network.AuthConfig
import com.wphelpd.admin.core.network.NetworkResult
import com.wphelpd.admin.data.api.HelpdeskAdminApi
import com.wphelpd.admin.data.api.dto.AuthCheckResponseDto
import com.wphelpd.admin.data.api.dto.DeviceTokenRequestDto
import com.wphelpd.admin.data.api.dto.DeviceTokenResponseDto
import com.wphelpd.admin.data.api.dto.NoteRequestDto
import com.wphelpd.admin.data.api.dto.NoteResponseDto
import com.wphelpd.admin.data.api.dto.PaginationDto
import com.wphelpd.admin.data.api.dto.ReplyResultDto
import com.wphelpd.admin.data.api.dto.ReplyRequestDto
import com.wphelpd.admin.data.api.dto.ReplyResponseDto
import com.wphelpd.admin.data.api.dto.StatusUpdateRequestDto
import com.wphelpd.admin.data.api.dto.StatusUpdateResponseDto
import com.wphelpd.admin.data.api.dto.TicketAttachmentDto
import com.wphelpd.admin.data.api.dto.TicketCustomerDto
import com.wphelpd.admin.data.api.dto.TicketDetailDto
import com.wphelpd.admin.data.api.dto.TicketDetailResponseDto
import com.wphelpd.admin.data.api.dto.TicketDto
import com.wphelpd.admin.data.api.dto.TicketListResponseDto
import com.wphelpd.admin.data.api.dto.TicketMessagesResponseDto
import com.wphelpd.admin.data.api.dto.TicketThreadEntryDto
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
        val user = (result as NetworkResult.Success).value.user
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

    @Test
    fun fetchTicketDetail_mapsContractThreadAndAttachments() = runTest {
        val repository = HelpdeskRepository {
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
                        assignedTo = JsonParser.parseString("""{"id":12,"name":"Admin User"}"""),
                        messages = listOf(
                            TicketThreadEntryDto(
                                id = 7001,
                                authorType = "customer",
                                authorName = "Jane Smith",
                                body = "I cannot sign in.",
                                createdAt = "2026-08-22T10:15:00Z"
                            )
                        ),
                        attachments = listOf(
                            TicketAttachmentDto(
                                id = 501,
                                name = "screenshot.png",
                                url = "https://example.test/screenshot.png",
                                mimeType = "image/png"
                            )
                        )
                    )
                )
            )
        }

        val result = repository.fetchTicketDetail(config, 101)

        assertThat(result).isInstanceOf(NetworkResult.Success::class.java)
        val detail = (result as NetworkResult.Success).value
        assertThat(detail.ticket.ticketNo).isEqualTo("HD-000101")
        assertThat(detail.thread).hasSize(1)
        assertThat(detail.attachments.single().name).isEqualTo("screenshot.png")
        assertThat(detail.assignedToName).isEqualTo("Admin User")
    }

    @Test
    fun fetchTicketDetail_fallsBackToMessagesEndpoint() = runTest {
        val repository = HelpdeskRepository {
            FakeHelpdeskAdminApi(
                ticketDetailResponse = TicketDetailResponseDto(
                    id = 101,
                    ticketNo = "HD-000101",
                    subject = "Login issue",
                    status = "open",
                    messageCount = 1
                ),
                ticketMessagesResponse = TicketMessagesResponseDto(
                    items = listOf(
                        TicketThreadEntryDto(
                            id = 8001,
                            authorType = "agent",
                            authorName = "Admin User",
                            body = "Please reset your password."
                        )
                    )
                )
            )
        }

        val result = repository.fetchTicketDetail(config, 101)

        assertThat(result).isInstanceOf(NetworkResult.Success::class.java)
        val detail = (result as NetworkResult.Success).value
        assertThat(detail.thread).hasSize(1)
        assertThat(detail.thread.single().authorType).isEqualTo("agent")
    }

    @Test
    fun fetchTicketDetail_fallsBackToMessagesEndpointWhenEmbeddedMessagesAreEmpty() = runTest {
        val repository = HelpdeskRepository {
            FakeHelpdeskAdminApi(
                ticketDetailResponse = TicketDetailResponseDto(
                    success = true,
                    data = TicketDetailDto(
                        id = 101,
                        ticketNo = "HD-000101",
                        subject = "Login issue",
                        status = "open",
                        messages = emptyList()
                    )
                ),
                ticketMessagesResponse = TicketMessagesResponseDto(
                    items = listOf(
                        TicketThreadEntryDto(
                            id = 8101,
                            authorType = "agent",
                            authorName = "Admin User",
                            body = "Saved reply from fallback endpoint."
                        )
                    )
                )
            )
        }

        val result = repository.fetchTicketDetail(config, 101)

        assertThat(result).isInstanceOf(NetworkResult.Success::class.java)
        val detail = (result as NetworkResult.Success).value
        assertThat(detail.thread).hasSize(1)
        assertThat(detail.thread.single().body).isEqualTo("Saved reply from fallback endpoint.")
    }

    @Test
    fun fetchTicketDetail_fallsBackToWrappedMessagesPayloadAndMapsThreadFields() = runTest {
        val repository = HelpdeskRepository {
            FakeHelpdeskAdminApi(
                ticketDetailResponse = TicketDetailResponseDto(
                    success = true,
                    data = TicketDetailDto(
                        id = 101,
                        ticketNo = "HD-000101",
                        subject = "Login issue",
                        status = "open",
                        messages = emptyList()
                    )
                ),
                ticketMessagesResponse = TicketMessagesResponseDto(
                    success = true,
                    data = JsonParser.parseString(
                        """
                        {
                          "items": [
                            {
                              "id": 8201,
                              "author_type": "agent",
                              "author_name": "Admin User",
                              "body": "Saved wrapped fallback reply.",
                              "created_at": "2026-08-22T12:30:00Z",
                              "is_internal": 1
                            }
                          ]
                        }
                        """.trimIndent()
                    )
                )
            )
        }

        val result = repository.fetchTicketDetail(config, 101)

        assertThat(result).isInstanceOf(NetworkResult.Success::class.java)
        val detail = (result as NetworkResult.Success).value
        assertThat(detail.thread).hasSize(1)
        val entry = detail.thread.single()
        assertThat(entry.id).isEqualTo(8201)
        assertThat(entry.authorType).isEqualTo("agent")
        assertThat(entry.authorName).isEqualTo("Admin User")
        assertThat(entry.body).isEqualTo("Saved wrapped fallback reply.")
        assertThat(entry.createdAt).isEqualTo("2026-08-22T12:30:00Z")
        assertThat(entry.isInternal).isTrue()
    }

    @Test
    fun fetchTicketDetail_usesWrappedMessagesWhenWrappedItemsAreUnusable() = runTest {
        val repository = HelpdeskRepository {
            FakeHelpdeskAdminApi(
                ticketDetailResponse = TicketDetailResponseDto(
                    success = true,
                    data = TicketDetailDto(
                        id = 101,
                        ticketNo = "HD-000101",
                        subject = "Login issue",
                        status = "open",
                        messages = emptyList()
                    )
                ),
                ticketMessagesResponse = TicketMessagesResponseDto(
                    success = true,
                    data = JsonParser.parseString(
                        """
                        {
                          "items": [
                            { "author_type": "agent", "body": "missing id should be skipped" }
                          ],
                          "messages": [
                            {
                              "id": 8202,
                              "author_type": "agent",
                              "author_name": "Admin User",
                              "body": "Fallback to wrapped messages entry.",
                              "created_at": "2026-08-22T12:35:00Z",
                              "is_internal": 0
                            }
                          ]
                        }
                        """.trimIndent()
                    )
                )
            )
        }

        val result = repository.fetchTicketDetail(config, 101)

        assertThat(result).isInstanceOf(NetworkResult.Success::class.java)
        val detail = (result as NetworkResult.Success).value
        assertThat(detail.thread).hasSize(1)
        assertThat(detail.thread.single().id).isEqualTo(8202)
        assertThat(detail.thread.single().body).isEqualTo("Fallback to wrapped messages entry.")
    }

    @Test
    fun fetchTicketDetail_mapsFlatResponseMessagesAndAttachments() = runTest {
        val repository = HelpdeskRepository {
            FakeHelpdeskAdminApi(
                ticketDetailResponse = TicketDetailResponseDto(
                    success = true,
                    id = 101,
                    ticketNo = "HD-000101",
                    subject = "Login issue",
                    status = "open",
                    assignedTo = JsonParser.parseString("""{"id":12,"name":"Admin User"}"""),
                    messages = listOf(
                        TicketThreadEntryDto(
                            id = 9001,
                            authorType = "customer",
                            authorName = "Jane Smith",
                            body = "I cannot sign in.",
                            createdAt = "2026-08-22T10:15:00Z"
                        )
                    ),
                    attachments = listOf(
                        TicketAttachmentDto(
                            id = 501,
                            name = "screenshot.png",
                            url = "https://example.test/screenshot.png",
                            mimeType = "image/png"
                        )
                    )
                )
            )
        }

        val result = repository.fetchTicketDetail(config, 101)

        assertThat(result).isInstanceOf(NetworkResult.Success::class.java)
        val detail = (result as NetworkResult.Success).value
        assertThat(detail.thread).hasSize(1)
        assertThat(detail.thread.single().body).isEqualTo("I cannot sign in.")
        assertThat(detail.attachments.single().name).isEqualTo("screenshot.png")
        assertThat(detail.assignedToName).isEqualTo("Admin User")
    }

    @Test
    fun replyToTicket_mapsContractReplyResultToThreadEntry() = runTest {
        val repository = HelpdeskRepository {
            FakeHelpdeskAdminApi(
                replyResponse = ReplyResponseDto(
                    success = true,
                    data = ReplyResultDto(
                        id = 9101,
                        ticketId = 101,
                        authorType = "agent",
                        authorName = "Admin User",
                        body = "Saved mobile reply.",
                        createdAt = "2026-08-22T12:00:00Z",
                        isInternal = 0
                    )
                )
            )
        }

        val result = repository.replyToTicket(config, ticketId = 101, message = "Saved mobile reply.")

        assertThat(result).isInstanceOf(NetworkResult.Success::class.java)
        val entry = (result as NetworkResult.Success).value
        assertThat(entry).isNotNull()
        assertThat(entry?.id).isEqualTo(9101)
        assertThat(entry?.authorType).isEqualTo("agent")
        assertThat(entry?.authorName).isEqualTo("Admin User")
        assertThat(entry?.body).isEqualTo("Saved mobile reply.")
        assertThat(entry?.isInternal).isFalse()
    }

    @Test
    fun updateTicketStatus_usesResponseStatusWhenAvailable() = runTest {
        val repository = HelpdeskRepository {
            FakeHelpdeskAdminApi(
                statusResponse = StatusUpdateResponseDto(
                    success = true,
                    status = "resolved"
                )
            )
        }

        val result = repository.updateTicketStatus(
            config,
            ticketId = 101,
            status = "pending_agent_reply"
        )

        assertThat(result).isInstanceOf(NetworkResult.Success::class.java)
        assertThat((result as NetworkResult.Success).value).isEqualTo("resolved")
    }

    @Test
    fun updateTicketStatus_rejectsStatusesOutsideContractValues() = runTest {
        val repository = HelpdeskRepository { FakeHelpdeskAdminApi() }

        val result = repository.updateTicketStatus(config, ticketId = 101, status = "archived")

        assertThat(result).isInstanceOf(NetworkResult.Failure::class.java)
        assertThat((result as NetworkResult.Failure).message).contains("Status must be one of:")
    }

    @Test
    fun registerDeviceToken_callsApiWithExpectedPayload() = runTest {
        val api = FakeHelpdeskAdminApi(
            deviceTokenRegisterResponse = DeviceTokenResponseDto(registered = true)
        )
        val repository = HelpdeskRepository { api }

        val result = repository.registerDeviceToken(
            config = config,
            deviceToken = "fcm-token-123",
            appVersion = "0.1.0"
        )

        assertThat(result).isInstanceOf(NetworkResult.Success::class.java)
        assertThat((result as NetworkResult.Success).value).isTrue()
        assertThat(api.lastRegisterRequest).isEqualTo(
            DeviceTokenRequestDto(
                deviceToken = "fcm-token-123",
                platform = "android",
                appVersion = "0.1.0"
            )
        )
    }

    @Test
    fun unregisterDeviceToken_callsApiWithExpectedPayload() = runTest {
        val api = FakeHelpdeskAdminApi(
            deviceTokenUnregisterResponse = DeviceTokenResponseDto(registered = false)
        )
        val repository = HelpdeskRepository { api }

        val result = repository.unregisterDeviceToken(
            config = config,
            deviceToken = "fcm-token-123",
            appVersion = "0.1.0"
        )

        assertThat(result).isInstanceOf(NetworkResult.Success::class.java)
        assertThat((result as NetworkResult.Success).value).isFalse()
        assertThat(api.lastUnregisterRequest).isEqualTo(
            DeviceTokenRequestDto(
                deviceToken = "fcm-token-123",
                platform = "android",
                appVersion = "0.1.0"
            )
        )
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
    ),
    private val ticketDetailResponse: TicketDetailResponseDto = TicketDetailResponseDto(
        id = 101,
        ticketNo = "HD-000101",
        subject = "Login issue",
        status = "open"
    ),
    private val ticketMessagesResponse: TicketMessagesResponseDto = TicketMessagesResponseDto(items = emptyList()),
    private val replyResponse: ReplyResponseDto = ReplyResponseDto(success = true),
    private val statusResponse: StatusUpdateResponseDto = StatusUpdateResponseDto(success = true),
    private val noteResponse: NoteResponseDto = NoteResponseDto(success = true),
    private val deviceTokenRegisterResponse: DeviceTokenResponseDto = DeviceTokenResponseDto(registered = true),
    private val deviceTokenUnregisterResponse: DeviceTokenResponseDto = DeviceTokenResponseDto(registered = false)
) : HelpdeskAdminApi {
    var lastRegisterRequest: DeviceTokenRequestDto? = null
    var lastUnregisterRequest: DeviceTokenRequestDto? = null

    override suspend fun authCheck(): AuthCheckResponseDto = authResponse

    override suspend fun getTickets(
        page: Int,
        perPage: Int,
        status: String?,
        search: String?
    ): TicketListResponseDto = ticketResponse

    override suspend fun getTicket(id: Int): TicketDetailResponseDto = ticketDetailResponse

    override suspend fun getTicketMessages(id: Int): TicketMessagesResponseDto = ticketMessagesResponse

    override suspend fun replyToTicket(id: Int, request: ReplyRequestDto): ReplyResponseDto = replyResponse

    override suspend fun updateTicketStatus(id: Int, request: StatusUpdateRequestDto): StatusUpdateResponseDto = statusResponse

    override suspend fun addTicketNote(id: Int, request: NoteRequestDto): NoteResponseDto = noteResponse

    override suspend fun registerDeviceToken(request: DeviceTokenRequestDto): DeviceTokenResponseDto {
        lastRegisterRequest = request
        return deviceTokenRegisterResponse
    }

    override suspend fun unregisterDeviceToken(request: DeviceTokenRequestDto): DeviceTokenResponseDto {
        lastUnregisterRequest = request
        return deviceTokenUnregisterResponse
    }
}
