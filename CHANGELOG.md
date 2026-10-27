# Cloudflare Turnstile changelog

## v1.2.1 (2026-10-27)

Plugin Manager only. Nothing changes on the storefront or in the settings.

- New Settings button in Plugin Manager. Once the plugin is installed, its info
  panel has a Settings button that opens Configuration > Cloudflare Turnstile.
  It isn't shown before Install or after Uninstall, and it works on every
  supported release (1.5.8 through 3.0.0): the installer saves the panel again
  at Install, Upgrade and Uninstall, because 1.5.8, 2.0 and 2.1 otherwise keep
  the panel they saw first.
- The buttons are one row: ReadMe | Settings | Forum Thread | GitHub. "Read Me"
  is now "ReadMe" and "Forum Support Thread" is now "Forum Thread". The buttons
  are a little narrower (8px side padding, 4px gaps), so all four fit on one
  line from a 1366px-wide window up.

## v1.2.0 (unreleased)

- Two more forms are protected, each with its own on/off setting: Ask a Question
  and Write a Review (both on by default). Both use the captcha notifier Zen Cart
  fires on those pages itself, on every release from 1.5.8 to 3.0.0, so a refused
  check means no email is sent and no review is stored.
- That brings the free plugin to the four forms store owners ask about most:
  Create Account, Contact Us, Ask a Question and Write a Review.

## v1.1.0 (2026-10-13)

First public release.

- Contact Us is now protected too: a failed check means the message isn't sent.
- One Page Checkout's account-registration form (action=register) now gets the
  check too. v1.0.0 only recognized core's action=process form, so with OPC's
  registered accounts switched on, registrations were refused with no check
  shown. Stores using core's Create Account form were never affected.
- Each form has its own on/off setting: Protect Create Account and Protect
  Contact Us (both on by default).
- The installer warns when the active template's html_header.php doesn't fire
  NOTIFY_HTML_HEAD_END, which would hide the check while still requiring it.
- Add-on seams for Turnstile Pro: NOTIFY_TURNSTILE_FORMS (add forms),
  NOTIFY_TURNSTILE_CHECKED (each result), and a public
  zcObserverTurnstile::verifyToken().
- siteverify calls verify Cloudflare's certificate explicitly; no curl_close()
  (deprecated in PHP 8.5).
- Zen Cart 1.5.8 (PHP 7.4 and 8.0) is supported as well as 2.0 through 3.0.0.
- The widget's size is chosen once the page is visible. Zen Cart's
  responsive_classic template hides the page until its own script runs, so the
  check always came out in its small "compact" size there, even on a desktop.

## v1.0.0 (2026-10-13)

Private build for one store: the check on every create-account form (Create
Account page, the Login page's new-customer form, One Page Checkout's
registration form).
