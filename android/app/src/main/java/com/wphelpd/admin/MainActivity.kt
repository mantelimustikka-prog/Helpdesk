package com.wphelpd.admin

import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.lifecycle.viewmodel.compose.viewModel
import com.wphelpd.admin.core.ui.theme.WpHelpdTheme
import com.wphelpd.admin.feature.tickets.TicketsRoute
import com.wphelpd.admin.feature.tickets.TicketsViewModel

class MainActivity : ComponentActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContent {
            WpHelpdTheme {
                val ticketsViewModel: TicketsViewModel = viewModel(factory = TicketsViewModel.factory())
                TicketsRoute(viewModel = ticketsViewModel)
            }
        }
    }
}
