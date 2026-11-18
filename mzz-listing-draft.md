# Cloudflare Turnstile (free) on myzencartzone.com: listing draft

For John's approval before anything goes live. Mirrors Product Recipes (80) and Error Pages (77).

## Product 83

| Field | Value |
|---|---|
| Name | Cloudflare Turnstile |
| Model | TURNSTILE-v1.2.1 |
| Price | $0, virtual, Download attribute (option 1 value 1) |
| Zip | cloudflare_turnstile_v1.2.1.zip (GitHub release v1.2.1 asset, 15 entries, 40,547 bytes, sha256 62650fbb...) |
| Categories | Free Plugins (1) + By Zen Cart Version 1.5.8 (5) through 3.0.0 (10), from the manifest's zcVersions |
| Sort | 570 |
| URL field | github.com/dbltoe/Cloudflare_Turnstile |
| Tile | plugins/turnstile.jpg (tick box with a small shield, TURNSTILE / FREE) |
| PHP range | 7.4-8.5 |
| Bundle (66) | new box "Cloudflare Turnstile" at sort 7, after Cash on Pickup; Dashboard Boxes through Trademark move from 7-23 to 8-24 |

### Description

Cloudflare Turnstile stops bot signups and form spam on your Zen Cart store with Cloudflare's free Turnstile check: no picture puzzles, and nothing goes to Google. Most real shoppers pass without doing a thing; only a visitor Cloudflare is unsure of is asked to tick a box.

The check goes on every form that creates an account (the Create Account page, the Login page's new-customer form and One Page Checkout's registration form), on Contact Us, on Ask a Question and on Write a Review, and each form has its own switch. Every submission is checked on your server, so a bot that posts straight to your store without loading the page is refused too: no account is created, no welcome email goes out, no message is sent and no review is stored.

Setting it up takes about five minutes in a free Cloudflare account, and your store doesn't need to run through Cloudflare. Paste the Site Key and Secret Key into Configuration > Cloudflare Turnstile and the check is live. Until both keys are in, the plugin does nothing, so it's safe to install first. The check appears just above each form's button with no template file changed, and the package includes a step-by-step guide to every Cloudflare screen.

**Zen Cart v1.5.8 through v3.0.0, PHP 7.4 through 8.5**, one codebase. Encapsulated: one folder under `zc_plugins`, installed from Plugin Manager, with no core or template file edited. Works with One Page Checkout. By My Zen Cart Host (dbltoe). GPL v2. Cloudflare and Turnstile are trademarks of Cloudflare, Inc.; this plugin isn't made or endorsed by Cloudflare.

**Want more?** Cloudflare Turnstile Pro (61) adds the check to Login, Password Forgotten and Gift Certificate send, a strict mode for a store under attack, and a report of every check.

**Prefer we do it?** Cloudflare Turnstile Setup (56) creates the widget in your Cloudflare account, installs the plugin, enters the keys and proves it with a test signup.

**Questions?** Ask in the Cloudflare Turnstile support thread on the Zen Cart forum (207429, #post-1347614).

### Meta tags

- Title: Cloudflare Turnstile: Stop Bot Signups on Zen Cart
- Keywords: Zen Cart Cloudflare Turnstile, bot signups, fake customer accounts, spam protection, CAPTCHA alternative, reCAPTCHA alternative, contact form spam, One Page Checkout, Zen Cart plugin, encapsulated plugin
- Description: Free Zen Cart plugin that adds Cloudflare's Turnstile check to Create Account, Contact Us, Ask a Question and Write a Review, checked on your server. No picture puzzles. Zen Cart 1.5.8 through 3.0.0.

## Two small changes to live pages that go with it

1. **Turnstile Pro (61)**: its first paragraph links the free plugin to GitHub ("not sold here" was the reason, 2026-10-19). Point that link at product 83 instead. Nothing else in 61 changes.
2. **Cloudflare Turnstile Setup (56)**: says "For Zen Cart 2.0 and later." The plugin has run on 1.5.8 since v1.1.0, so make it "For Zen Cart 1.5.8 and later." Nothing else in 56 changes.
