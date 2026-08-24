package com.wphelpd.admin.feature.tickets
import androidx.activity.compose.BackHandler
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.rememberScrollState
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
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
import androidx.compose.runtime.SideEffect
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.input.PasswordVisualTransformation
import androidx.compose.ui.unit.dp
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import com.wphelpd.admin.data.repository.HelpdeskRepository
import com.wphelpd.admin.domain.model.AppearanceColors
import com.wphelpd.admin.domain.model.Ticket
import com.wphelpd.admin.domain.model.TicketAttachment
import com.wphelpd.admin.domain.model.TicketDetail
import com.wphelpd.admin.domain.model.TicketThreadEntry
import com.wphelpd.admin.domain.model.statusLabel
import com.wphelpd.admin.domain.model.ticketStatusLabel

@Composable
fun TicketsRoute(
    viewModel: TicketsViewModel,
    onLogout: () -> Unit = {}
) {
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
        onRefreshDetail = viewModel::refreshSelectedTicket,
        onReplyTextChange = viewModel::updateReplyText,
        onSubmitReply = viewModel::submitReply,
        onStatusChange = viewModel::updateTicketStatus,
        onNoteTextChange = viewModel::updateNoteText,
        onSubmitNote = viewModel::submitNote,
        onLogout = onLogout
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
    onRefreshDetail: () -> Unit,
    onReplyTextChange: (String) -> Unit = {},
    onSubmitReply: () -> Unit = {},
    onStatusChange: (String) -> Unit = {},
    onNoteTextChange: (String) -> Unit = {},
    onSubmitNote: () -> Unit = {},
    onLogout: () -> Unit = {}
) {
    when {
        uiState.currentUser == null || uiState.requiresSetup -> {
            ServerSetupScreen(
                uiState = uiState,
                onSiteUrlChange = onSiteUrlChange,
                onUsernameChange = onUsernameChange,
                onApplicationPasswordChange = onApplicationPasswordChange,
                onWpNonceChange = onWpNonceChange,
                onConnect = onConnect,
                onLogout = onLogout
            )
        }
        uiState.selectedTicketId != null -> {
            TicketDetailScreen(
                uiState = uiState,
                onBack = onBack,
                onRefreshDetail = onRefreshDetail,
                onReplyTextChange = onReplyTextChange,
                onSubmitReply = onSubmitReply,
                onStatusChange = onStatusChange,
                onNoteTextChange = onNoteTextChange,
                onSubmitNote = onSubmitNote,
                onLogout = onLogout,
                appearanceColors = uiState.appearanceColors
            )
        }
        else -> {
            TicketListScreen(
                uiState = uiState,
                onRefreshList = onRefreshList,
                onTicketSelected = onTicketSelected,
                onLogout = onLogout,
                appearanceColors = uiState.appearanceColors
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
    onConnect: () -> Unit,
    onLogout: () -> Unit
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
                        Spacer(modifier = Modifier.height(8.dp))
                        TextButton(onClick = onLogout) {
                            Text("Clear session")
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
    onTicketSelected: (Int) -> Unit,
    onLogout: () -> Unit,
    appearanceColors: AppearanceColors = AppearanceColors.Empty
) {
    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text("Tickets") },
                actions = {
                    TextButton(onClick = onLogout) {
                        Text("Logout")
                    }
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
                TicketCard(ticket = ticket, onClick = { onTicketSelected(ticket.id) }, appearanceColors = appearanceColors)
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
    onRefreshDetail: () -> Unit,
    onReplyTextChange: (String) -> Unit,
    onSubmitReply: () -> Unit,
    onStatusChange: (String) -> Unit,
    onNoteTextChange: (String) -> Unit,
    onSubmitNote: () -> Unit,
    onLogout: () -> Unit,
    appearanceColors: AppearanceColors = AppearanceColors.Empty
) {
    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text(uiState.ticketDetail?.ticket?.subject ?: "Ticket detail") },
                navigationIcon = {
                    IconButton(onClick = onBack) {
                        Icon(
                            imageVector = Icons.AutoMirrored.Filled.ArrowBack,
                            contentDescription = "Back"
                        )
                    }
                },
                actions = {
                    TextButton(onClick = onLogout) {
                        Text("Logout")
                    }
                    TextButton(
                        onClick = onRefreshDetail,
                        enabled = !uiState.isDetailLoading && !uiState.isDetailActionInProgress
                    ) {
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
                    TextButton(
                        onClick = onRefreshDetail,
                        enabled = !uiState.isDetailLoading && !uiState.isDetailActionInProgress
                    ) {
                        Text("Retry")
                    }
                }
            }

            uiState.ticketDetail?.let { detail ->
                val areDetailActionsEnabled = !uiState.isDetailActionInProgress
                item {
                    Spacer(modifier = Modifier.height(4.dp))
                    TicketMetadata(detail, appearanceColors)
                }

                item {
                    AttachmentSection(detail.attachments)
                }

                item {
                    ConversationSection(detail.thread, appearanceColors)
                }

                item {
                    ReplyComposer(
                        text = uiState.replyText,
                        isEnabled = areDetailActionsEnabled,
                        isLoading = uiState.isReplying,
                        errorMessage = uiState.replyError,
                        onTextChange = onReplyTextChange,
                        onSubmit = onSubmitReply
                    )
                }

                item {
                    StatusActions(
                        currentStatus = detail.ticket.status,
                        isEnabled = areDetailActionsEnabled,
                        isLoading = uiState.isUpdatingStatus,
                        errorMessage = uiState.statusUpdateError,
                        onStatusChange = onStatusChange,
                        appearanceColors = appearanceColors
                    )
                }

                item {
                    NoteComposer(
                        text = uiState.noteText,
                        isEnabled = areDetailActionsEnabled,
                        isLoading = uiState.isAddingNote,
                        errorMessage = uiState.noteError,
                        onTextChange = onNoteTextChange,
                        onSubmit = onSubmitNote
                    )
                }
            }

            if (
                !uiState.isDetailLoading &&
                uiState.ticketDetail == null &&
                uiState.detailErrorMessage == null
            ) {
                item {
                    Text(
                        text = "No ticket data available.",
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
private fun TicketCard(ticket: Ticket, onClick: () -> Unit, appearanceColors: AppearanceColors = AppearanceColors.Empty) {
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
            val statusColor = appearanceColors.statusColor(ticket.status).toComposeColorOrNull()
            Row {
                Text(
                    text = ticket.statusLabel(),
                    style = MaterialTheme.typography.bodyMedium,
                    color = statusColor ?: Color.Unspecified
                )
                listOfNotNull(ticket.priority, ticket.customerName).forEach { value ->
                    Text(
                        text = " • $value",
                        style = MaterialTheme.typography.bodyMedium
                    )
                }
            }
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
private fun TicketMetadata(detail: TicketDetail, appearanceColors: AppearanceColors = AppearanceColors.Empty) {
    Card(modifier = Modifier.fillMaxWidth()) {
        Column(modifier = Modifier.padding(16.dp)) {
            Text("Details", style = MaterialTheme.typography.titleSmall)
            Spacer(modifier = Modifier.height(8.dp))
            MetadataLine("Ticket number", detail.ticket.ticketNo)
            val statusColor = appearanceColors.statusColor(detail.ticket.status).toComposeColorOrNull()
            MetadataLine("Status", detail.ticket.statusLabel(), valueColor = statusColor)
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
private fun MetadataLine(label: String, value: String, valueColor: Color? = null) {
    Row(modifier = Modifier.fillMaxWidth()) {
        Text(
            text = "$label: ",
            style = MaterialTheme.typography.bodyMedium,
            color = MaterialTheme.colorScheme.onSurfaceVariant
        )
        Text(
            text = value,
            style = MaterialTheme.typography.bodyMedium,
            color = valueColor ?: Color.Unspecified
        )
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
private fun ConversationSection(thread: List<TicketThreadEntry>, appearanceColors: AppearanceColors = AppearanceColors.Empty) {
    Card(modifier = Modifier.fillMaxWidth()) {
        Column(modifier = Modifier.padding(16.dp)) {
            Text("Conversation", style = MaterialTheme.typography.titleSmall)
            Spacer(modifier = Modifier.height(4.dp))
            Text(
                text = "TEMP DEBUG: thread size = ${thread.size}",
                style = MaterialTheme.typography.labelSmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant
            )
            if (thread.isNotEmpty()) {
                Spacer(modifier = Modifier.height(2.dp))
                Text(
                    text = "TEMP DEBUG: first author = ${thread.first().authorType}, preview = ${thread.first().body.take(40)}",
                    style = MaterialTheme.typography.labelSmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant
                )
            }
            if (thread.isEmpty()) {
                Spacer(modifier = Modifier.height(8.dp))
                Text("No messages yet.", style = MaterialTheme.typography.bodySmall)
            } else {
                thread.forEach { entry ->
                    Spacer(modifier = Modifier.height(8.dp))
                    ThreadEntryCard(entry, appearanceColors)
                }
            }
        }
    }
}

@Composable
private fun ThreadEntryCard(entry: TicketThreadEntry, appearanceColors: AppearanceColors = AppearanceColors.Empty) {
    val backgroundColor = if (entry.isInternal) {
        MaterialTheme.colorScheme.tertiaryContainer
    } else {
        MaterialTheme.colorScheme.surfaceVariant
    }
    val bodyTextColor = when {
        entry.isInternal -> Color.Unspecified
        entry.authorType.equals("agent", ignoreCase = true) ||
            entry.authorType.equals("admin", ignoreCase = true) ->
            appearanceColors.adminReplyColor.toComposeColorOrNull() ?: Color.Unspecified
        else ->
            appearanceColors.clientReplyColor.toComposeColorOrNull() ?: Color.Unspecified
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
            Text(entry.body, style = MaterialTheme.typography.bodyMedium, color = bodyTextColor)
        }
    }
}

@Composable
private fun ReplyComposer(
    text: String,
    isEnabled: Boolean,
    isLoading: Boolean,
    errorMessage: String?,
    onTextChange: (String) -> Unit,
    onSubmit: () -> Unit
) {
    val inputEnabled = isEnabled && !isLoading
    Card(modifier = Modifier.fillMaxWidth()) {
        Column(modifier = Modifier.padding(16.dp)) {
            Text("Reply", style = MaterialTheme.typography.titleSmall)
            Spacer(modifier = Modifier.height(8.dp))
            OutlinedTextField(
                value = text,
                onValueChange = onTextChange,
                modifier = Modifier.fillMaxWidth(),
                minLines = 3,
                label = { Text("Message") },
                enabled = inputEnabled
            )
            errorMessage?.let {
                Spacer(modifier = Modifier.height(4.dp))
                Text(it, color = MaterialTheme.colorScheme.error, style = MaterialTheme.typography.bodySmall)
            }
            Spacer(modifier = Modifier.height(8.dp))
            Row(verticalAlignment = Alignment.CenterVertically) {
                Button(onClick = onSubmit, enabled = inputEnabled && text.isNotBlank()) {
                    Text("Send reply")
                }
                if (isLoading) {
                    Spacer(modifier = Modifier.width(8.dp))
                    CircularProgressIndicator(modifier = Modifier.size(24.dp))
                }
            }
        }
    }
}

private val statusLabels: List<String> = HelpdeskRepository.statusOptions

@Composable
private fun StatusActions(
    currentStatus: String,
    isEnabled: Boolean,
    isLoading: Boolean,
    errorMessage: String?,
    onStatusChange: (String) -> Unit,
    appearanceColors: AppearanceColors = AppearanceColors.Empty
) {
    val actionsEnabled = isEnabled && !isLoading
    Card(modifier = Modifier.fillMaxWidth()) {
        Column(modifier = Modifier.padding(16.dp)) {
            Text("Change status", style = MaterialTheme.typography.titleSmall)
            errorMessage?.let {
                Spacer(modifier = Modifier.height(4.dp))
                Text(it, color = MaterialTheme.colorScheme.error, style = MaterialTheme.typography.bodySmall)
            }
            Spacer(modifier = Modifier.height(8.dp))
            Row(
                horizontalArrangement = Arrangement.spacedBy(8.dp),
                modifier = Modifier
                    .fillMaxWidth()
                    .horizontalScroll(rememberScrollState())
            ) {
                statusLabels.forEach { status ->
                    val isCurrent = status.equals(currentStatus, ignoreCase = true)
                    val statusColor = appearanceColors.statusColor(status).toComposeColorOrNull()
                    if (isCurrent) {
                        Button(
                            onClick = {},
                            enabled = false,
                            colors = ButtonDefaults.buttonColors(
                                disabledContainerColor = statusColor ?: MaterialTheme.colorScheme.surfaceVariant,
                                disabledContentColor = MaterialTheme.colorScheme.onSurface
                            )
                        ) {
                            Text(status.ticketStatusLabel())
                        }
                    } else {
                        TextButton(
                            onClick = { onStatusChange(status) },
                            enabled = actionsEnabled,
                            colors = ButtonDefaults.textButtonColors(
                                contentColor = statusColor ?: MaterialTheme.colorScheme.primary
                            )
                        ) {
                            Text(status.ticketStatusLabel())
                        }
                    }
                }
            }
            if (isLoading) {
                Spacer(modifier = Modifier.height(4.dp))
                CircularProgressIndicator(modifier = Modifier.height(24.dp))
            }
        }
    }
}

@Composable
private fun NoteComposer(
    text: String,
    isEnabled: Boolean,
    isLoading: Boolean,
    errorMessage: String?,
    onTextChange: (String) -> Unit,
    onSubmit: () -> Unit
) {
    val inputEnabled = isEnabled && !isLoading
    Card(modifier = Modifier.fillMaxWidth()) {
        Column(modifier = Modifier.padding(16.dp)) {
            Text("Internal note", style = MaterialTheme.typography.titleSmall)
            Spacer(modifier = Modifier.height(8.dp))
            OutlinedTextField(
                value = text,
                onValueChange = onTextChange,
                modifier = Modifier.fillMaxWidth(),
                minLines = 3,
                label = { Text("Note") },
                enabled = inputEnabled
            )
            errorMessage?.let {
                Spacer(modifier = Modifier.height(4.dp))
                Text(it, color = MaterialTheme.colorScheme.error, style = MaterialTheme.typography.bodySmall)
            }
            Spacer(modifier = Modifier.height(8.dp))
            Row(verticalAlignment = Alignment.CenterVertically) {
                Button(onClick = onSubmit, enabled = inputEnabled && text.isNotBlank()) {
                    Text("Add note")
                }
                if (isLoading) {
                    Spacer(modifier = Modifier.width(8.dp))
                    CircularProgressIndicator(modifier = Modifier.size(24.dp))
                }
            }
        }
    }
}

// Parses a CSS-style hex color string ("#rrggbb" or "#aarrggbb") into a Compose Color.
// The server (WordPress sanitize_hex_color) emits 6-character values; the 8-character branch
// assumes the optional alpha is in aarrggbb order (not rrggbbaa).
private fun String.toComposeColorOrNull(): Color? {
    val hex = this.trim()
    if (hex.isEmpty()) return null
    return try {
        val normalized = if (hex.startsWith("#")) hex.substring(1) else hex
        val argb = when (normalized.length) {
            6 -> "FF$normalized".toLong(16) // prepend opaque alpha → 0xFFRRGGBB
            8 -> normalized.toLong(16)      // caller supplies alpha as first two digits (aarrggbb)
            else -> return null
        }
        Color(
            red = argb.shr(16).and(0xFF).toInt(),
            green = argb.shr(8).and(0xFF).toInt(),
            blue = argb.and(0xFF).toInt(),
            alpha = argb.shr(24).and(0xFF).toInt()
        )
    } catch (_: NumberFormatException) {
        null
    }
}
