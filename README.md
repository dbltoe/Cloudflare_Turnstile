# Cloudflare Turnstile for Zen Cart

Stops bot signups and Contact Us spam with Cloudflare's free Turnstile check. No picture puzzles, and nothing goes to Google.

The check goes on every create-account form (the Create Account page, the Login page's new-customer form, and One Page Checkout's registration form) and on Contact Us. Every submission is verified on the server, and one whose check is missing or fails is refused. No template file is changed.

An encapsulated plugin (`zc_plugins/Turnstile/`) for **Zen Cart 2.0 through 3.0.0** on **PHP 8.0 through 8.5**.

## Quick start

1. Upload `zc_plugins/Turnstile` into the store's `zc_plugins` folder.
2. **Modules > Plugin Manager**: install Cloudflare Turnstile.
3. In a free Cloudflare account, **Application security > Turnstile > Add widget manually**: add your store's hostnames, leave the mode on Managed, and create it.
4. Paste the Site Key and Secret Key into **Configuration > Cloudflare Turnstile**.

The full guide, with every Cloudflare screen, is `readme.html` (also inside the plugin and linked from Plugin Manager).

**Rather not do it yourself?** We'll set it up for you for a flat fee. Ask at [myzencartzone.com](https://myzencartzone.com/).

## Documents

- [docs/INSTALL.md](docs/INSTALL.md): installing, upgrading, uninstalling
- [docs/CONFIGURATION.md](docs/CONFIGURATION.md): the Cloudflare setup and every setting
- [docs/COMPATIBILITY.md](docs/COMPATIBILITY.md): Zen Cart and PHP versions, templates, One Page Checkout
- [docs/CUSTOMIZING.md](docs/CUSTOMIZING.md): notifiers for add-ons, wording, styling
- [CHANGELOG.md](CHANGELOG.md)

## License

GNU General Public License, version 2. See [LICENSE](LICENSE). Cloudflare and Turnstile are trademarks of Cloudflare, Inc.; this plugin is independent and isn't made or endorsed by Cloudflare.
