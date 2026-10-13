# Cloudflare Turnstile changelog

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
