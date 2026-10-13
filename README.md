# Cloudflare Turnstile for Zen Cart

Adds a Cloudflare Turnstile check to every create-account form (Create Account page, the Login
page's inline form, One-Page Checkout's registration form) and refuses the account when the
check is missing or fails. No template file is changed.

- Install from Admin > Modules > Plugin Manager, then enter the Site Key and Secret Key under
  Configuration > Cloudflare Turnstile. It does nothing until both keys are set.
- Server-side check: `NOTIFY_CREATE_ACCOUNT_VALIDATION_CHECK` (core create_account and OPC).
- Widget: injected by a small script printed at `NOTIFY_HTML_HEAD_END` on the create_account
  and login pages; compact size below 300px of column width.
- If Cloudflare is unreachable or rejects the Secret Key, the account is allowed and a
  myDEBUG warning is written, so an outage or a typo never blocks real customers.

Tested 2026-10-13 on test.haredo.com (ZC 2.2.2, ZCA Bootstrap) with Cloudflare's test keys.
