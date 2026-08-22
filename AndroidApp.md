# Android Admin App for WP Helpdesk

## Overview

This document defines the product direction for an Android companion app that lets admins and support staff manage tickets in the WP Helpdesk plugin from an Android device.

The app should be a secure, admin-focused client for customer service operations, with a mandatory local app password and direct communication with the Helpdesk REST API.

## Goals

- Provide a fast mobile interface for support agents and admins
- Allow secure access to tickets and customer conversations
- Support replying, status updates, notes, and new ticket creation
- Keep sensitive admin access protected by a local device password
- Integrate with the existing WP Helpdesk plugin backend

## Core Requirement: App Password Lock

The app must **always require a local password to open**.

### Password behavior

- On first launch, the user must create a password
- The password is stored securely on the device
- On every later launch, the app must ask for that password before showing app content
- The app must not open directly without the password
- Future options may include:
  - biometric unlock
  - password reset via admin action or reinstall
  - auto-lock after inactivity

## Backend Integration

The Android app will communicate with the WP Helpdesk plugin through its REST API.

From the repository README:

- Base admin API: `/wp-json/helpdesk/v1/admin/`
- Recommended auth: WordPress Application Passwords
- For browser-based requests: use `X-WP-Nonce`

### Recommended app-side approach

- Use HTTPS only
- Use Application Passwords or a dedicated admin token flow
- Keep app password and server credentials separate from the WordPress login where possible
- Store secrets securely with Android Keystore and encrypted storage

## Main Features

The app should support the daily work of a customer service admin.

### Ticket management

- View ticket list
- Filter tickets by status:
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
- Assign or reassign tickets
- View attachments
- Search tickets
- View customer information
- Create new tickets
- Receive push notifications for new tickets and replies

## Suggested App Screens

### 1. Lock Screen

- Create password on first launch
- Enter password on subsequent launches

### 2. Server Setup Screen

- WordPress site URL
- Admin username
- Application password or API token

### 3. Dashboard

- Ticket counts
- New tickets
- Open tickets
- Overdue tickets

### 4. Ticket List

- Search
- Filters
- Status chips
- Priority indicators

### 5. Ticket Detail

- Ticket metadata
- Conversation thread
- Attachments
- Actions:
  - reply
  - add note
  - change status
  - assign agent

### 6. Compose Reply

- Plain-text or markdown reply editor
- Optional attachment support

### 7. Settings

- Change app password
- Reconnect server
- Logout
- Notification preferences
- Biometric unlock toggle

## Architecture

A clean implementation can be organized into these layers:

### 1. Local app lock

- Handles the mandatory password gate
- Stores password hash securely
- Supports session timeout / auto-lock later

### 2. Authentication layer

- Manages connection to the WordPress admin API
- Stores server URL, username, and auth credentials
- Handles login/session state

### 3. Ticket API layer

- Fetch tickets
- Fetch a single ticket
- Post a reply
- Update ticket status
- Add internal notes
- Create a new ticket

### 4. UI layer

- Dashboard
- Ticket list
- Ticket detail
- Reply composer
- Settings

## Security Considerations

Because this is an admin app, security is critical.

### Local security

- Require local password every time the app opens
- Store password hash, not plain text
- Use salted hashing
- Support auto-lock after inactivity
- Clear sensitive data on logout

### Server security

- HTTPS only
- Application passwords or token-based auth
- Capability checks in plugin API
- Restrict admin endpoints to authorized users only
- Add audit logging for replies and status changes

## Helpdesk Plugin Support Needed

To support the app properly, the plugin should expose clean admin endpoints such as:

- `GET /admin/tickets`
- `GET /admin/tickets/{id}`
- `POST /admin/tickets/{id}/reply`
- `POST /admin/tickets/{id}/status`
- `POST /admin/tickets/{id}/note`
- `POST /admin/tickets`
- `GET /admin/users`
- `GET /admin/attachments`

## MVP Scope

The recommended first version of the app should include:

- App password lock on first launch
- Server connection setup
- Ticket list
- Ticket detail view
- Replying to tickets
- Status changes
- Push notifications

## Future Enhancements

Possible later additions include:

- Biometric login
- Offline caching
- Attachment uploads from camera and gallery
- Advanced filtering and sorting
- Agent assignment workflows
- Knowledge base shortcuts
- Multi-account support

## Notes

This document is the starting point for the Android admin app plan and can later be expanded into a formal technical specification, API contract, or implementation checklist.
