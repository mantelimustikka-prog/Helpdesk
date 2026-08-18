# WP Helpdesk

WP Helpdesk is a network-only WordPress multisite plugin scaffold for managing support tickets, topic flows, outbound notifications, attachments, and Android admin app access.

## Current scaffold scope

- Network admin menu with Dashboard, Tickets, Topics, and Settings pages
- Network-global custom tables using `$wpdb->base_prefix . 'hd_'`
- REST API namespace: `/wp-json/helpdesk/v1/`
- Email notification abstraction through `NotificationService`
- Push notification abstraction through FCM
- Attachment upload scaffolding for tickets and messages

## Architecture

### Network activation

1. Copy the plugin into your multisite plugins directory.
2. Activate **WP Helpdesk** from **Network Admin → Plugins**.
3. Activation runs all migrations and creates the `wp_hd_*` network tables using the network base prefix.
4. Activation also seeds default `hd_*` network options and registers the custom helpdesk capabilities.

### CKEditor email header/footer

The **Email Header & Footer** settings tab expects a bundled CKEditor 4 copy:

1. Download CKEditor 4 from https://ckeditor.com/ckeditor-4/download/
2. Extract the distribution into:

   `assets/vendor/ckeditor/`

3. Confirm the editor file exists at:

   `assets/vendor/ckeditor/ckeditor.js`

The plugin enqueues this bundled file for the network settings editor.

### Email delivery

All outbound plugin email must go through:

`WPHelpdesk\Domain\Notification\NotificationService`

That service is the only place that wraps email content with the configured network header and footer layout before calling `wp_mail()`.

### Android admin API

The Android admin application should authenticate over HTTPS using WordPress Application Passwords.

- Base URL: `/wp-json/helpdesk/v1/admin/`
- Recommended auth: WordPress Application Passwords
- For browser-based requests, send a valid `X-WP-Nonce` nonce

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
