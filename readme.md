# ColorBliss Subscribe

A lightweight subscription “star button” for WordPress with a custom REST endpoint, honeypot, fill-time check, and simple rate limiting.

## Features
- `[cb_subscribe]` shortcode renders an animated star button → expands to a form.
- REST endpoint at `/wp-json/colorbliss/v1/subscribe`.
- Anti-bot measures: honeypot field, min time-to-fill, IP/email rate limits.
- Emails are configurable via WordPress filters (no secrets in repo).

## Install
1. Upload folder `colorbliss-subscribe` to `wp-content/plugins/`.
2. Activate **ColorBliss Subscribe** in WP Admin → Plugins.
3. Add `[cb_subscribe]` shortcode to any page/post.

## Configure (no hard-coded emails)
Add to your theme’s `functions.php` or a small mu-plugin:

```php
add_filter('cb_subscribe_to_email', function(){ return 'recipient@example.com'; });
add_filter('cb_subscribe_bcc_email', function(){ return 'me@example.com'; }); // optional
add_filter('cb_subscribe_from_name', function(){ return 'My Site'; });

## License
MIT

# 6) How to push to GitHub (quick)

```bash
cd path/to/colorbliss-subscribe
git init
git add .
git commit -m "Initial commit: ColorBliss Subscribe plugin"
# Create a new empty GitHub repo first, then:
git remote add origin https://github.com/<you>/colorbliss-subscribe.git
git branch -M main
git push -u origin main
