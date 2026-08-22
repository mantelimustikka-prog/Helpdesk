# WP Helpdesk

WP Helpdesk is a network-only WordPress multisite plugin scaffold for managing support tickets, topic flows, outbound notifications, attachments, and Android admin app access.

## Current scaffold scope

- Network admin menu with Dashboard, Tickets, Topics, and Settings pages
- Network-global custom tables using `$wpdb->base_prefix . 'hd_'`
- REST API namespace: `/wp-json/helpdesk/v1/`
- Email notification abstraction through `NotificationService`
- Push notification abstraction through FCM
- Attachment upload scaffolding for tickets and messages
- Standalone Android admin scaffold under `/android`

## Architecture

### Network activation

1. Copy the plugin into your multisite plugins directory.
2. Activate **WP Helpdesk** from **Network Admin → Plugins**.
3. Activation runs all migrations and creates the `wp_hd_*` network tables using the network base prefix.
4. Activation also seeds default `hd_*` network options and registers the custom helpdesk capabilities.

### CKEditor email header/footer

CKEditor 4.22.1 (GPL-2.0 OR LGPL-2.1 OR MPL-1.1) is **bundled** with the plugin under `assets/vendor/ckeditor/`. No CDN dependency or manual download is required.

To configure the email header and footer:

1. Go to **Network Admin → Helpdesk → Settings**.
2. Click the **Email Header & Footer** tab.
3. Use the rich-text editors to compose the HTML header and footer content.
4. Click **Save Settings**.

The plugin enqueues the bundled `assets/vendor/ckeditor/ckeditor.js` only on the Email Header & Footer tab. If JavaScript is unavailable the fields gracefully fall back to plain textareas.

### Email delivery

All outbound plugin email is sent through a single service:

`WPHelpdesk\Domain\Notification\NotificationService`

That service is the only code path that calls `wp_mail()`. Before sending, it wraps every email body with the saved network header and footer (stored as `hd_email_header_html` and `hd_email_footer_html` network options). No support email bypasses this wrapper.

**Inbound reply processing is not implemented.** Replies sent to notification From/Reply-To addresses are not parsed or imported back into tickets.

### Android admin API

The Android admin application should authenticate over HTTPS using WordPress Application Passwords.

- Base URL: `/wp-json/helpdesk/v1/admin/`
- Recommended auth: WordPress Application Passwords
- For browser-based requests, send a valid `X-WP-Nonce` nonce
- Android scaffold path: `/android`

### Push notifications

Set the `hd_fcm_server_key` network option with your Firebase Cloud Messaging server key before enabling push delivery.

The current scaffold uses an abstraction layer and includes a legacy HTTP call placeholder with a TODO for full FCM v1 support.

## Legacy product notes

The intended product direction remains:

- Guest and member ticket forms
- Topic-driven multi-step flows
- Knowledge base assisted submission
- Incremental ticket numbering
- Agent replies, status changes, and attachments

## Front-end entry points

The plugin currently exposes these customer-facing routes:

- `/helpdesk/` – Helpdesk landing page
- `/helpdesk/new/` – guest request form
- `/helpdesk/member/new/` – logged-in member request form
- WooCommerce My Account endpoint `helpdesk` with subviews:
  - `/my-account/helpdesk/`
  - `/my-account/helpdesk/new/`
  - `/my-account/helpdesk/requests/`
  - `/my-account/helpdesk/request/{ticket-no}/`

When the WooCommerce endpoint is added to an existing site, the plugin bumps its
rewrite version and flushes rewrites once on the next boot (or immediately on
activation) so the new account URLs resolve correctly.
