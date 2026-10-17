**Support thread:** https://www.zen-cart.com/threads/207429?page=1#post-1347614 ("Cloudflare Turnstile [Support Thread]", All Other Contributions/Addons), posted by John 2026-10-13. The permalink is in the manifest's forum button, the readme and README.md. Opening post below, as drafted.

---

**Cloudflare Turnstile** stops bot signups and Contact Us spam with Cloudflare's free Turnstile check. No picture puzzles, nothing goes to Google, and most real visitors pass without doing anything at all.

Free and encapsulated (zc_plugins), for Zen Cart 2.0 through 3.0.0 on PHP 8.0 through 8.5. Download and source: https://github.com/dbltoe/Cloudflare_Turnstile/releases/latest

**Why it exists**

We wrote it for a store whose customer list had been filling up with fake accounts for almost two years: well over a thousand of them, real-looking names and addresses, almost none of which ever ordered. Every one of them got a welcome email, most were signed up for the newsletter, and the template's honeypot field didn't slow them down. Since Turnstile went live there, signups need to pass Cloudflare's check first.

**What it protects**

Every form that creates an account: the Create Account page, the new-customer form on the Login page, and One Page Checkout's account-registration form. A failed check means no account and no welcome email.

Contact Us. A failed check means the message isn't sent.

Ask a Question, on a product page. A failed check means the message isn't sent.

Write a Review. A failed check means the review isn't stored.

Each form has its own on/off switch. The check is placed just above the form's Submit or Send button, and every submission is verified on your server, so a bot that skips the page and posts straight to the form is refused too.

**What it doesn't change**

No template file is edited. The check is added by a small script, and the server side uses core's own notifiers (`NOTIFY_CREATE_ACCOUNT_VALIDATION_CHECK` and `NOTIFY_CONTACT_US_CAPTCHA_CHECK`). If Cloudflare can't be reached, or rejects your Secret Key, the submission is allowed and a warning goes to your logs folder, so an outage or a typo never locks out real customers.

**Setting it up**

Install it from Modules > Plugin Manager. It does nothing until you enter a Site Key and a Secret Key under Configuration > Cloudflare Turnstile, so it's safe to install first.

The keys come from a free Cloudflare account. Your site doesn't need to run through Cloudflare. In the Cloudflare dashboard it's Application security > Turnstile > Add widget manually: add your store's hostnames (with and without www), leave the mode on Managed, and create it. The readme walks through every screen.

If you'd rather not do the Cloudflare part yourself, we'll do the whole setup for a flat fee, including a test signup on your live store. You invite us to your Cloudflare account, so no password changes hands. Ask at myzencartzone.com.

**Things to know**

Your template's `common/html_header.php` has to fire `NOTIFY_HTML_HEAD_END`. Every Zen Cart template, ZCA Bootstrap and the Bootstrap 5 plugin template do. The installer warns you in the admin if yours doesn't, and until it's fixed you should leave Turnstile switched off, or the check would be required but never shown.

If you tried an early copy (v1.0.0) on a store with One Page Checkout's registered accounts switched on, please use v1.1.0: v1.0.0 didn't recognize OPC's registration form.

Testing on a copy of your store? Cloudflare publishes test keys that always pass. They're in the readme.

**Coming separately**

Turnstile Pro, for stores that are being attacked rather than just annoyed: protection for checkout against card testing, the login and password-reset forms, reviews and the other forms bots use, the admin login, and a report of what was blocked.

Questions, reports and suggestions are welcome here.
