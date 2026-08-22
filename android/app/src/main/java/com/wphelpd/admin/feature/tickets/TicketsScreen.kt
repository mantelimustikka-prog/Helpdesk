package com.wphelpd.admin.feature.tickets

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material3.Button
import androidx.compose.material3.Card
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.TopAppBar
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.input.PasswordVisualTransformation
import androidx.compose.ui.unit.dp
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import com.wphelpd.admin.domain.model.Ticket

@Composable
fun TicketsRoute(viewModel: TicketsViewModel) {
    val uiState by viewModel.uiState.collectAsStateWithLifecycle()
    TicketsScreen(
        uiState = uiState,
        onSiteUrlChange = viewModel::updateSiteUrl,
        onUsernameChange = viewModel::updateUsername,
        onApplicationPasswordChange = viewModel::updateApplicationPassword,
        onWpNonceChange = viewModel::updateWpNonce,
        onConnect = viewModel::connectAndLoadTickets,
        onRefresh = viewModel::refreshTickets
    )
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun TicketsScreen(
    uiState: TicketsUiState,
    onSiteUrlChange: (String) -> Unit,
    onUsernameChange: (String) -> Unit,
    onApplicationPasswordChange: (String) -> Unit,
    onWpNonceChange: (String) -> Unit,
    onConnect: () -> Unit,
    onRefresh: () -> Unit
) {
    Scaffold(
        topBar = {
            TopAppBar(title = { Text("WP HelpD Admin") })
        }
    ) { paddingValues ->
        LazyColumn(
            modifier = Modifier
                .fillMaxSize()
                .padding(paddingValues)
                .padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp)
        ) {
            item {
                ServerSetupCard(
                    uiState = uiState,
                    onSiteUrlChange = onSiteUrlChange,
                    onUsernameChange = onUsernameChange,
                    onApplicationPasswordChange = onApplicationPasswordChange,
                    onWpNonceChange = onWpNonceChange,
                    onConnect = onConnect,
                    onRefresh = onRefresh
                )
            }

            uiState.errorMessage?.let { message ->
                item {
                    Text(
                        text = message,
                        color = MaterialTheme.colorScheme.error,
                        style = MaterialTheme.typography.bodyMedium
                    )
                }
            }

            if (uiState.isLoading) {
                item {
                    Box(
                        modifier = Modifier.fillMaxWidth(),
                        contentAlignment = Alignment.Center
                    ) {
                        CircularProgressIndicator()
                    }
                }
            }

            uiState.currentUser?.let { currentUser ->
                item {
                    Card(modifier = Modifier.fillMaxWidth()) {
                        Column(modifier = Modifier.padding(16.dp)) {
                            Text(currentUser.name, style = MaterialTheme.typography.titleMedium)
                            Text(currentUser.email, style = MaterialTheme.typography.bodyMedium)
                            Text(
                                currentUser.roles.joinToString(prefix = "Roles: "),
                                style = MaterialTheme.typography.bodySmall
                            )
                        }
                    }
                }
            }

            if (!uiState.isLoading && uiState.currentUser != null && uiState.tickets.isEmpty()) {
                item {
                    Text("No tickets returned yet.")
                }
            }

            items(uiState.tickets, key = Ticket::id) { ticket ->
                TicketCard(ticket = ticket)
            }
        }
    }
}

@Composable
private fun ServerSetupCard(
    uiState: TicketsUiState,
    onSiteUrlChange: (String) -> Unit,
    onUsernameChange: (String) -> Unit,
    onApplicationPasswordChange: (String) -> Unit,
    onWpNonceChange: (String) -> Unit,
    onConnect: () -> Unit,
    onRefresh: () -> Unit
) {
    Card(modifier = Modifier.fillMaxWidth()) {
        Column(modifier = Modifier.padding(16.dp)) {
            Text("Connection", style = MaterialTheme.typography.titleMedium)
            Spacer(modifier = Modifier.height(12.dp))
            OutlinedTextField(
                value = uiState.siteUrl,
                onValueChange = onSiteUrlChange,
                modifier = Modifier.fillMaxWidth(),
                singleLine = true,
                label = { Text("WordPress site URL") },
                placeholder = { Text("https://example.com") }
            )
            Spacer(modifier = Modifier.height(8.dp))
            OutlinedTextField(
                value = uiState.username,
                onValueChange = onUsernameChange,
                modifier = Modifier.fillMaxWidth(),
                singleLine = true,
                label = { Text("Username") }
            )
            Spacer(modifier = Modifier.height(8.dp))
            OutlinedTextField(
                value = uiState.applicationPassword,
                onValueChange = onApplicationPasswordChange,
                modifier = Modifier.fillMaxWidth(),
                singleLine = true,
                visualTransformation = PasswordVisualTransformation(),
                label = { Text("Application password") }
            )
            Spacer(modifier = Modifier.height(8.dp))
            OutlinedTextField(
                value = uiState.wpNonce,
                onValueChange = onWpNonceChange,
                modifier = Modifier.fillMaxWidth(),
                singleLine = true,
                label = { Text("X-WP-Nonce (optional)") }
            )
            Spacer(modifier = Modifier.height(12.dp))
            Row(horizontalArrangement = Arrangement.spacedBy(12.dp)) {
                Button(onClick = onConnect, enabled = uiState.canSubmit) {
                    Text("Auth check + load tickets")
                }
                if (uiState.currentUser != null) {
                    TextButton(onClick = onRefresh, enabled = !uiState.isLoading) {
                        Text("Refresh")
                    }
                }
            }
        }
    }
}

@Composable
private fun TicketCard(ticket: Ticket) {
    Card(modifier = Modifier.fillMaxWidth()) {
        Column(modifier = Modifier.padding(16.dp)) {
            Text(
                text = "${ticket.ticketNo} · ${ticket.subject}",
                style = MaterialTheme.typography.titleMedium
            )
            Spacer(modifier = Modifier.height(4.dp))
            Text(
                text = listOfNotNull(ticket.status, ticket.priority, ticket.customerName)
                    .joinToString(separator = " • "),
                style = MaterialTheme.typography.bodyMedium
            )
            ticket.customerEmail?.let {
                Spacer(modifier = Modifier.height(4.dp))
                Text(it, style = MaterialTheme.typography.bodySmall)
            }
            ticket.lastMessageExcerpt?.let {
                Spacer(modifier = Modifier.height(8.dp))
                Text(it, style = MaterialTheme.typography.bodySmall)
            }
        }
    }
}
