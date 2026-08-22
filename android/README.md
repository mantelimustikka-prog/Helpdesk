# WP HelpD Android scaffold

This directory contains the initial Android admin client scaffold for the WP HelpD plugin API.

## Included MVP pieces

- Retrofit/OkHttp network client for `/wp-json/helpdesk/v1/admin/`
- WordPress Application Password authentication over HTTPS
- Optional `X-WP-Nonce` header support for browser-style sessions
- `GET /auth/check` integration
- `GET /tickets` integration
- Compose UI + `TicketsViewModel` for auth check, loading state, error state, and ticket list rendering
- JVM unit tests for auth and ticket response mapping

## Open in Android Studio

Open the `/android` directory as a standalone Gradle project.

## Notes

- Enter either the site root URL (`https://example.com`) or the full admin API base URL (`https://example.com/wp-json/helpdesk/v1/admin/`).
- The ticket DTO supports both the documented contract payload (`data` + `pagination`) and the current plugin scaffold payload (`items` + `page`/`per_page`) so the first app iteration can work while the backend evolves.
