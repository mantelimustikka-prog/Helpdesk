package com.wphelpd.admin.feature.tickets

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
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.PasswordVisualTransformation
import androidx.compose.ui.unit.dp
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import com.wphelpd.admin.domain.model.Ticket
import com.wphelpd.admin.domain.model.TicketAttachment
import com.wphelpd.admin.domain.model.TicketDetail
import com.wphelpd.admin.domain.model.TicketThreadEntry

private val allowedStatuses = listOf("new", "open", "pending", "resolved", "closed")

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
        onRefreshList = viewModel::refreshTickets,
        onTicketSelected = viewModel::selectTicket,
        onRefreshDetail = viewModel::refreshSelectedTicket,
        onReplySubmit = viewModel::submitReply,
        onStatusSubmit = viewModel::submitStatusUpdate,
        onNoteSubmit = viewModel::submitInternalNote
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
    onRefreshList: () -> Unit,
    onTicketSelected: (Int) -> Unit,
    onRefreshDetail: () -> Unit,
    onReplySubmit: (String) -> Unit,
    onStatusSubmit: (String) -> Unit,
    onNoteSubmit: (String) -> Unit
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
                    onRefresh = onRefreshList
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
                TicketCard(
                    ticket = ticket,
                    isSelected = ticket.id == uiState.selectedTicketId,
                    onClick = { onTicketSelected(ticket.id) }
                )
            }

            if (uiState.selectedTicketId != null) {
                item {
                    TicketDetailSection(
                        uiState = uiState,
                        onRefreshDetail = onRefreshDetail,
                        onReplySubmit = onReplySubmit,
                        onStatusSubmit = onStatusSubmit,
                        onNoteSubmit = onNoteSubmit
                    )
                }
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
private fun TicketCard(ticket: Ticket, isSelected: Boolean, onClick: () -> Unit) {
    val selectedColor = if (isSelected) {
        MaterialTheme.colorScheme.primary.copy(alpha = 0.08f)
    } else {
        MaterialTheme.colorScheme.surface
    }
    Card(
        modifier = Modifier
            .fillMaxWidth()
            .clickable(onClick = onClick)
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .background(selectedColor)
                .padding(16.dp)
        ) {
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
            Spacer(modifier = Modifier.height(8.dp))
            Text(
                if (isSelected) "Selected" else "Tap to open details",
                style = MaterialTheme.typography.labelMedium
            )
        }
    }
}

@Composable
private fun TicketDetailSection(
    uiState: TicketsUiState,
    onRefreshDetail: () -> Unit,
    onReplySubmit: (String) -> Unit,
    onStatusSubmit: (String) -> Unit,
    onNoteSubmit: (String) -> Unit
) {
    var replyDraft by remember(uiState.selectedTicketId) { mutableStateOf("") }
    var statusDraft by remember(uiState.selectedTicketId) { mutableStateOf(uiState.ticketDetail?.ticket?.status ?: "") }
    var noteDraft by remember(uiState.selectedTicketId) { mutableStateOf("") }

    Card(modifier = Modifier.fillMaxWidth()) {
        Column(modifier = Modifier.padding(16.dp)) {
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Text("Ticket detail", style = MaterialTheme.typography.titleMedium)
                TextButton(
                    onClick = onRefreshDetail,
                    enabled = !uiState.isDetailLoading && !uiState.isMutating
                ) {
                    Text("Refresh")
                }
            }

            if (uiState.isDetailLoading) {
                Spacer(modifier = Modifier.height(8.dp))
                CircularProgressIndicator()
            }

            uiState.detailErrorMessage?.let {
                Spacer(modifier = Modifier.height(8.dp))
                Text(it, color = MaterialTheme.colorScheme.error)
            }

            uiState.ticketDetail?.let { detail ->
                TicketMetadata(detail)

                Spacer(modifier = Modifier.height(12.dp))
                Text("Attachments", style = MaterialTheme.typography.titleSmall)
                if (detail.attachments.isEmpty()) {
                    Text("No attachments.", style = MaterialTheme.typography.bodySmall)
                } else {
                    detail.attachments.forEach { attachment ->
                        AttachmentRow(attachment)
                    }
                }

                Spacer(modifier = Modifier.height(12.dp))
                Text("Conversation", style = MaterialTheme.typography.titleSmall)
                if (detail.thread.isEmpty()) {
                    Text("No messages yet.", style = MaterialTheme.typography.bodySmall)
                } else {
                    detail.thread.forEach { entry ->
                        ThreadEntryCard(entry)
                    }
                }

                Spacer(modifier = Modifier.height(12.dp))
                Text("Reply", style = MaterialTheme.typography.titleSmall)
                OutlinedTextField(
                    value = replyDraft,
                    onValueChange = { replyDraft = it },
                    modifier = Modifier.fillMaxWidth(),
                    label = { Text("Reply message") }
                )
                Spacer(modifier = Modifier.height(8.dp))
                Button(
                    onClick = {
                        onReplySubmit(replyDraft)
                        replyDraft = ""
                    },
                    enabled = !uiState.isMutating
                ) {
                    Text("Send reply")
                }

                Spacer(modifier = Modifier.height(12.dp))
                Text("Status update", style = MaterialTheme.typography.titleSmall)
                OutlinedTextField(
                    value = statusDraft,
                    onValueChange = { statusDraft = it },
                    modifier = Modifier.fillMaxWidth(),
                    singleLine = true,
                    label = { Text("Status") }
                )
                Text(
                    "Allowed: ${allowedStatuses.joinToString()}",
                    style = MaterialTheme.typography.bodySmall
                )
                Spacer(modifier = Modifier.height(8.dp))
                Button(
                    onClick = { onStatusSubmit(statusDraft.ifBlank { detail.ticket.status }) },
                    enabled = !uiState.isMutating
                ) {
                    Text("Update status")
                }

                Spacer(modifier = Modifier.height(12.dp))
                Text("Internal note", style = MaterialTheme.typography.titleSmall)
                OutlinedTextField(
                    value = noteDraft,
                    onValueChange = { noteDraft = it },
                    modifier = Modifier.fillMaxWidth(),
                    label = { Text("Private note") }
                )
                Spacer(modifier = Modifier.height(8.dp))
                Button(
                    onClick = {
                        onNoteSubmit(noteDraft)
                        noteDraft = ""
                    },
                    enabled = !uiState.isMutating
                ) {
                    Text("Add note")
                }
            }

            uiState.actionMessage?.let {
                Spacer(modifier = Modifier.height(12.dp))
                val messageColor = if (it.contains("failed", ignoreCase = true) || it.contains("required", ignoreCase = true)) {
                    MaterialTheme.colorScheme.error
                } else {
                    MaterialTheme.colorScheme.primary
                }
                Text(it, color = messageColor, fontWeight = FontWeight.Medium)
            }
        }
    }
}

@Composable
private fun TicketMetadata(detail: TicketDetail) {
    Spacer(modifier = Modifier.height(8.dp))
    Text("${detail.ticket.ticketNo} · ${detail.ticket.subject}", style = MaterialTheme.typography.titleSmall)
    Text(
        listOfNotNull(detail.ticket.status, detail.ticket.priority, detail.ticket.customerName).joinToString(" • "),
        style = MaterialTheme.typography.bodyMedium
    )
    detail.ticket.customerEmail?.let {
        Text(it, style = MaterialTheme.typography.bodySmall)
    }
    detail.assignedToName?.let {
        Text("Assigned to: $it", style = MaterialTheme.typography.bodySmall)
    }
}

@Composable
private fun AttachmentRow(attachment: TicketAttachment) {
    Spacer(modifier = Modifier.height(4.dp))
    Text(
        text = "• ${attachment.name} (${attachment.mimeType ?: "file"})",
        style = MaterialTheme.typography.bodySmall
    )
    Text(text = attachment.url, style = MaterialTheme.typography.bodySmall)
}

@Composable
private fun ThreadEntryCard(entry: TicketThreadEntry) {
    Spacer(modifier = Modifier.height(8.dp))
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
                if (entry.isInternal) {
                    append("Internal note · ")
                }
                append(entry.authorType.replaceFirstChar { it.uppercase() })
                entry.authorName?.let { append(" · ").append(it) }
            }
            Text(heading, style = MaterialTheme.typography.labelMedium)
            entry.createdAt?.let {
                Text(it, style = MaterialTheme.typography.labelSmall)
            }
            Spacer(modifier = Modifier.height(4.dp))
            Text(entry.body, style = MaterialTheme.typography.bodyMedium)
        }
    }
}
