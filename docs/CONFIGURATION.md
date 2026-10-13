# Configuring Cloudflare Turnstile

## Cloudflare

1. Sign up at `dash.cloudflare.com` (the free plan is enough). Skip any prompt to add your domain or change DNS; Turnstile doesn't need it.
2. Left menu: **Application security > Turnstile**, then **Add widget manually**.
3. **Widget name:** anything, like `My Store forms`.
4. **Hostnames:** type the store's domain and choose *Add "example.com" as a custom hostname*. Add the `www.` form and any test copy the same way (up to 10 per widget).
5. **Widget Mode:** Managed (recommended).
6. **Skip future security rule challenges for verified visitors** (pre-clearance): off. It only applies to sites proxied through Cloudflare.
7. **Create**. Copy the **Site key** and the **Secret key** (click either to copy). They stay visible later under the widget.

Someone else's Cloudflare account: the owner invites you under **Manage account > Members**; after you accept, the account appears in your list and its Turnstile page works the same way.

Test keys for a test copy of the store: Site key `1x00000000000000000000AA` with Secret key `1x0000000000000000000000000000000AA` always pass.

## Settings (Configuration > Cloudflare Turnstile)

| Setting | Notes |
|---|---|
| Enable Cloudflare Turnstile? | Master switch. false turns every check off without uninstalling. |
| Site Key | Public; it appears in the page. |
| Secret Key | Masked in the admin. Only ever sent from the server to Cloudflare. |
| Protect Create Account | The Create Account page, the Login page's new-customer form, One Page Checkout's registration form. |
| Protect Contact Us | The Contact Us form; a failed check means no email is sent. |

## What happens when Cloudflare can't answer

If Cloudflare is unreachable, or rejects the Secret Key itself, the submission is allowed and a warning is written to the store's `logs` folder. An outage or a typo in the admin never locks out real customers. A missing, malformed or failed check from the visitor is always refused.

## Doing it for you

We'll do the whole setup for a flat fee: the widget, the plugin, the keys and a test signup on the live store. You invite us to your Cloudflare account under Manage account > Members, so no password is shared, and you can remove us afterward. See Cloudflare Turnstile Setup on myzencartzone.com: https://myzencartzone.com/index.php?main_page=product_info&products_id=56
