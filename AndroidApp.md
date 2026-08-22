# Android Admin App for WP Helpdesk

This document captures the product direction and implementation notes for the Android admin companion app for the WP Helpdesk plugin.

## Core requirement

The app should **always require a local app password to open**.

### Password behavior

- On **first launch**, the user must **create a password**.
- That password is stored securely on the device.
- On every later launch, the app must ask for that password before showing anything.
- The app should **not** open directly without the password.
- Later enhancements can include:
  - fingerprint/biometric unlock
  - password reset via admin action or reinstall
  - auto-lock after inactivity

## What the app should do

The app will act as an **admin companion app** for the WP Helpdesk plugin and let support staff manage tickets from Android.

### Main features

- View ticket list
- Filter by status:
  - new
  - open
  - pending
  - resolved
  - closed
- Open ticket details
- Read customer messages
- Reply to tickets
- Add internal notes
- Change ticket status
- Assign/reassign tickets
- View attachments
- Create new tickets
- Search tickets
- View customer info
- Push notifications for new tickets and replies

## Backend communication

The app should talk to the Helpdesk plugin through its REST API.

From the repository README:

- Base admin API: `/wp-json/helpdesk/v1/admin/`
- Recommended auth: WordPress Application Passwords
- For browser-based requests: send `X-WP-Nonce`

For the Android app, the preferred approach is:

- use HTTPS
- use Application Passwords or a dedicated admin token flow
- keep app password and server credentials separate from WordPress login if possible
- store tokens securely with Android Keystore / encrypted storage

## Suggested app architecture

### App layers

1. **Local app lock**
   - device password on open
   - encrypted secure storage

2. **Authentication layer**
   - connect to WordPress admin API
   - save server URL, username, app password/token

3. **Ticket API layer**
   - fetch tickets
   - fetch single ticket
   - post reply
   - update status
   - add note
   - create ticket

4. **UI layer**
   - dashboard
   - ticket list
   - ticket detail
   - reply screen
   - settings

## Suggested screens

### 1. App lock screen

- create password on first run
- enter password on subsequent runs

### 2. Server setup screen

- WordPress site URL
- admin username
- application password / API token

### 3. Dashboard

- ticket counts
- new tickets
- open tickets
- overdue tickets

### 4. Ticket list

- search
- filters
- status chips
- priority indicators

### 5. Ticket detail

- ticket metadata
- conversation thread
- attachments
- actions:
  - reply
  - add note
  - change status
  - assign agent

### 6. Compose reply

- rich plain-text or markdown reply
- optional attachment

### 7. Settings

- app password change
- reconnect server
- logout
- notification preferences
- biometric unlock toggle

## Recommended tech stack

For Android, the suggested stack is:

- Kotlin
- Jetpack Compose
- ViewModel + StateFlow
- Retrofit for API calls
- Room for offline cache if needed
- EncryptedSharedPreferences or DataStore + encryption
- Android Keystore for secrets
- Firebase Cloud Messaging for push alerts

## Security design

Because this is an admin app, security matters a lot.

### Local security

- local password required every time app opens
- store password hash, not plain text
- use salted hashing
- add timeout auto-lock
- clear sensitive data on logout

### Server security

- HTTPS only
- application passwords or token-based auth
- role/capability checks in plugin API
- restrict admin endpoints to authorized users only
- audit logs for replies and status changes

## Helpdesk plugin work needed

To support the app properly, the plugin should expose clean admin endpoints like:

- `GET /admin/tickets`
- `GET /admin/tickets/{id}`
- `POST /admin/tickets/{id}/reply`
- `POST /admin/tickets/{id}/status`
- `POST /admin/tickets/{id}/note`
- `POST /admin/tickets`
- `GET /admin/users`
- `GET /admin/attachments`

## Best next step

The recommended MVP is:

- app lock password on first launch
- login to Helpdesk server
- ticket list
- ticket detail
- reply
- status change
- push notifications

## Notes

This document is intended as the starting point for the Android admin app plan and can be expanded into a full specification, API contract, or project scaffold.
