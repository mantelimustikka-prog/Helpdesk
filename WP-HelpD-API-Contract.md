# WP HelpD Helpdesk Plugin – Initial API Contract

## 1. Purpose

This document defines the initial REST API contract for the WP Helpdesk plugin so the WP HelpD Android app can manage customer service tickets securely and consistently.

The goal is to provide the smallest useful API surface needed for the first Android release.

## 2. API Principles

- All admin endpoints must require authenticated requests
- All endpoints must be served over HTTPS
- Use WordPress Application Passwords for Android app authentication in MVP
- Responses should be JSON
- Error responses should be consistent and descriptive
- The API should only expose capabilities allowed for the current user

## 3. Base Namespace

All endpoints below are expected to live under:

`/wp-json/helpdesk/v1/admin/`

## 4. Authentication

### 4.1 MVP authentication method

- WordPress Application Passwords
- Android app sends Basic Auth credentials over HTTPS

### 4.2 Optional browser support

- Use `X-WP-Nonce` for browser-based admin requests when needed

### 4.3 Auth checks

Every endpoint must validate:
- valid authentication
- required capabilities
- site context / multisite context if applicable

## 5. Core Resources

The initial API will work with these main resources:

- Ticket
- TicketMessage
- Attachment
- User / Agent
- Auth session check

## 6. Endpoint List

## 6.1 Authentication / Session

### GET `/auth/check`

Checks whether the current credentials are valid.

#### Response
```json
{
  "success": true,
  "user": {
    "id": 12,
    "name": "Admin User",
    "email": "admin@example.com",
    "roles": ["administrator"]
  }
}
```

#### Error responses
- `401 Unauthorized` – invalid credentials
- `403 Forbidden` – authenticated but not allowed

---

## 6.2 Ticket List

### GET `/tickets`

Returns a paginated list of tickets.

#### Query parameters
- `status` – optional filter
- `search` – optional text search
- `page` – page number
- `per_page` – items per page
- `assigned_to` – optional agent filter

#### Example response
```json
{
  "success": true,
  "data": [
    {
      "id": 101,
      "ticket_no": "HD-000101",
      "subject": "Login issue",
      "status": "open",
      "priority": "normal",
      "customer_name": "Jane Smith",
      "customer_email": "jane@example.com",
      "created_at": "2026-08-22T10:15:00Z",
      "updated_at": "2026-08-22T11:00:00Z",
      "message_count": 3,
      "last_message_excerpt": "I still cannot sign in..."
    }
  ],
  "pagination": {
    "page": 1,
    "per_page": 20,
    "total": 1,
    "total_pages": 1
  }
}
```

---

## 6.3 Ticket Detail

### GET `/tickets/{id}`

Returns a single ticket with conversation thread and metadata.

#### Example response
```json
{
  "success": true,
  "data": {
    "id": 101,
    "ticket_no": "HD-000101",
    "subject": "Login issue",
    "status": "open",
    "priority": "normal",
    "customer": {
      "id": 44,
      "name": "Jane Smith",
      "email": "jane@example.com"
    },
    "assigned_to": {
      "id": 12,
      "name": "Admin User"
    },
    "messages": [
      {
        "id": 9001,
        "author_type": "customer",
        "author_name": "Jane Smith",
        "body": "I cannot sign in.",
        "created_at": "2026-08-22T10:15:00Z"
      }
    ],
    "attachments": [
      {
        "id": 501,
        "name": "screenshot.png",
        "url": "https://example.com/uploads/screenshot.png",
        "mime_type": "image/png"
      }
    ]
  }
}
```

---

## 6.4 Reply to Ticket

### POST `/tickets/{id}/reply`

Adds a reply to an existing ticket.

#### Request body
```json
{
  "message": "Please reset your password and try again.",
  "internal_note": false
}
```

#### Optional fields
- `attachments` – file IDs or upload references if supported later
- `internal_note` – whether the message is internal only

#### Response
```json
{
  "success": true,
  "data": {
    "message_id": 9002,
    "ticket_id": 101,
    "created_at": "2026-08-22T11:15:00Z"
  }
}
```

---

## 6.5 Update Ticket Status

### POST `/tickets/{id}/status`

Updates ticket status.

#### Request body
```json
{
  "status": "pending"
}
```

#### Allowed values
- `new`
- `open`
- `pending`
- `resolved`
- `closed`

#### Response
```json
{
  "success": true,
  "data": {
    "ticket_id": 101,
    "status": "pending"
  }
}
```

---

## 6.6 Add Internal Note

### POST `/tickets/{id}/note`

Adds a private note to a ticket thread.

#### Request body
```json
{
  "note": "Waiting for customer to confirm the email address."
}
```

#### Response
```json
{
  "success": true,
  "data": {
    "note_id": 7001,
    "ticket_id": 101
  }
}
```

---

## 6.7 Create Ticket

### POST `/tickets`

Creates a new helpdesk ticket from the app.

#### Request body
```json
{
  "subject": "New support request",
  "message": "Customer issue details...",
  "customer_name": "Jane Smith",
  "customer_email": "jane@example.com",
  "priority": "normal"
}
```

#### Response
```json
{
  "success": true,
  "data": {
    "ticket_id": 102,
    "ticket_no": "HD-000102"
  }
}
```

---

## 6.8 Users / Agents

### GET `/users`

Returns a list of users or agents that can be assigned to tickets.

#### Response
```json
{
  "success": true,
  "data": [
    {
      "id": 12,
      "name": "Admin User"
    }
  ]
}
```

---

## 6.9 Attachments

### GET `/attachments/{id}`

Returns metadata or access information for a single attachment.

#### Response
```json
{
  "success": true,
  "data": {
    "id": 501,
    "name": "screenshot.png",
    "url": "https://example.com/uploads/screenshot.png",
    "mime_type": "image/png"
  }
}
```

## 7. Error Format

All endpoints should use a consistent error format.

```json
{
  "success": false,
  "error": {
    "code": "invalid_status",
    "message": "The provided status is not allowed."
  }
}
```

### Suggested HTTP statuses
- `400 Bad Request`
- `401 Unauthorized`
- `403 Forbidden`
- `404 Not Found`
- `422 Unprocessable Entity`
- `500 Internal Server Error`

## 8. Ticket Model Fields

Minimum ticket fields expected by the app:

- `id`
- `ticket_no`
- `subject`
- `status`
- `priority`
- `customer_name`
- `customer_email`
- `assigned_to`
- `created_at`
- `updated_at`
- `message_count`
- `last_message_excerpt`

## 9. Message Model Fields

Minimum message fields expected by the app:

- `id`
- `ticket_id`
- `author_type`
- `author_name`
- `body`
- `created_at`

## 10. Attachment Model Fields

Minimum attachment fields expected by the app:

- `id`
- `name`
- `url`
- `mime_type`

## 11. Implementation Notes for the Plugin

- Use capability checks before every protected action
- Prefer dedicated controller classes for each resource
- Keep response schemas stable once the Android app starts using them
- Add versioning later if breaking changes are needed
- Make ticket list queries efficient because mobile clients will refresh frequently

## 12. MVP Contract Summary

For the first Android release, the plugin should support:

- auth check
- ticket list
- ticket detail
- reply
- status update
- internal notes
- create ticket
- agent list

## 13. Next Step

Implement these endpoints in the Helpdesk plugin, then wire the Android app scaffold to this contract.
