package com.wphelpd.admin

import android.content.Intent
import android.net.Uri
import android.os.Bundle
import android.util.Log
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.compose.foundation.Image
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.Button
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.lifecycle.DefaultLifecycleObserver
import androidx.lifecycle.LifecycleOwner
import androidx.lifecycle.ProcessLifecycleOwner
import androidx.lifecycle.ViewModelProvider
import com.wphelpd.admin.core.ui.theme.WpHelpdTheme
import com.wphelpd.admin.feature.applock.AppLockLifecyclePolicy
import com.wphelpd.admin.feature.applock.AppLockManager
import com.wphelpd.admin.feature.applock.AppLockViewModel
import com.wphelpd.admin.feature.applock.CreatePasswordScreen
import com.wphelpd.admin.feature.applock.PasswordResetFlow
import com.wphelpd.admin.feature.applock.UnlockScreen
import com.wphelpd.admin.feature.notifications.NotificationDialog
import com.wphelpd.admin.feature.notifications.NotificationEvent
import com.wphelpd.admin.feature.notifications.NotificationEventBus
import com.wphelpd.admin.feature.notifications.NotificationPreferences
import com.wphelpd.admin.feature.notifications.NotificationScheduler
import com.wphelpd.admin.feature.tickets.TicketsRoute
import com.wphelpd.admin.feature.tickets.TicketsViewModel
import com.wphelpd.admin.core.config.SecureServerConfigRepository
import com.wphelpd.admin.startup.StartupViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.asStateFlow

private const val TAG = "MainActivity"

class MainActivity : ComponentActivity() {
    private val pendingTicketId = MutableStateFlow<Int?>(null)
    private lateinit var startupViewModel: StartupViewModel
    private lateinit var lockViewModel: AppLockViewModel
    private lateinit var ticketsViewModel: TicketsViewModel
    private lateinit var notificationPreferences: NotificationPreferences
    private lateinit var serverConfigRepository: SecureServerConfigRepository
    private val processLifecycleObserver = object : DefaultLifecycleObserver {
        override fun onStop(owner: LifecycleOwner) {
            if (!::lockViewModel.isInitialized || !::ticketsViewModel.isInitialized) return
            if (AppLockLifecyclePolicy.shouldRelockOnProcessStop(lockViewModel.uiState.value.isUnlocked)) {
                ticketsViewModel.clearSensitiveSessionState()
                lockViewModel.lock()
            }
        }
    }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        startupViewModel = ViewModelProvider(this, StartupViewModel.factory)[StartupViewModel::class.java]
        initializeApp()
        setContent {
            WpHelpdTheme {
                val errorMessage = startupViewModel.startupError.collectAsStateWithLifecycle().value
                if (errorMessage != null) {
                    StartupErrorScreen(
                        message = errorMessage,
                        onRetry = {
                            startupViewModel.clearError()
                            initializeApp()
                        }
                    )
                    return@WpHelpdTheme
                }

                if (!::lockViewModel.isInitialized || !::ticketsViewModel.isInitialized) {
                    SplashLoadingScreen()
                    return@WpHelpdTheme
                }

                val lockState = lockViewModel.uiState.collectAsStateWithLifecycle().value
                val pendingTicket = pendingTicketId.asStateFlow().collectAsStateWithLifecycle().value
                var pendingNotification by remember { mutableStateOf<NotificationEvent?>(null) }

                // Collect notification events from the polling service regardless of lock
                // state so that system-level notifications are never missed while the app is
                // backgrounded or the screen is locked.  The in-app dialog is shown only when
                // the app is unlocked (see below).
                LaunchedEffect(Unit) {
                    NotificationEventBus.events.collect { event ->
                        pendingNotification = event
                    }
                }

                when {
                    lockState.isInitialising -> {
                        SplashLoadingScreen()
                    }
                    lockState.isUnlocked -> {
                        val ticketsState = ticketsViewModel.uiState.collectAsStateWithLifecycle().value

                        LaunchedEffect(lockState.isUnlocked) {
                            ticketsViewModel.restoreSessionFromSavedConfigIfNeeded()
                        }

                        LaunchedEffect(ticketsState.currentUser) {
                            if (ticketsState.currentUser != null) {
                                try {
                                    NotificationScheduler.schedule(applicationContext)
                                    Log.d(TAG, "Notification polling scheduled successfully.")
                                } catch (e: Exception) {
                                    Log.e(TAG, "Failed to schedule notifications: ${e.message}", e)
                                }
                            }
                        }

                        // Open ticket from deep link when the app is unlocked and ready.
                        LaunchedEffect(
                            lockState.isUnlocked,
                            ticketsState.currentUser,
                            pendingTicket
                        ) {
                            if (
                                lockState.isUnlocked &&
                                ticketsState.currentUser != null &&
                                pendingTicket != null
                            ) {
                                ticketsViewModel.selectTicket(pendingTicket)
                                pendingTicketId.value = null
                            }
                        }

                        // Show in-app notification dialog when poller finds new items.
                        if (pendingNotification != null) {
                            val event = pendingNotification!!
                            NotificationDialog(
                                newTicketCount = event.newTicketCount,
                                newReplyCount = event.newReplyCount,
                                onView = {
                                    ticketsViewModel.refreshTickets()
                                    pendingNotification = null
                                },
                                onDismiss = { pendingNotification = null }
                            )
                        }

                        if (ticketsState.isBootstrapping) {
                            SplashLoadingScreen()
                        } else {
                            TicketsRoute(
                                viewModel = ticketsViewModel,
                                onLogout = {
                                    pendingTicketId.value = null
                                    notificationPreferences.clear()
                                    NotificationScheduler.cancel(applicationContext)
                                    ticketsViewModel.logout()
                                    lockViewModel.lock()
                                }
                            )
                        }
                    }
                    lockState.isFirstRun -> {
                        CreatePasswordScreen(
                            errorMessage = lockState.errorMessage,
                            onCreatePassword = { pw, confirm, email ->
                                lockViewModel.createPassword(pw, confirm, email)
                            }
                        )
                    }
                    else -> {
                        var showPasswordResetFlow by remember { mutableStateOf(false) }
                        val siteUrl = serverConfigRepository.load()?.siteUrl

                        if (showPasswordResetFlow && siteUrl != null) {
                            PasswordResetFlow(
                                siteUrl = siteUrl,
                                hintEmail = lockViewModel.getStoredEmail(),
                                onResetSuccess = { newPassword ->
                                    lockViewModel.updatePassword(newPassword)
                                    showPasswordResetFlow = false
                                },
                                onCancel = { showPasswordResetFlow = false }
                            )
                        } else {
                            UnlockScreen(
                                errorMessage = lockState.errorMessage,
                                onUnlock = { pw -> lockViewModel.unlock(pw) },
                                onForgotPassword = if (siteUrl != null) {
                                    { showPasswordResetFlow = true }
                                } else null
                            )
                        }
                    }
                }
            }
        }
    }

    override fun onDestroy() {
        super.onDestroy()
        ProcessLifecycleOwner.get().lifecycle.removeObserver(processLifecycleObserver)
    }

    private fun initializeApp() {
        // On retry, ViewModelProvider returns the same ViewModel instances already held in the
        // ViewModelStore. That is intentional: any ViewModel that survived partial initialization
        // is still valid, and the retry only needs to complete the remaining startup steps.
        try {
            pendingTicketId.value = extractTicketIdFromIntent(intent)
            val lockManager = AppLockManager(applicationContext)
            val serverConfigRepository = SecureServerConfigRepository(applicationContext)
            this.serverConfigRepository = serverConfigRepository
            notificationPreferences = NotificationPreferences(applicationContext)
            lockViewModel = ViewModelProvider(
                this,
                AppLockViewModel.factory(lockManager)
            )[AppLockViewModel::class.java]
            ticketsViewModel = ViewModelProvider(
                this,
                TicketsViewModel.factory(serverConfigRepository = serverConfigRepository)
            )[TicketsViewModel::class.java]
            ProcessLifecycleOwner.get().lifecycle.removeObserver(processLifecycleObserver)
            ProcessLifecycleOwner.get().lifecycle.addObserver(processLifecycleObserver)
        } catch (e: Exception) {
            Log.e(TAG, "Startup initialization failed — ${e.javaClass.simpleName}", e)
            startupViewModel.reportError("The app failed to start. Please try again.")
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

@androidx.compose.runtime.Composable
private fun StartupErrorScreen(message: String, onRetry: () -> Unit) {
    Box(
        modifier = Modifier
            .fillMaxSize()
            .background(MaterialTheme.colorScheme.background)
            .padding(32.dp),
        contentAlignment = Alignment.Center
    ) {
        Column(
            horizontalAlignment = Alignment.CenterHorizontally,
            verticalArrangement = Arrangement.Center
        ) {
            Text(
                text = "Could not start the app",
                style = MaterialTheme.typography.titleLarge,
                textAlign = TextAlign.Center
            )
            Spacer(modifier = Modifier.height(16.dp))
            Text(
                text = message,
                style = MaterialTheme.typography.bodyMedium,
                textAlign = TextAlign.Center,
                color = MaterialTheme.colorScheme.onSurfaceVariant
            )
            Spacer(modifier = Modifier.height(24.dp))
            Button(onClick = onRetry) {
                Text("Retry")
            }
        }
    }
}
