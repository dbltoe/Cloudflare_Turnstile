# Compatibility

All four forms — Create Account, Contact Us, Ask a Question, Write a Review — work on every release below.

| Zen Cart | All four forms | Notes |
|---|---|---|
| 1.5.8 | yes | PHP 7.4 or 8.0 |
| 2.0.x | yes | |
| 2.1.x | yes | |
| 2.2.x | yes | Live on a 2.2.2 store with ZCA Bootstrap and One Page Checkout |
| 2.3 | yes | |
| 3.0.0 | yes | |

The server-side checks use core notifiers that exist on every release listed: `NOTIFY_CREATE_ACCOUNT_VALIDATION_CHECK` (core `create_account.php`, and One Page Checkout's registration page), `NOTIFY_CONTACT_US_CAPTCHA_CHECK` (core `contact_us` page), `NOTIFY_ASK_A_QUESTION_CAPTCHA_CHECK` and `NOTIFY_REVIEWS_WRITE_CAPTCHA_CHECK`. `tests/zc_compat.php` checks them against each branch.

PHP 7.4 through 8.5. Every shipped file is linted, and every test runs, on each of them.

## Templates

The widget is placed by a small script printed at `NOTIFY_HTML_HEAD_END`, which the template's `common/html_header.php` fires. Zen Cart's template_default and responsive_classic do on every release, as do ZCA Bootstrap and the Bootstrap 5 plugin template. A template copied from a very old Zen Cart may not: the installer looks at the active template's file and warns in the admin if the call is missing. Until it's added, leave Turnstile switched off, or the check would be required but never shown.

The script finds forms by their action, not by template markup:

- create account: the action contains `create_account` and the form carries `action=process` (core) or `action=register` (One Page Checkout's account-registration form);
- Contact Us: the action contains `contact_us` and `action=send`;
- Ask a Question: the action contains `ask_a_question` and `action=send`;
- Write a Review: the action contains `product_reviews_write` and `action=process`.

So a template that restyles those forms is fine as long as it keeps core's form actions.

## One Page Checkout

When OPC's registered accounts are on, its account-registration form replaces core's Create Account form and posts `action=register`; it fires the same `NOTIFY_CREATE_ACCOUNT_VALIDATION_CHECK`, so it's recognized and checked like core's. Checked on One Page Checkout 2.7.0.
