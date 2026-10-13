# Installing Cloudflare Turnstile

## Requirements

- Zen Cart 1.5.8 or later, on the PHP version it requires (7.4 to 8.5).
- A free Cloudflare account. The store doesn't need to run through Cloudflare.
- A template whose `common/html_header.php` fires `NOTIFY_HTML_HEAD_END`. Every Zen Cart template, ZCA Bootstrap and the Bootstrap 5 plugin template do. The installer warns in the admin when the active template doesn't.

## Install

1. Upload `zc_plugins/Turnstile/v1.1.0/` to the store's `zc_plugins/Turnstile/v1.1.0/`.
2. Admin > Modules > Plugin Manager > Cloudflare Turnstile > **Install**.
3. Create the Cloudflare widget and enter its two keys under **Configuration > Cloudflare Turnstile** (see CONFIGURATION.md).

Nothing changes on the storefront until both keys are saved, so it's safe to install first and set up Cloudflare later.

## Upgrade from v1.0.0

Upload `v1.1.0` beside `v1.0.0` and click **Upgrade** in Plugin Manager. The keys you entered are kept. The two new settings, Protect Create Account and Protect Contact Us, arrive switched on, so Contact Us is protected from the moment the upgrade finishes.

## Uninstall

Plugin Manager > Cloudflare Turnstile > **Uninstall** removes the settings page and the keys. No template file was ever changed, so there's nothing else to undo.
