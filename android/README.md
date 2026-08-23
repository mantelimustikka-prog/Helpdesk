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
- Compose UI + `TicketsViewModel` for auth check, loading and error states, ticket list, ticket detail, and reply/status/note actions
- JVM unit tests for auth and ticket response mapping

## Open in Android Studio

Open the `/android` directory as a standalone Gradle project.

## Notes

- Enter either the site root URL (`https://example.com`) or the full admin API base URL (`https://example.com/wp-json/helpdesk/v1/admin/`).
- The ticket DTO supports both the documented contract payload (`data` + `pagination`) and the current plugin scaffold payload (`items` + `page`/`per_page`) so the first app iteration can work while the backend evolves.

## Image assets

The `splash_background.png` (in `res/drawable/`) and the `ic_launcher*.png` files (in `res/mipmap-*/`) are **placeholder colour fills** committed as structural scaffolding.

To use the real dog photos:

1. **Splash / background** (`res/drawable/splash_background.png`): Replace with a high-resolution portrait photo (e.g. 1080 × 1920 px). The image is displayed with `ContentScale.Crop` so keep the dog's face centred. A 53 % black overlay (`0x88000000`) is applied automatically to keep text readable.
2. **App icon** (`res/mipmap-*/ic_launcher.png` and `ic_launcher_round.png`): Replace each density variant with the square dog-face crop at the correct size:
   - `mdpi` → 48 × 48 px
   - `hdpi` → 72 × 72 px
   - `xhdpi` → 96 × 96 px
   - `xxhdpi` → 144 × 144 px
   - `xxxhdpi` → 192 × 192 px
3. **Adaptive icon foreground/background** (`res/drawable/ic_launcher_foreground.png` / `ic_launcher_background.png`): Replace with 108 × 108 px crops for the adaptive-icon layer (used on Android 8+).

Android Studio's **Image Asset Studio** (right-click `res` → New → Image Asset) can generate all density variants from a single 512 px source automatically.

