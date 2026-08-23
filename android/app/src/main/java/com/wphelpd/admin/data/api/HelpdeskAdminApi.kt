package com.wphelpd.admin.data.api

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
import retrofit2.http.GET
import retrofit2.http.Body
import retrofit2.http.POST
import retrofit2.http.Path
import retrofit2.http.Query

interface HelpdeskAdminApi {
    @GET("auth/check")
    suspend fun authCheck(): AuthCheckResponseDto

    @GET("tickets")
    suspend fun getTickets(
        @Query("page") page: Int = 1,
        @Query("per_page") perPage: Int = 20,
        @Query("status") status: String? = null,
        @Query("search") search: String? = null
    ): TicketListResponseDto

    @GET("tickets/{id}")
    suspend fun getTicket(@Path("id") id: Int): TicketDetailResponseDto

    @GET("tickets/{id}/messages")
    suspend fun getTicketMessages(@Path("id") id: Int): TicketMessagesResponseDto

    @POST("tickets/{id}/reply")
    suspend fun replyToTicket(
        @Path("id") id: Int,
        @Body request: ReplyRequestDto
    ): ReplyResponseDto

    @POST("tickets/{id}/status")
    suspend fun updateTicketStatus(
        @Path("id") id: Int,
        @Body request: StatusUpdateRequestDto
    ): StatusUpdateResponseDto

    @POST("tickets/{id}/note")
    suspend fun addTicketNote(
        @Path("id") id: Int,
        @Body request: NoteRequestDto
    ): NoteResponseDto

    @POST("devices/register")
    suspend fun registerDeviceToken(
        @Body request: DeviceTokenRequestDto
    ): DeviceTokenResponseDto

    @POST("devices/unregister")
    suspend fun unregisterDeviceToken(
        @Body request: DeviceTokenRequestDto
    ): DeviceTokenResponseDto
}
