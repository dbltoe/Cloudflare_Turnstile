# Turnstile Pro: scope sketch

Status: sketch for John, 2026-10-13. Not built. Not packaged with the free plugin.

## Positioning

The free plugin covers what the Plugins Library's Google reCAPTCHA plugin covers (Create Account and Contact Us), without Google, and is the first Turnstile plugin in the Library. Pro is for stores that are being attacked, not just annoyed: card testing, password guessing, review spam. It's a separate plugin (`zc_plugins/TurnstilePro/`) that requires free v1.1.0 or later and plugs into its seams, the same split as Authorize.Net Accept and Accept Pro.

## What Pro adds

### 1. Checkout card-testing shield (the headline)

Card testers run stolen cards through a store's checkout, small amounts, many cards. KDMR has seen it (one account, 5 Chase cards, five ZIP codes, six tries). Each attempt costs the merchant a processing fee, and enough of them gets the merchant account flagged.

- The Turnstile check on the payment step: the standard checkout's confirmation page and One Page Checkout's Confirm button.
- A failed-payment counter per customer, per IP and per card fingerprint (brand + last four, never the number). Past a threshold in a time window, the next attempt needs a fresh interactive check, then is refused for a cooling-off period.
- Works with any payment module; aware of Round Pay and Authorize.Net Accept through their failure notifiers (`NOTIFY_ROUNDPAY_PAYMENT_FAILED`, the Accept.js equivalents), so declines are counted precisely instead of guessed.
- To verify before building: the exact notifier before payment processing on each release (`checkout_process` start) and how OPC posts the token through its confirmation step. Turnstile tokens are single use and expire after 300 seconds, so the OPC page has to refresh the widget if the buyer lingers.

### 2. More forms

Login (password guessing), Password Forgotten (email bombing), One Page Checkout's guest-information form, Write a Review, Ask a Question, newsletter signup (where a newsletter plugin provides a form), Gift Certificate send. Each one is a form entry added through `NOTIFY_TURNSTILE_FORMS`, its own on/off switch, and a server check on that form's core notifier (to be confirmed per form and per release, as Contact Us was).

### 3. Admin login

The check on the admin login page. The stakes are highest there; the setting comes with a clear "if Cloudflare is down you can still log in" fail-open, and a documented way back in.

### 4. The report

A table of checks (form, passed or not, Cloudflare's codes, date, and the IP truncated to /24 or /48 so it isn't personal data) with an admin page: blocks per day and per form, the busiest sources, and failed-payment streaks. Retention setting (default 30 days), and a purge on uninstall. Fed by the free plugin's `NOTIFY_TURNSTILE_CHECKED`.

### 5. Per-form behavior

- Strict mode per form: fail **closed** when Cloudflare can't answer (for checkout and admin login a store may prefer that).
- Appearance per form: Cloudflare sets Managed / Non-interactive / Invisible on the widget, so Pro accepts more than one widget (site key and secret per mode) and a choice per form; plus theme (auto/light/dark), size, language and "interaction-only" appearance.

### 6. Diagnostics

A "Test my keys" button in the admin: it calls siteverify with a dummy token. Cloudflare answers `invalid-input-response` when the Secret Key is good and `invalid-input-secret` when it isn't, so a typo is caught before a customer meets it (behavior to confirm against Cloudflare before building). (This one is small enough to consider for free v1.2.0 instead.)

## Setup service (both free and Pro)

Offered in the free readme, README.md and CONFIGURATION.md as "a flat fee". What it includes:

1. The store owner invites us to their Cloudflare account (Manage account > Members), or we create the Cloudflare account with them on a call. No password changes hands.
2. We create the widget with the store's hostnames (live, www, test copy).
3. We install or upgrade the plugin and enter the keys.
4. We make a real test signup and a Contact Us message on the live store, then delete the test account.
5. Pro: we set the checkout thresholds with them and show them the report.

Open: the price. A myzencartzone.com product for it (like the plugin products) would let customers order it directly.

## Pricing to decide

Free plugin: free. Pro: in line with our other Pro plugins. Setup service: flat fee, separately or bundled with Pro at a discount.

## Rough effort

Checkout shield and report: most of the work (two to three sessions with the harness and a sandbox run on _test223 with Round Pay and Authorize.Net in sandbox). Extra forms: one session, mostly verifying each form's notifier on every branch. Admin login and diagnostics: one session.
