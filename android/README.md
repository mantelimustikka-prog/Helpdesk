# WP HelpD Android scaffold

This directory contains the initial Android admin client scaffold for the WP HelpD plugin API.

## Included MVP pieces

- Retrofit/OkHttp network client for `/wp-json/helpdesk/v1/admin/`
- WordPress Application Password authentication over HTTPS
- Optional `X-WP-Nonce` header support for browser-style sessions
- `GET /auth/check` integration
- `GET /tickets` integration
- Ticket detail integration via `GET /tickets/{id}` with thread + attachments support (including `GET /tickets/{id}/messages` fallback for current plugin payloads)
- Ticket actions:
  - `POST /tickets/{id}/reply`
  - `POST /tickets/{id}/status`
  - `POST /tickets/{id}/note`
- In-app polling notifications:
  - `GET /notifications/since?since={timestamp}` — polls every 5 minutes for new tickets/replies
  - In-app dialog shown when new items are found
- Compose UI + `TicketsViewModel` for auth check, loading and error states, ticket list, ticket detail, and reply/status/note actions
- JVM unit tests for auth and ticket response mapping

## Not yet in scaffold MVP

- Ticket assignment/reassignment UI/actions
- New-ticket creation flow
- Attachment upload from reply composer

## Open in Android Studio

Open the `/android` directory as a standalone Gradle project.

## Notes

- Enter either the site root URL (`https://example.com`) or the full admin API base URL (`https://example.com/wp-json/helpdesk/v1/admin/`).
- The ticket DTO supports both the documented contract payload (`data` + `pagination`) and the current plugin scaffold payload (`items` + `page`/`per_page`) so the first app iteration can work while the backend evolves.
- Polling notifications require the user to be logged in; polling is cancelled on logout and resumed on login.
