package com.wphelpd.admin.core.network

sealed interface NetworkResult<out T> {
    data class Success<T>(val value: T) : NetworkResult<T>
    data class Failure(val message: String, val throwable: Throwable? = null) : NetworkResult<Nothing>
}
