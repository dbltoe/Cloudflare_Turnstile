# Screenshots

Captured 2026-10-17 from the live haredo.com store and Mark's Cloudflare account (he agreed to their use). Secret Key masked in the admin shot, Cloudflare account name masked in the Cloudflare shots, admin header with the login IP and hosting hostname cropped off.

| File | What it shows |
|---|---|
| `widget-live-success-haredo.png` | The widget on the live Create Account form, tight crop. Managed mode verified the visitor with no interaction, so it reads "Success!". |
| `widget-interactive-testkey-illustration.png` | The checkbox state, "Verify you are human". Rendered with Cloudflare's forced-interactive **test** key, so it carries Cloudflare's red "For testing only" strip. Illustration only; never use it as a product shot. |
| `cloudflare-widget-hostnames-mode.jpg` | Cloudflare, Edit Widget: the store's three hostnames and the Managed mode choice. |
| `cloudflare-widget-keys.jpg` | Cloudflare, Widget Keys: Site Key in the clear, Secret Key hidden. |
| `turnstile-admin-configuration.png` | Configuration &gt; Cloudflare Turnstile with both keys in and a switch per protected form. |
| `turnstile-cloudflare-analytics-7day.jpg` | Seven days on the live store: 240 challenges, 28 solved, 11.67% human solve rate, 154 attempts from Electron. |

## TODO: put these in a guide

They were made for the Plugins Library reviewer, but they belong in the documentation, where they answer "what will this look like and what do I have to do".

- **`readme.html`, the Cloudflare setup guide.** It walks through creating the widget in words only. Add `cloudflare-widget-hostnames-mode.jpg` at the "add your hostnames, leave the mode on Managed" step and `cloudflare-widget-keys.jpg` at the "copy the two keys" step, then `turnstile-admin-configuration.png` where the keys get pasted into Zen Cart. Remember the readme ships in two places (inside the plugin and at the package root), so the images need a path that works from both — a `docs/screenshots/` folder copied by the build script, referenced relatively.
- **`docs/CONFIGURATION.md`.** Same three images, same order.
- **`README.md`.** One image only: `widget-live-success-haredo.png`, near the top, so the repo shows what the plugin puts on the page.
- **The Plugins Library listing** (`docs/PLUGIN_LIBRARY_LISTING.md`) and the myzencartzone product page: the live widget shot plus the analytics shot, which is the one that argues for the plugin rather than describing it.
- **Worth writing alongside them:** a short "what your visitors see" section. Managed mode usually verifies silently and shows the success box; the checkbox only appears when Cloudflare wants interaction. People expect reCAPTCHA's "I'm not a robot" and need to be told the quiet case is normal, not a broken widget.

When this is done, also drop the images into the forum thread (207429) as a reply, since the thread has no pictures at all.
