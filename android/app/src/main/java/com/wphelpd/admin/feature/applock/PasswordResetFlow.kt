package com.wphelpd.admin.feature.applock

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material3.Button
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.input.PasswordVisualTransformation
import androidx.compose.ui.unit.dp
import com.wphelpd.admin.data.api.HelpdeskPublicApi
import com.wphelpd.admin.data.api.dto.RequestResetCodeRequestDto
import com.wphelpd.admin.data.api.dto.ResetPasswordRequestDto
import com.wphelpd.admin.data.api.dto.VerifyResetCodeRequestDto
import kotlinx.coroutines.launch

private enum class ResetStep { EMAIL, CODE, NEW_PASSWORD }

/**
 * Full password reset flow consisting of three sequential screens:
 * 1. Enter email → request reset code from backend
 * 2. Enter 8-digit code → verify with backend and receive a short-lived reset token
 * 3. Enter new password → submit to backend, new password stored locally on success
 *
 * @param siteUrl         The WordPress site URL used to build the API base URL.
 * @param hintEmail       Pre-filled email address (stored during setup), may be null.
 * @param onResetSuccess  Called with the new plain-text password when reset succeeds.
 *                        The caller is responsible for storing the new password locally.
 * @param onCancel        Called when the user cancels the flow at any step.
 */
@Composable
fun PasswordResetFlow(
    siteUrl: String,
    hintEmail: String?,
    onResetSuccess: (newPassword: String) -> Unit,
    onCancel: () -> Unit
) {
    var step by rememberSaveable { mutableStateOf(ResetStep.EMAIL) }
    var email by rememberSaveable { mutableStateOf(hintEmail ?: "") }
    var resetToken by rememberSaveable { mutableStateOf("") }

    when (step) {
        ResetStep.EMAIL -> {
            PasswordResetEmailScreen(
                email = email,
                onEmailChange = { email = it },
                onSubmit = { step = ResetStep.CODE },
                onCancel = onCancel,
                siteUrl = siteUrl
            )
        }

        ResetStep.CODE -> {
            PasswordResetCodeScreen(
                email = email,
                onSubmit = { code, token ->
                    resetToken = token
                    step = ResetStep.NEW_PASSWORD
                },
                onCancel = onCancel,
                siteUrl = siteUrl
            )
        }

        ResetStep.NEW_PASSWORD -> {
            PasswordResetNewPasswordScreen(
                email = email,
                resetToken = resetToken,
                onSubmit = { newPassword ->
                    onResetSuccess(newPassword)
                },
                onCancel = onCancel,
                siteUrl = siteUrl
            )
        }
    }
}

// -----------------------------------------------------------------------------
// Step 1: Enter email
// -----------------------------------------------------------------------------

@Composable
private fun PasswordResetEmailScreen(
    email: String,
    onEmailChange: (String) -> Unit,
    onSubmit: () -> Unit,
    onCancel: () -> Unit,
    siteUrl: String
) {
    var isLoading by rememberSaveable { mutableStateOf(false) }
    var errorMessage by rememberSaveable { mutableStateOf("") }
    val scope = rememberCoroutineScope()

    ResetScaffold(
        title = "Reset Password",
        subtitle = "Enter your email address to receive a reset code.",
        onCancel = onCancel
    ) {
        OutlinedTextField(
            value = email,
            onValueChange = onEmailChange,
            label = { Text("Email address") },
            keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Email),
            singleLine = true,
            modifier = Modifier.fillMaxWidth()
        )

        ErrorText(errorMessage)

        Spacer(modifier = Modifier.height(16.dp))

        Button(
            onClick = {
                val trimmed = email.trim()
                if (trimmed.isBlank()) {
                    errorMessage = "Email is required"
                    return@Button
                }
                if (!android.util.Patterns.EMAIL_ADDRESS.matcher(trimmed).matches()) {
                    errorMessage = "Invalid email address"
                    return@Button
                }
                errorMessage = ""
                isLoading = true
                scope.launch {
                    try {
                        val api = HelpdeskPublicApi.create(siteUrl)
                        val response = api.requestPasswordResetCode(
                            RequestResetCodeRequestDto(email = trimmed)
                        )
                        if (response.success) {
                            onSubmit()
                        } else {
                            errorMessage = response.error?.get("message") ?: "Failed to send reset code"
                        }
                    } catch (_: Exception) {
                        errorMessage = "Network error. Please check your connection."
                    } finally {
                        isLoading = false
                    }
                }
            },
            enabled = !isLoading,
            modifier = Modifier.fillMaxWidth()
        ) {
            if (isLoading) CircularProgressIndicator(
                modifier = Modifier.padding(end = 8.dp),
                strokeWidth = 2.dp,
                color = Color.White
            )
            Text("Send Reset Code")
        }
    }
}

// -----------------------------------------------------------------------------
// Step 2: Enter 8-digit code
// -----------------------------------------------------------------------------

@Composable
private fun PasswordResetCodeScreen(
    email: String,
    onSubmit: (code: String, resetToken: String) -> Unit,
    onCancel: () -> Unit,
    siteUrl: String
) {
    var code by rememberSaveable { mutableStateOf("") }
    var isLoading by rememberSaveable { mutableStateOf(false) }
    var errorMessage by rememberSaveable { mutableStateOf("") }
    val scope = rememberCoroutineScope()

    ResetScaffold(
        title = "Enter Reset Code",
        subtitle = "Check your email for an 8-digit code and enter it below.",
        onCancel = onCancel
    ) {
        OutlinedTextField(
            value = code,
            onValueChange = { if (it.length <= 8 && it.all { ch -> ch.isDigit() }) code = it },
            label = { Text("8-digit code") },
            keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number),
            singleLine = true,
            modifier = Modifier.fillMaxWidth()
        )

        ErrorText(errorMessage)

        Spacer(modifier = Modifier.height(16.dp))

        Button(
            onClick = {
                if (code.length != 8) {
                    errorMessage = "Please enter the complete 8-digit code"
                    return@Button
                }
                errorMessage = ""
                isLoading = true
                scope.launch {
                    try {
                        val api = HelpdeskPublicApi.create(siteUrl)
                        val response = api.verifyPasswordResetCode(
                            VerifyResetCodeRequestDto(email = email, code = code)
                        )
                        if (response.success && response.resetToken != null) {
                            onSubmit(code, response.resetToken)
                        } else {
                            errorMessage = response.error?.get("message") ?: "Invalid or expired code"
                        }
                    } catch (_: Exception) {
                        errorMessage = "Network error. Please check your connection."
                    } finally {
                        isLoading = false
                    }
                }
            },
            enabled = !isLoading,
            modifier = Modifier.fillMaxWidth()
        ) {
            if (isLoading) CircularProgressIndicator(
                modifier = Modifier.padding(end = 8.dp),
                strokeWidth = 2.dp,
                color = Color.White
            )
            Text("Verify Code")
        }
    }
}

// -----------------------------------------------------------------------------
// Step 3: Set new password
// -----------------------------------------------------------------------------

@Composable
private fun PasswordResetNewPasswordScreen(
    email: String,
    resetToken: String,
    onSubmit: (newPassword: String) -> Unit,
    onCancel: () -> Unit,
    siteUrl: String
) {
    var password by rememberSaveable { mutableStateOf("") }
    var confirm by rememberSaveable { mutableStateOf("") }
    var isLoading by rememberSaveable { mutableStateOf(false) }
    var errorMessage by rememberSaveable { mutableStateOf("") }
    val scope = rememberCoroutineScope()

    ResetScaffold(
        title = "Set New Password",
        subtitle = "Enter and confirm your new app password.",
        onCancel = onCancel
    ) {
        OutlinedTextField(
            value = password,
            onValueChange = { password = it },
            label = { Text("New password (min 6 characters)") },
            visualTransformation = PasswordVisualTransformation(),
            keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Password),
            singleLine = true,
            modifier = Modifier.fillMaxWidth()
        )

        Spacer(modifier = Modifier.height(8.dp))

        OutlinedTextField(
            value = confirm,
            onValueChange = { confirm = it },
            label = { Text("Confirm new password") },
            visualTransformation = PasswordVisualTransformation(),
            keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Password),
            singleLine = true,
            modifier = Modifier.fillMaxWidth()
        )

        ErrorText(errorMessage)

        Spacer(modifier = Modifier.height(16.dp))

        Button(
            onClick = {
                when {
                    password.length < 6 -> {
                        errorMessage = "Password must be at least 6 characters"
                        return@Button
                    }
                    password != confirm -> {
                        errorMessage = "Passwords do not match"
                        return@Button
                    }
                }
                errorMessage = ""
                isLoading = true
                scope.launch {
                    try {
                        val api = HelpdeskPublicApi.create(siteUrl)
                        val response = api.resetPassword(
                            ResetPasswordRequestDto(
                                resetToken = resetToken,
                                email = email,
                                newPassword = password
                            )
                        )
                        if (response.success) {
                            onSubmit(password)
                        } else {
                            errorMessage = response.error?.get("message") ?: "Failed to reset password"
                        }
                    } catch (_: Exception) {
                        errorMessage = "Network error. Please check your connection."
                    } finally {
                        isLoading = false
                    }
                }
            },
            enabled = !isLoading,
            modifier = Modifier.fillMaxWidth()
        ) {
            if (isLoading) CircularProgressIndicator(
                modifier = Modifier.padding(end = 8.dp),
                strokeWidth = 2.dp,
                color = Color.White
            )
            Text("Reset Password")
        }
    }
}

// -----------------------------------------------------------------------------
// Shared helpers
// -----------------------------------------------------------------------------

@Composable
private fun ResetScaffold(
    title: String,
    subtitle: String,
    onCancel: () -> Unit,
    content: @Composable () -> Unit
) {
    Box(
        modifier = Modifier
            .fillMaxSize()
            .background(MaterialTheme.colorScheme.background)
    ) {
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(24.dp),
            verticalArrangement = Arrangement.Center,
            horizontalAlignment = Alignment.CenterHorizontally
        ) {
            Text(title, style = MaterialTheme.typography.headlineSmall)
            Spacer(modifier = Modifier.height(8.dp))
            Text(subtitle, style = MaterialTheme.typography.bodyMedium)
            Spacer(modifier = Modifier.height(24.dp))

            content()

            Spacer(modifier = Modifier.height(8.dp))
            TextButton(onClick = onCancel) {
                Text("Cancel")
            }
        }
    }
}

@Composable
private fun ErrorText(message: String) {
    if (message.isNotBlank()) {
        Spacer(modifier = Modifier.height(8.dp))
        Text(
            text = message,
            color = MaterialTheme.colorScheme.error,
            style = MaterialTheme.typography.bodySmall
        )
    }
}
