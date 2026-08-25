package com.wphelpd.admin.feature.notifications

import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.width
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Button
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import kotlinx.coroutines.delay

/**
 * In-app dialog shown when new tickets or replies are found by the polling service.
 *
 * Auto-dismisses after [AUTO_DISMISS_MS] milliseconds if the user does not interact.
 *
 * @param newTicketCount Number of new tickets found.
 * @param newReplyCount  Number of new replies found.
 * @param onView         Called when the user taps "View" — should navigate to the tickets list.
 * @param onDismiss      Called when the dialog is dismissed (either by user or auto-dismiss).
 */
@Composable
fun NotificationDialog(
    newTicketCount: Int,
    newReplyCount: Int,
    onView: () -> Unit,
    onDismiss: () -> Unit
) {
    LaunchedEffect(Unit) {
        delay(AUTO_DISMISS_MS)
        onDismiss()
    }

    AlertDialog(
        onDismissRequest = onDismiss,
        title = {
            Text(text = "\uD83D\uDCEC New Tickets & Replies")
        },
        text = {
            Column(modifier = Modifier.fillMaxWidth()) {
                if (newTicketCount > 0) {
                    Text(
                        text = "• $newTicketCount new ticket${if (newTicketCount > 1) "s" else ""}",
                        modifier = Modifier.padding(bottom = 4.dp)
                    )
                }
                if (newReplyCount > 0) {
                    Text(
                        text = "• $newReplyCount new repl${if (newReplyCount > 1) "ies" else "y"}",
                        modifier = Modifier.padding(bottom = 4.dp)
                    )
                }
                Spacer(modifier = Modifier.height(8.dp))
            }
        },
        confirmButton = {
            Row {
                Button(onClick = {
                    onDismiss()
                    onView()
                }) {
                    Text("View")
                }
                Spacer(modifier = Modifier.width(8.dp))
                TextButton(onClick = onDismiss) {
                    Text("Dismiss")
                }
            }
        }
    )
}

private const val AUTO_DISMISS_MS = 10_000L
