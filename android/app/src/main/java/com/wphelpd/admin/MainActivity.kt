package com.wphelpd.admin

import android.content.Intent
import android.net.Uri
import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.compose.foundation.Image
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.res.painterResource
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.lifecycle.lifecycleScope
import com.google.firebase.messaging.FirebaseMessaging
import com.wphelpd.admin.core.config.SecureServerConfigRepository
import com.wphelpd.admin.core.ui.theme.WpHelpdTheme
import com.wphelpd.admin.feature.applock.AppLockLifecyclePolicy
import com.wphelpd.admin.feature.applock.AppLockManager
import com.wphelpd.admin.feature.applock.AppLockViewModel
import com.wphelpd.admin.feature.applock.CreatePasswordScreen
import com.wphelpd.admin.feature.applock.UnlockScreen
import com.wphelpd.admin.feature.push.PushNotificationAccessGate
import com.wphelpd.admin.feature.push.PushTokenStateStore
import com.wphelpd.admin.feature.push.PushTokenSyncManager
import com.wphelpd.admin.feature.tickets.TicketsRoute
import com.wphelpd.admin.feature.tickets.TicketsViewModel
import kotlinx.coroutines.launch
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.asStateFlow

class MainActivity : ComponentActivity() {
    private val pendingTicketId = MutableStateFlow<Int?>(null)
    private lateinit var lockViewModel: AppLockViewModel
    private lateinit var ticketsViewModel: TicketsViewModel

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        pendingTicketId.value = extractTicketIdFromIntent(intent)
        val lockManager = AppLockManager(applicationContext)
        val serverConfigRepository = SecureServerConfigRepository(applicationContext)
        val pushTokenStateStore = PushTokenStateStore(applicationContext)
        val pushTokenSyncManager = PushTokenSyncManager(stateStore = pushTokenStateStore)
        lockViewModel = AppLockViewModel(lockManager)
        ticketsViewModel = TicketsViewModel(serverConfigRepository = serverConfigRepository)
        FirebaseMessaging.getInstance().token
            .addOnSuccessListener { token ->
                if (token.isNotBlank()) {
                    pushTokenStateStore.saveCurrentToken(token)
                }
            }

        setContent {
            WpHelpdTheme {
                val lockState = lockViewModel.uiState.collectAsStateWithLifecycle().value
                val pendingTicket = pendingTicketId.asStateFlow().collectAsStateWithLifecycle().value

                when {
                    lockState.isInitialising -> {
                        SplashLoadingScreen()
                    }
                    lockState.isUnlocked -> {
                        val ticketsState = ticketsViewModel.uiState.collectAsStateWithLifecycle().value

                        LaunchedEffect(lockState.isUnlocked) {
                            ticketsViewModel.restoreSessionFromSavedConfigIfNeeded()
                        }

                        LaunchedEffect(
                            lockState.isUnlocked,
                            ticketsState.isBootstrapping,
                            ticketsState.requiresSetup,
                            ticketsState.currentUser,
                            pendingTicket
                        ) {
                            val ticketIdToOpen = PushNotificationAccessGate.resolveTicketIdToOpen(
                                pendingTicketId = pendingTicket,
                                isUnlocked = lockState.isUnlocked,
                                isBootstrapping = ticketsState.isBootstrapping,
                                requiresSetup = ticketsState.requiresSetup,
                                hasCurrentUser = ticketsState.currentUser != null
                            )
                            if (ticketIdToOpen != null && ticketsState.selectedTicketId != ticketIdToOpen) {
                                ticketsViewModel.selectTicket(ticketIdToOpen)
                                pendingTicketId.value = null
                            }
                        }

                        LaunchedEffect(
                            lockState.isUnlocked,
                            ticketsState.currentUser,
                            ticketsState.siteUrl,
                            ticketsState.username,
                            ticketsState.applicationPassword
                        ) {
                            if (
                                lockState.isUnlocked &&
                                ticketsState.currentUser != null &&
                                ticketsState.siteUrl.isNotBlank() &&
                                ticketsState.username.isNotBlank() &&
                                ticketsState.applicationPassword.isNotBlank()
                            ) {
                                pushTokenSyncManager.registerIfNeeded(
                                    config = ticketsState.toAuthConfig()
                                )
                            }
                        }

                        if (ticketsState.isBootstrapping) {
                            SplashLoadingScreen()
                        } else {
                            TicketsRoute(
                                viewModel = ticketsViewModel,
                                onLogout = {
                                    val config = ticketsState.toAuthConfig()
                                    pushTokenStateStore.clearHandledNotifications()
                                    ticketsViewModel.logout()
                                    lockViewModel.lock()
                                    lifecycleScope.launch {
                                        pushTokenSyncManager.unregisterIfNeeded(config)
                                    }
                                }
                            )
                        }
                    }
                    lockState.isFirstRun -> {
                        CreatePasswordScreen(
                            errorMessage = lockState.errorMessage,
                            onCreatePassword = { pw, confirm ->
                                lockViewModel.createPassword(pw, confirm)
                            }
                        )
                    }
                    else -> {
                        UnlockScreen(
                            errorMessage = lockState.errorMessage,
                            onUnlock = { pw -> lockViewModel.unlock(pw) }
                        )
                    }
                }
            }

            override fun onStop() {
                super.onStop()
                if (!::lockViewModel.isInitialized || !::ticketsViewModel.isInitialized) return
                if (
                    AppLockLifecyclePolicy.shouldRelockOnStop(
                        isUnlocked = lockViewModel.uiState.value.isUnlocked,
                        isChangingConfigurations = isChangingConfigurations
                    )
                ) {
                    ticketsViewModel.clearSensitiveSessionState()
                    lockViewModel.lock()
                }
            }
        }
    }

    override fun onNewIntent(intent: Intent) {
        super.onNewIntent(intent)
        setIntent(intent)
        pendingTicketId.value = extractTicketIdFromIntent(intent)
    }

    private fun extractTicketIdFromIntent(intent: Intent?): Int? {
        val extraTicketId = intent?.getIntExtra(EXTRA_TICKET_ID, 0)?.takeIf { it > 0 }
        if (extraTicketId != null) return extraTicketId
        return extractTicketIdFromDeepLink(intent?.data)
    }

    private fun extractTicketIdFromDeepLink(uri: Uri?): Int? {
        if (uri?.scheme != "wphelpd") return null
        if (uri.host != "ticket") return null
        return uri.lastPathSegment?.toIntOrNull()?.takeIf { it > 0 }
    }

    companion object {
        const val EXTRA_TICKET_ID = "extra_ticket_id"
        const val ACTION_OPEN_TICKET = "com.wphelpd.admin.action.OPEN_TICKET"
    }
}

@androidx.compose.runtime.Composable
private fun SplashLoadingScreen() {
    Box(modifier = Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
        Image(
            painter = painterResource(id = R.drawable.splash_background),
            contentDescription = null,
            contentScale = ContentScale.Crop,
            modifier = Modifier.fillMaxSize()
        )
        Box(
            modifier = Modifier
                .fillMaxSize()
                .background(Color(0x99000000))
        )
        CircularProgressIndicator(color = Color.White)
    }
}
