package com.wphelpd.admin.feature.tickets

import androidx.activity.compose.BackHandler
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
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
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.ArrowBack
import androidx.compose.material3.Button
import androidx.compose.material3.Card
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
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
import com.wphelpd.admin.domain.model.TicketAttachment
import com.wphelpd.admin.domain.model.TicketDetail
import com.wphelpd.admin.domain.model.TicketThreadEntry

@Composable
fun TicketsRoute(viewModel: TicketsViewModel) {
    val uiState by viewModel.uiState.collectAsStateWithLifecycle()

    BackHandler(enabled = uiState.selectedTicketId != null) {
        viewModel.clearSelectedTicket()
    }

    TicketsScreen(
        uiState = uiState,
        onSiteUrlChange = viewModel::updateSiteUrl,
        onUsernameChange = viewModel::updateUsername,
        onApplicationPasswordChange = viewModel::updateApplicationPassword,
        onWpNonceChange = viewModel::updateWpNonce,
        onConnect = viewModel::connectAndLoadTickets,
        onRefreshList = viewModel::refreshTickets,
        onTicketSelected = viewModel::selectTicket,
        onBack = viewModel::clearSelectedTicket,
        onRefreshDetail = viewModel::refreshSelectedTicket
    )
}

@Composable
fun TicketsScreen(
    uiState: TicketsUiState,
    onSiteUrlChange: (String) -> Unit,
    onUsernameChange: (String) -> Unit,
    onApplicationPasswordChange: (String) -> Unit,
    onWpNonceChange: (String) -> Unit,
    onConnect: () -> Unit,
    onRefreshList: () -> Unit,
    onTicketSelected: (Int) -> Unit,
    onBack: () -> Unit,
    onRefreshDetail: () -> Unit
) {
    when {
        uiState.currentUser == null || uiState.requiresSetup -> {
            ServerSetupScreen(
                uiState = uiState,
                onSiteUrlChange = onSiteUrlChange,
                onUsernameChange = onUsernameChange,
                onApplicationPasswordChange = onApplicationPasswordChange,
                onWpNonceChange = onWpNonceChange,
                onConnect = onConnect
            )
        }
        uiState.selectedTicketId != null -> {
            TicketDetailScreen(
                uiState = uiState,
                onBack = onBack,
                onRefreshDetail = onRefreshDetail
            )
        }
        else -> {
            TicketListScreen(
                uiState = uiState,
                onRefreshList = onRefreshList,
                onTicketSelected = onTicketSelected
            )
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun ServerSetupScreen(
    uiState: TicketsUiState,
    onSiteUrlChange: (String) -> Unit,
    onUsernameChange: (String) -> Unit,
    onApplicationPasswordChange: (String) -> Unit,
    onWpNonceChange: (String) -> Unit,
    onConnect: () -> Unit
) {
    Scaffold(
        topBar = { TopAppBar(title = { Text("WP HelpD — Connect") }) }
    ) { paddingValues ->
        LazyColumn(
            modifier = Modifier
                .fillMaxSize()
                .padding(paddingValues)
                .padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp)
        ) {
            item {
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
                        Button(onClick = onConnect, enabled = uiState.canSubmit) {
                            Text("Connect")
                        }
                    }
                }
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
                    Box(modifier = Modifier.fillMaxWidth(), contentAlignment = Alignment.Center) {
                        CircularProgressIndicator()
                    }
                }
            }
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun TicketListScreen(
    uiState: TicketsUiState,
    onRefreshList: () -> Unit,
    onTicketSelected: (Int) -> Unit
) {
    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text("Tickets") },
                actions = {
                    TextButton(onClick = onRefreshList, enabled = !uiState.isLoading) {
                        Text("Refresh")
                    }
                }
            )
        }
    ) { paddingValues ->
        LazyColumn(
            modifier = Modifier
                .fillMaxSize()
                .padding(paddingValues)
                .padding(horizontal = 16.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp)
        ) {
            uiState.currentUser?.let { currentUser ->
                item {
                    Spacer(modifier = Modifier.height(8.dp))
                    Card(modifier = Modifier.fillMaxWidth()) {
                        Column(modifier = Modifier.padding(12.dp)) {
                            Text(currentUser.name, style = MaterialTheme.typography.titleSmall)
                            Text(currentUser.email, style = MaterialTheme.typography.bodySmall)
                        }
                    }
                }
            }

            uiState.errorMessage?.let { message ->
                item {
                    Text(
                        text = message,
                        color = MaterialTheme.colorScheme.error,
                        style = MaterialTheme.typography.bodyMedium,
                        modifier = Modifier.padding(top = 8.dp)
                    )
                }
            }

            if (uiState.isLoading) {
                item {
                    Box(modifier = Modifier.fillMaxWidth(), contentAlignment = Alignment.Center) {
                        CircularProgressIndicator(modifier = Modifier.padding(16.dp))
                    }
                }
            }

            if (!uiState.isLoading && uiState.tickets.isEmpty() && uiState.errorMessage == null) {
                item {
                    Text(
                        text = "No tickets found.",
                        style = MaterialTheme.typography.bodyMedium,
                        modifier = Modifier.padding(top = 8.dp)
                    )
                }
            }

            items(uiState.tickets, key = Ticket::id) { ticket ->
                TicketCard(ticket = ticket, onClick = { onTicketSelected(ticket.id) })
            }

            item { Spacer(modifier = Modifier.height(8.dp)) }
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun TicketDetailScreen(
    uiState: TicketsUiState,
    onBack: () -> Unit,
    onRefreshDetail: () -> Unit
) {
    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text(uiState.ticketDetail?.ticket?.subject ?: "Ticket detail") },
                navigationIcon = {
                    IconButton(onClick = onBack) {
                        Icon(
                            imageVector = Icons.Filled.ArrowBack,
                            contentDescription = "Back"
                        )
                    }
                },
                actions = {
                    TextButton(onClick = onRefreshDetail, enabled = !uiState.isDetailLoading) {
                        Text("Refresh")
                    }
                }
            )
        }
    ) { paddingValues ->
        LazyColumn(
            modifier = Modifier
                .fillMaxSize()
                .padding(paddingValues)
                .padding(horizontal = 16.dp),
            verticalArrangement = Arrangement.spacedBy(8.dp)
        ) {
            if (uiState.isDetailLoading) {
                item {
                    Box(modifier = Modifier.fillMaxWidth(), contentAlignment = Alignment.Center) {
                        CircularProgressIndicator(modifier = Modifier.padding(16.dp))
                    }
                }
            }

            uiState.detailErrorMessage?.let { error ->
                item {
                    Text(
                        text = error,
                        color = MaterialTheme.colorScheme.error,
                        style = MaterialTheme.typography.bodyMedium,
                        modifier = Modifier.padding(top = 8.dp)
                    )
                    Spacer(modifier = Modifier.height(4.dp))
                    TextButton(onClick = onRefreshDetail) {
                        Text("Retry")
                    }
                }
            }

            uiState.ticketDetail?.let { detail ->
                item {
                    Spacer(modifier = Modifier.height(4.dp))
                    TicketMetadata(detail)
                }

                item {
                    AttachmentSection(detail.attachments)
                }

                item {
                    ConversationSection(detail.thread)
                }
            }

            if (
                !uiState.isDetailLoading &&
                uiState.ticketDetail == null &&
                uiState.detailErrorMessage == null
            ) {
                item {
                    Text(
                        text = "Loading ticket…",
                        style = MaterialTheme.typography.bodySmall,
                        modifier = Modifier.padding(top = 8.dp)
                    )
                }
            }

            item { Spacer(modifier = Modifier.height(8.dp)) }
        }
    }
}

@Composable
private fun TicketCard(ticket: Ticket, onClick: () -> Unit) {
    Card(
        modifier = Modifier
            .fillMaxWidth()
            .clickable(onClick = onClick)
    ) {
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

@Composable
private fun TicketMetadata(detail: TicketDetail) {
    Card(modifier = Modifier.fillMaxWidth()) {
        Column(modifier = Modifier.padding(16.dp)) {
            Text("Details", style = MaterialTheme.typography.titleSmall)
            Spacer(modifier = Modifier.height(8.dp))
            MetadataLine("Ticket number", detail.ticket.ticketNo)
            MetadataLine("Status", detail.ticket.status)
            MetadataLine("Priority", detail.ticket.priority ?: "—")
            MetadataLine("Customer", detail.ticket.customerName ?: "—")
            MetadataLine("Customer email", detail.ticket.customerEmail ?: "—")
            MetadataLine("Assigned agent", detail.assignedToName ?: "—")
            MetadataLine("Created", detail.ticket.createdAt ?: "—")
            MetadataLine("Updated", detail.ticket.updatedAt ?: "—")
            MetadataLine("Messages", detail.ticket.messageCount.toString())
        }
    }
}

@Composable
private fun MetadataLine(label: String, value: String) {
    Row(modifier = Modifier.fillMaxWidth()) {
        Text(
            text = "$label: ",
            style = MaterialTheme.typography.bodyMedium,
            color = MaterialTheme.colorScheme.onSurfaceVariant
        )
        Text(text = value, style = MaterialTheme.typography.bodyMedium)
    }
}

@Composable
private fun AttachmentSection(attachments: List<TicketAttachment>) {
    if (attachments.isEmpty()) return
    Card(modifier = Modifier.fillMaxWidth()) {
        Column(modifier = Modifier.padding(16.dp)) {
            Text("Attachments", style = MaterialTheme.typography.titleSmall)
            Spacer(modifier = Modifier.height(8.dp))
            attachments.forEach { attachment ->
                Text(
                    text = "• ${attachment.name} (${attachment.mimeType ?: "file"})",
                    style = MaterialTheme.typography.bodySmall
                )
                Text(text = attachment.url, style = MaterialTheme.typography.bodySmall)
            }
        }
    }
}

@Composable
private fun ConversationSection(thread: List<TicketThreadEntry>) {
    Card(modifier = Modifier.fillMaxWidth()) {
        Column(modifier = Modifier.padding(16.dp)) {
            Text("Conversation", style = MaterialTheme.typography.titleSmall)
            if (thread.isEmpty()) {
                Spacer(modifier = Modifier.height(8.dp))
                Text("No messages yet.", style = MaterialTheme.typography.bodySmall)
            } else {
                thread.forEach { entry ->
                    Spacer(modifier = Modifier.height(8.dp))
                    ThreadEntryCard(entry)
                }
            }
        }
    }
}

@Composable
private fun ThreadEntryCard(entry: TicketThreadEntry) {
    val backgroundColor = if (entry.isInternal) {
        MaterialTheme.colorScheme.tertiaryContainer
    } else {
        MaterialTheme.colorScheme.surfaceVariant
    }
    Card(modifier = Modifier.fillMaxWidth()) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .background(backgroundColor)
                .padding(12.dp)
        ) {
            val heading = buildString {
                if (entry.isInternal) append("Internal note · ")
                append(entry.authorType.replaceFirstChar { it.uppercase() })
                entry.authorName?.let { append(" · ").append(it) }
            }
            Text(heading, style = MaterialTheme.typography.labelMedium)
            entry.createdAt?.let { Text(it, style = MaterialTheme.typography.labelSmall) }
            Spacer(modifier = Modifier.height(4.dp))
            Text(entry.body, style = MaterialTheme.typography.bodyMedium)
        }
    }
}

