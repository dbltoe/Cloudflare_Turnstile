**Support thread:** https://www.zen-cart.com/threads/207429?page=1#post-1347614 ("Cloudflare Turnstile [Support Thread]", All Other Contributions/Addons), posted by John 2026-10-13. The permalink is in the manifest's forum button, the readme and README.md. Opening post below, as drafted.

---

**Cloudflare Turnstile** stops bot signups and Contact Us spam with Cloudflare's free Turnstile check. No picture puzzles, nothing goes to Google, and most real visitors pass without doing anything at all.

Free and encapsulated (zc_plugins), for Zen Cart 2.0 through 3.0.0 on PHP 8.0 through 8.5. Download and source: https://github.com/dbltoe/Cloudflare_Turnstile/releases/latest

**Why it exists**

We wrote it for a store whose customer list had been filling up with fake accounts for almost two years: well over a thousand of them, real-looking names and addresses, almost none of which ever ordered. Every one of them got a welcome email, most were signed up for the newsletter, and the template's honeypot field didn't slow them down. Since Turnstile went live there, signups need to pass Cloudflare's check first.

**What it protects**

Every form that creates an account: the Create Account page, the new-customer form on the Login page, and One Page Checkout's account-registration form. A failed check means no account and no welcome email.

Contact Us. A failed check means the message isn't sent.

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

---

## v1.2.0 update reply (POSTED by John, 2026-10-17)

Drafted 2026-10-17 for thread 207429 and posted by John himself; the wording as it went up was not captured here. The forum closes edits a few minutes after submission, so neither this reply nor the opening post can be changed. The opening post above is the record of what was posted on 2026-10-13 and lists two forms, because that is what v1.1.0 protected; this reply adds the other two. The opening post also understates the range ("Zen Cart 2.0 through 3.0.0 on PHP 8.0 through 8.5"), which the 1.5.8 re-cut on the same day made stale, so the reply corrects it.

**v1.2.0 is up**, and it adds two more forms. The plugin now covers Create Account, Contact Us, Ask a Question and Write a Review, which is the set most people ask for.

Download and source: https://github.com/dbltoe/Cloudflare_Turnstile/releases/latest

**What's new**

Ask a Question, on a product page. A failed check means the message isn't sent.

Write a Review. A failed check means the review isn't stored.

Each has its own on/off switch, and both arrive switched on after an upgrade. Your keys and your existing settings are left alone.

Both use the captcha notifier Zen Cart fires on those pages itself, so the check happens before core mails anything or stores a review. As before, no template file is edited, and if Cloudflare can't be reached the submission is allowed and a warning goes to your logs folder.

**Upgrading**

Upload the v1.2.0 folder beside your current one and click Upgrade in Plugin Manager. That's all; the settings you already saved are kept.

**Tested**

End to end on a 2.2.2 store: installed from Plugin Manager, the check appears on each form, and a review posted without completing the check was refused and never stored. Also checked against every release the plugin declares, 1.5.8 through 3.0.0 on PHP 7.4 through 8.5, including that each form's notifier fires before the point where Zen Cart mails or stores anything.

**Two corrections to the first post in this thread**, which I can no longer edit:

It says Zen Cart 2.0 through 3.0.0 on PHP 8.0 through 8.5. Since v1.1.0 it has been **1.5.8 through 3.0.0 on PHP 7.4 through 8.5**.

It also lists reviews among the things coming in a paid add-on. Write a Review is in this free release instead, along with Ask a Question.

---

## Widget screenshot reply (POSTED 2026-10-17, post 1347683)

https://www.zen-cart.com/threads/207429?page=1#post-1347683 — attachment `docs/screenshots/widget-live-success-haredo.png`, posted from John's logged-in Chrome at his instruction. Text as posted:

A few people have asked what it actually looks like on the page. Here it is on a live store's Create Account form, sitting just above the Submit button.

In Managed mode most visitors never tick anything: Cloudflare verifies quietly in the background and the box shows the green Success you see here. The checkbox reading "Verify you are human" only appears when Cloudflare decides it wants interaction, so a quiet pass is normal rather than a sign the check isn't working. On a narrow phone the box switches to its compact size so it doesn't push the page sideways.

Forum notes: the reply editor is rich text over a hidden `#reply-body` textarea and must be opened with the Reply link (`#reply`) before typing lands; the attachment input accepts a direct file upload before the editor is open. Editing closes minutes after posting.
