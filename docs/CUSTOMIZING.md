# Customizing Cloudflare Turnstile

## Notifiers for add-ons

| Notifier | When | Data |
|---|---|---|
| `NOTIFY_TURNSTILE_FORMS` | Once per request, when the list of protected forms is first built | `$p2` (by reference) = the forms array. Add an entry to protect another form; see `zcObserverTurnstile::forms()` for the keys (`setting`, `pages`, `actionHas`, `inputName`, `inputValue` (a string or a list of accepted values), `actionParam`, `errorStack`). |
| `NOTIFY_TURNSTILE_CHECKED` | After every check | `$p1` = `['passed' => bool, 'form' => name, 'codes' => Cloudflare's error codes]` |

An add-on that protects a form checks the submission itself with:

```php
if (!zcObserverTurnstile::isActive()) { /* not configured: do nothing */ }
if (!zcObserverTurnstile::verifyToken($_POST['cf-turnstile-response'] ?? '', 'my_form')) {
    zcObserverTurnstile::refuse('my_form');   // message to the form's errorStack area
    // ...and stop the form's own processing
}
```

`verifyToken()` fails open on a Cloudflare outage or a rejected Secret Key, exactly as the built-in checks do. `zcObserverTurnstile::widgetScript($siteKey, $forms)` returns the placement script for a page the plugin doesn't know about.

## Wording

The refusal message is `TURNSTILE_TEXT_ERROR` in `catalog/includes/languages/english/extra_definitions/lang.turnstile.php`.

## Styling

The widget sits in `<div class="cf-turnstile">` with a 1rem top and bottom margin. Cloudflare draws the check itself in an iframe; its theme follows the visitor's light or dark setting.
