# WP HelpD Android App – Technical Implementation Plan

## 1. Purpose

WP HelpD is an Android companion app for the WP Helpdesk plugin. It allows admins and support staff to manage tickets on mobile devices securely.

The app must always require a local password to open, even if the user is already authenticated to the Helpdesk backend.

## 2. Product Goals

- Provide a fast mobile interface for customer support operations
- Secure access with a mandatory local app password
- Integrate with the Helpdesk plugin REST API
- Support ticket viewing, replies, status changes, and basic admin actions
- Deliver push notifications for new tickets and replies

## 3. Recommended Stack

- **Language:** Kotlin
- **UI:** Jetpack Compose
- **Architecture:** MVVM
- **Networking:** Retrofit + Kotlin serialization or Moshi
- **Local storage:** DataStore + EncryptedSharedPreferences
- **Background / notifications:** Firebase Cloud Messaging
- **Security:** Android Keystore for secrets

## 4. App Architecture

### 4.1 Layers

#### Presentation layer
- Jetpack Compose screens
- ViewModels for screen state
- Navigation between lock screen, login, ticket list, ticket detail, and settings

#### Domain layer
- Ticket operations
- Authentication/session logic
- App lock validation

#### Data layer
- Helpdesk API client
- Local secure storage
- Notification token registration
- Ticket cache, if added later

### 4.2 Core modules

- `auth`
  - local app lock
  - server login session
  - credential persistence

- `tickets`
  - list, detail, reply, update status

- `notifications`
  - FCM token handling
  - device registration

- `settings`
  - server URL
  - app password change
  - logout

## 5. App Flow

### 5.1 First launch
1. Show welcome screen
2. Prompt user to create local app password
3. Save password hash securely
4. Prompt user to enter WordPress site URL and application password
5. Test connection to the API
6. Land on dashboard/ticket list

### 5.2 Returning launch
1. Show app lock screen
2. Validate local app password
3. Restore saved session/server config
4. Open dashboard

### 5.3 Ticket work flow
1. User opens ticket list
2. Selects a ticket
3. Reads messages and attachments
4. Replies or updates status
5. App refreshes ticket state

## 6. Proposed Screen List

- Splash / bootstrap screen
- Local app lock screen
- First-run setup screen
- Server connection screen
- Dashboard screen
- Ticket list screen
- Ticket detail screen
- Reply composer screen
- Create ticket screen
- Settings screen

## 7. Security Model

### Local security
- The app password must be required on every app open
- Store only a salted hash of the local password
- Use encrypted storage for any sensitive data
- Implement auto-lock after inactivity

### Backend security
- Use HTTPS only
- Use WordPress Application Passwords for MVP
- Verify user capabilities in backend endpoints
- Avoid storing plain credentials

## 8. MVP Build Order

### Phase 1
- Project scaffold
- Navigation structure
- Local app lock
- Secure storage

### Phase 2
- Server setup/login screen
- API client and auth checks
- Ticket list endpoint integration

### Phase 3
- Ticket detail screen
- Reply and status update actions
- Pull-to-refresh and error handling

### Phase 4
- Push notifications
- Create ticket flow
- Attachment handling

## 9. Initial Deliverables

- Android project scaffold
- REST API contract for the Helpdesk plugin
- Base Compose UI screens
- Auth and local lock implementation plan
- Ticket list/detail data models

## 10. Open Questions

- Should the app support multiple WordPress sites?
- Should replies support attachments in v1?
- Should the app cache tickets offline?
- Should biometric unlock be included after the password gate?

## 11. Next Step

The best next step is to define the initial API contract for the Helpdesk plugin and then scaffold the Android project around that contract.
