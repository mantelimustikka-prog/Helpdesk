# WP HelpD Android App – Project Scaffold Plan

## 1. Project Identity

- **App name:** WP HelpD
- **Platform:** Android
- **Language:** Kotlin
- **UI:** Jetpack Compose
- **Architecture:** MVVM
- **Networking:** Retrofit
- **Secure storage:** DataStore + EncryptedSharedPreferences
- **Push notifications:** Firebase Cloud Messaging

## 2. Recommended Package Structure

```text
com.wphelpd
├── App.kt
├── MainActivity.kt
├── navigation/
│   ├── AppNavGraph.kt
│   ├── Routes.kt
│   └── Destinations.kt
├── core/
│   ├── security/
│   │   ├── PasswordHasher.kt
│   │   ├── SecureStorage.kt
│   │   └── AppLockManager.kt
│   ├── network/
│   │   ├── ApiClient.kt
│   │   ├── AuthInterceptor.kt
│   │   └── NetworkResult.kt
│   ├── ui/
│   │   ├── theme/
│   │   │   ├── Color.kt
│   │   │   ├── Theme.kt
│   │   │   └── Type.kt
│   │   └── components/
│   └── utils/
├── data/
│   ├── api/
│   │   ├── HelpdeskApi.kt
│   │   └── dto/
│   ├── repository/
│   │   ├── AuthRepository.kt
│   │   ├── TicketRepository.kt
│   │   └── SettingsRepository.kt
│   └── local/
│       ├── AppDatabase.kt
│       └── dao/
├── domain/
│   ├── model/
│   │   ├── Ticket.kt
│   │   ├── TicketMessage.kt
│   │   ├── Attachment.kt
│   │   └── User.kt
│   └── usecase/
│       ├── UnlockAppUseCase.kt
│       ├── LoadTicketsUseCase.kt
│       ├── ReplyTicketUseCase.kt
│       └── UpdateTicketStatusUseCase.kt
└── feature/
    ├── lock/
    │   ├── LockScreen.kt
    │   └── LockViewModel.kt
    ├── setup/
    │   ├── SetupScreen.kt
    │   └── SetupViewModel.kt
    ├── dashboard/
    │   ├── DashboardScreen.kt
    │   └── DashboardViewModel.kt
    ├── tickets/
    │   ├── TicketListScreen.kt
    │   ├── TicketDetailScreen.kt
    │   ├── ReplyScreen.kt
    │   ├── CreateTicketScreen.kt
    │   └── TicketsViewModel.kt
    └── settings/
        ├── SettingsScreen.kt
        └── SettingsViewModel.kt
```

## 3. Build Phases

### Phase 1 – Foundation
- Create Android project
- Set up Compose and navigation
- Create theme with blue, white, and black styling
- Implement secure local app lock

### Phase 2 – Backend Connection
- Add API client
- Add server setup screen
- Store WordPress site URL and application password securely
- Add connection test endpoint

### Phase 3 – Ticket Management
- Implement ticket list
- Implement ticket detail
- Implement reply and status update actions
- Add refresh and empty/error states

### Phase 4 – Notifications and Enhancements
- Register FCM token
- Handle push notifications
- Add create ticket flow
- Add attachment support

## 4. UI Theme Direction

The app should use:
- **Primary:** blue tones
- **Background:** white
- **Text / contrast:** black and dark gray
- **Style:** clean admin interface, high readability, simple action buttons

## 5. Required Starter Screens

- Lock screen
- First-run password creation screen
- Server setup/login screen
- Dashboard screen
- Ticket list screen
- Ticket detail screen
- Reply composer screen
- Settings screen

## 6. Data Models to Start With

- `Ticket`
- `TicketMessage`
- `Attachment`
- `AdminUser`
- `AuthSession`
- `HelpdeskSettings`

## 7. First Implementation Milestone

The first milestone should deliver:
- app lock
- server configuration
- login test
- ticket list fetch
- ticket detail view stub

## 8. Next Engineering Step

After this scaffold is approved, the next step is to implement the Android project skeleton and then wire it to the initial Helpdesk API endpoints.
