package com.wphelpd.admin

import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.lifecycle.viewmodel.compose.viewModel
import com.wphelpd.admin.core.ui.theme.WpHelpdTheme
import com.wphelpd.admin.core.config.SecureServerConfigRepository
import com.wphelpd.admin.feature.applock.AppLockManager
import com.wphelpd.admin.feature.applock.AppLockViewModel
import com.wphelpd.admin.feature.applock.CreatePasswordScreen
import com.wphelpd.admin.feature.applock.UnlockScreen
import com.wphelpd.admin.feature.tickets.TicketsRoute
import com.wphelpd.admin.feature.tickets.TicketsViewModel
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier

class MainActivity : ComponentActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        val lockManager = AppLockManager(applicationContext)
        val serverConfigRepository = SecureServerConfigRepository(applicationContext)
        setContent {
            WpHelpdTheme {
                val lockViewModel: AppLockViewModel =
                    viewModel(factory = AppLockViewModel.factory(lockManager))
                val lockState = lockViewModel.uiState.collectAsStateWithLifecycle().value

                when {
                    lockState.isInitialising -> {
                        Box(modifier = Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                            CircularProgressIndicator()
                        }
                    }
                    lockState.isUnlocked -> {
                        val ticketsViewModel: TicketsViewModel =
                            viewModel(factory = TicketsViewModel.factory(serverConfigRepository = serverConfigRepository))
                        TicketsRoute(viewModel = ticketsViewModel)
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
        }
    }
}
