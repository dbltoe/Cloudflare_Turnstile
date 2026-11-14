# Cloudflare Turnstile

Version 1.2.2 · Zen Cart 1.5.8 through 3.0.0 · PHP 7.4 through 8.5 · GPL v2 · by My Zen Cart Host (dbltoe)

Stop bot signups and Contact Us spam with Cloudflare's free check. No picture puzzles, and nothing goes to Google.

Bots that create fake accounts fill your customer list, your newsletter list and your mail log with junk, and every one of them gets a welcome email from your store. Cloudflare Turnstile tells people and bots apart without making shoppers click on traffic lights. Most real visitors pass without doing anything at all.

This plugin adds Turnstile to the forms bots go for, checks every submission on your server, and refuses the ones that fail. It's an encapsulated plugin: everything lives in `zc_plugins/Turnstile/`, and no template file is changed.

> **Rather not do the Cloudflare part yourself?** We'll do the whole setup for you for a flat fee: the Cloudflare widget, the plugin, the keys, and a test signup on your live store to prove it works. You never have to share your Cloudflare password; you just invite us to your Cloudflare account, and you can remove us when we're done. See Cloudflare Turnstile Setup on myzencartzone.com: https://myzencartzone.com/index.php?main_page=product_info&products_id=56

## What it protects

| Form | Where it appears |
| --- | --- |
| Create Account | The Create Account page, the new-customer form on the Login page, and One Page Checkout's registration form. A failed check means no account is created and no welcome email goes out. |
| Contact Us | The Contact Us page. A failed check means the message isn't sent. |
| Ask a Question | The Ask a Question form on a product page. A failed check means the message isn't sent. |
| Write a Review | The review form. A failed check means the review isn't stored. |

Each form can be switched on or off on its own. The check is placed just above the form's Submit or Send button.

## Requirements

| Item | Needed |
| --- | --- |
| Zen Cart | 1.5.8 or later (1.5.8, 2.0, 2.1, 2.2, 2.3, 3.0.0). Works with One Page Checkout. |
| PHP | 7.4 or later, whatever your Zen Cart version requires (tested on PHP 7.4 through 8.5). curl is used when available; without it the plugin falls back to PHP's own HTTP functions. |
| Cloudflare | A free Cloudflare account. Your site does **not** need to run through Cloudflare; Turnstile works on any website. |
| Template | Your template's `common/html_header.php` must fire `NOTIFY_HTML_HEAD_END`. Every Zen Cart template and ZCA Bootstrap do. The installer warns you if yours doesn't (see Troubleshooting). |

## Installing

1. Upload the `zc_plugins/Turnstile` folder from the package into your store's `zc_plugins` folder, so that it lands at `<store root>/zc_plugins/Turnstile/v1.2.2/`. Nothing goes anywhere else.
2. In the admin, go to **Modules > Plugin Manager**, select **Cloudflare Turnstile** and click **Install**.
3. A new settings page appears at **Configuration > Cloudflare Turnstile**, and the plugin's Plugin Manager panel gets a **Settings** button that opens it. The plugin does nothing until you enter the two keys from Cloudflare, so it's safe to install before you have them.

Once it's installed, a superuser can open this guide from the **ReadMe** button in Plugin Manager.

## Setting up Cloudflare, step by step

This takes about five minutes. Cloudflare changes its dashboard from time to time, so a button may move, but the steps stay the same.

### Get a Cloudflare account

1. Go to `dash.cloudflare.com` and sign up. The free plan is all Turnstile needs.
2. You don't need to add your domain or change your DNS. Skip any prompt that asks you to.

### Create the widget

1. In the left menu, open **Application security** and choose **Turnstile**.
2. Click **Add widget manually**. (Ignore "Set up with Spin"; this plugin does that wiring for you.)
3. **Widget name:** anything that tells you what it's for, like `My Store forms`.
4. **Hostnames:** type your store's domain, for example `example.com`, and choose **Add "example.com" as a custom hostname**. Add `www.example.com` the same way, and any test copy of your store (like `test.example.com`). A widget can have up to 10 hostnames.
5. **Widget Mode:** leave it on **Managed** (Cloudflare's recommendation). Most visitors pass invisibly; only suspicious ones are asked to tick a box.
6. **Skip future security rule challenges for verified visitors** (pre-clearance): leave it **off**. It only applies to sites whose traffic runs through Cloudflare.
7. Click **Create**.

### Copy the keys into your store

1. Cloudflare now shows a **Site key** and a **Secret key**. Clicking either one copies it.
2. In your Zen Cart admin, open **Configuration > Cloudflare Turnstile**.
3. Paste the Site key into **Site Key** and the Secret key into **Secret Key**, saving each one.

That's it: the check is live as soon as both keys are saved. You can see the keys again later under Application security > Turnstile > your widget.

> **Keep the Secret key secret.** It's only ever sent from your server to Cloudflare. The Site key is public by design; it appears in your page's code.

### If someone else manages your Cloudflare account

Ask the account owner to invite you under **Manage account > Members**. After you accept the invitation email, the account appears in your Cloudflare account list, and its Turnstile page works the same way. This is also how you let us set it up for you without sharing a password.

## Settings

All under **Configuration > Cloudflare Turnstile**.

| Setting | What it does |
| --- | --- |
| Enable Cloudflare Turnstile? | The master switch. **false** turns every check off without uninstalling. |
| Site Key | From your Cloudflare widget. Public. |
| Secret Key | From the same widget. Shown masked in the admin. |
| Protect Create Account | Check every form that creates an account. |
| Protect Contact Us | Check the Contact Us form before its email is sent. |
| Protect Ask a Question | Check the Ask a Question form before its email is sent. |
| Protect Write a Review | Check the review form before the review is stored. |

## Testing it

1. Open your store's Create Account page in a private (incognito) window. You should see the Cloudflare check just above the Submit button. Usually it shows "Success!" on its own within a second or two.
2. Create a throwaway account. If it's created, everything is working end to end. Delete it under Customers afterward.
3. Do the same on Contact Us and send yourself a test message.
4. If you have reviews switched on, open a product's Write a Review page and post one; and try Ask a Question on a product page. Both show the same check above their button.

Want to test without touching your live store? On a test copy, use Cloudflare's published test keys: Site key `1x00000000000000000000AA` (always passes) with Secret key `1x0000000000000000000000000000000AA`. Switch to your real keys for the live store.

## Troubleshooting

| What you see | What to check |
| --- | --- |
| No check appears on the form | Both keys must be saved and the master switch on. Then check that your template fires `NOTIFY_HTML_HEAD_END`: the installer shows a warning in the admin if it doesn't. Until the template is fixed, leave Turnstile switched off, or nobody will be able to submit the form. |
| The check says the domain is invalid | The store's hostname isn't on the widget. Add it (with and without `www.`) under Application security > Turnstile > your widget. |
| Customers see "Please complete the security check" | They submitted before the check finished, or JavaScript is off in their browser. The form keeps what they typed; they tick the box and submit again. |
| Signups still get through with a wrong Secret key | By design. If Cloudflare rejects your Secret key or can't be reached, the plugin lets the submission through and writes a warning to your `logs` folder, so a typo or an outage never locks out real customers. Check the logs folder for "Turnstile" warnings. |
| Fake accounts already in your store | Turnstile stops new ones. Existing fake accounts need removing separately; we can help with that too. |

## Privacy and safety

- Cloudflare says Turnstile processes only the data it needs for the security check and doesn't read or send your visitors' form entries. Its full terms are in Cloudflare's Turnstile Privacy Addendum. Mention Cloudflare in your privacy notice as a security service provider.
- The Secret key never leaves your server except to go to Cloudflare, and it's masked in the admin.
- The plugin only talks to Cloudflare's Turnstile address and loads only Cloudflare's Turnstile script.

## Upgrading and uninstalling

**Upgrade:** upload the new version folder beside the old one, so both the old folder and `zc_plugins/Turnstile/v1.2.2/` are present, and click **Upgrade** in Plugin Manager. Your keys and choices are kept; new settings arrive with sensible defaults. Delete the old version folder afterward.

**Version 1.2.2** replaces readme.html with this guide: the ReadMe button in Plugin Manager now opens it inside the admin, for superusers, in its own window. Nothing changes on the storefront or in the settings.

**Uninstall:** Plugin Manager > Cloudflare Turnstile > Uninstall removes the settings page and the keys. No template file was ever changed, so there's nothing else to undo. Delete the widget in Cloudflare too if you won't use it again.

## Turnstile Pro

Turnstile Pro is a separate add-on for stores that want more: protection for checkout against card testing (bots trying stolen cards through your store), the login and password-reset forms, product reviews and the other forms bots use, the admin login, per-form check styles, and a report of what was blocked. Ask us at https://myzencartzone.com/

## Support

Cloudflare Turnstile for Zen Cart is written and maintained by My Zen Cart Host (dbltoe). Questions and reports are welcome in the Zen Cart forum support thread (https://www.zen-cart.com/threads/207429?page=1#post-1347614), which is also linked from the plugin's Plugin Manager panel, or as an issue on the GitHub repository (https://github.com/dbltoe/Cloudflare_Turnstile).

The download also carries `docs/INSTALL.md`, `docs/CONFIGURATION.md`, `docs/COMPATIBILITY.md` and `docs/CUSTOMIZING.md`, for notifiers, wording, styling and version details.

## Changelog

See `CHANGELOG.md`. Version 1.2.2 replaces readme.html with this guide, opened from the ReadMe button in Plugin Manager.

## License

Cloudflare Turnstile is free software under the GNU General Public License, version 2 (https://www.gnu.org/licenses/old-licenses/gpl-2.0.html); the full text is in `LICENSE`. Copyright © 2026 My Zen Cart Host (dbltoe).

Cloudflare and Turnstile are trademarks of Cloudflare, Inc.; this plugin is independent and isn't made or endorsed by Cloudflare.
