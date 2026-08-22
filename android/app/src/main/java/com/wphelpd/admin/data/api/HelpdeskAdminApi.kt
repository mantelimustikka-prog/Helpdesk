package com.wphelpd.admin.data.api

import com.wphelpd.admin.data.api.dto.AuthCheckResponseDto
import com.wphelpd.admin.data.api.dto.TicketListResponseDto
import retrofit2.http.GET
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
}
