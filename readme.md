# ColorBliss Subscribe (WordPress Plugin)

A lightweight WordPress plugin that adds an interactive “subscribe” star button with a custom REST endpoint, anti-bot protections, and a modern JS/CSS front-end.

Originally built for a client project, this plugin demonstrates a clean **WordPress + REST + frontend interaction pattern**.

---

## Features

* Shortcode: `[cb_subscribe]`
* Interactive UI (animated star → expandable form)
* Custom REST API endpoint (`/wp-json/colorbliss/v1/subscribe`)
* Spam protection:

  * Honeypot field
  * Minimum fill-time check
  * Rate limiting (per IP + email)
* Email notifications via `wp_mail`
* Accessible UI (keyboard + ARIA support)
* Minimal footprint (no external dependencies)

---

## Architecture Overview

This plugin follows a simple but powerful pattern:

* **PHP (WordPress layer)**

  * Registers shortcode
  * Enqueues assets
  * Defines REST endpoint
  * Handles validation + email sending

* **JavaScript (client layer)**

  * UI interaction (expand/collapse)
  * Form submission via `fetch`
  * Modal feedback

* **CSS (presentation layer)**

  * Animated star button
  * Responsive layout
  * Modal styling

This separation keeps WordPress as the “bridge” while allowing modern frontend behavior.

---

## Installation

1. Download or clone this repository
2. Place the folder in:

   ```
   wp-content/plugins/
   ```
3. Activate **ColorBliss Subscribe** in WordPress Admin
4. Add the shortcode to any page or post:

   ```
   [cb_subscribe]
   ```

---

## Configuration

Avoid hardcoding email addresses by using filters:

```php
add_filter('cb_subscribe_to_email', function() {
  return 'you@example.com';
});
```

Optional:

```php
add_filter('cb_subscribe_bcc_email', fn() => 'backup@example.com');
add_filter('cb_subscribe_from_name', fn() => 'My Site');
```

---

## REST Endpoint

```
POST /wp-json/colorbliss/v1/subscribe
```

Payload:

```json
{
  "name": "John Doe",
  "email": "john@example.com"
}
```

---

## Anti-Spam Measures

* Hidden honeypot field
* Minimum interaction time threshold (~1.5s)
* Rate limiting:

  * 1 request per 30s per email
  * 10 requests per hour per IP

---

## Notes

This plugin intentionally keeps PHP minimal and pushes interaction into the frontend.
It could be extended to use an external service (e.g., Node, .NET, or serverless backend) while keeping WordPress as the integration layer.

---

## License

MIT
