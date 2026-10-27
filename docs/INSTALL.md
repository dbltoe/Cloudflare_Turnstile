# Installing Cloudflare Turnstile

## Requirements

- Zen Cart 1.5.8 or later, on the PHP version it requires (7.4 to 8.5).
- A free Cloudflare account. The store doesn't need to run through Cloudflare.
- A template whose `common/html_header.php` fires `NOTIFY_HTML_HEAD_END`. Every Zen Cart template, ZCA Bootstrap and the Bootstrap 5 plugin template do. The installer warns in the admin when the active template doesn't.

## Install

1. Upload `zc_plugins/Turnstile/v1.2.1/` to the store's `zc_plugins/Turnstile/v1.2.1/`.
2. Admin > Modules > Plugin Manager > Cloudflare Turnstile > **Install**.
3. Create the Cloudflare widget and enter its two keys under **Configuration > Cloudflare Turnstile** (see CONFIGURATION.md). Once the plugin is installed, the **Settings** button in its Plugin Manager panel opens that page too.

Nothing changes on the storefront until both keys are saved, so it's safe to install first and set up Cloudflare later.

## Upgrade from v1.2.0

Upload `v1.2.1` beside `v1.2.0` and click **Upgrade** in Plugin Manager. Your keys and settings are kept. Nothing changes on the storefront; the Plugin Manager panel gets its Settings button.

## Upgrade from v1.1.0

Upload `v1.2.1` beside `v1.1.0` and click **Upgrade** in Plugin Manager. Your keys and your existing settings are kept. The two new settings, Protect Ask a Question and Protect Write a Review, arrive switched on, so both forms are protected from the moment the upgrade finishes. Switch either off under Configuration > Cloudflare Turnstile if you'd rather not check it.

## Upgrade from v1.0.0

Upload `v1.2.1` beside `v1.0.0` and click **Upgrade** in Plugin Manager. The keys you entered are kept. The four per-form settings all arrive switched on.

## Uninstall

Plugin Manager > Cloudflare Turnstile > **Uninstall** removes the settings page and the keys. No template file was ever changed, so there's nothing else to undo.
