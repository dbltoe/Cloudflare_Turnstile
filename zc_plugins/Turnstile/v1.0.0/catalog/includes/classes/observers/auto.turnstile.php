<?php
/**
 * Cloudflare Turnstile -- storefront observer.
 *
 * Auto-loaded and instantiated by includes/init_includes/init_observers.php;
 * the class name must stay 'zcObserver' . camelize(<filename>), and nothing
 * may list this file in an auto_loader as well.
 *
 *   NOTIFY_HTML_HEAD_END                    On the create_account and login
 *                                           pages, prints a small script that
 *                                           puts the widget into every form
 *                                           that creates an account, then
 *                                           loads Cloudflare's api.js. No
 *                                           template file is changed.
 *   NOTIFY_CREATE_ACCOUNT_VALIDATION_CHECK  Fired by core create_account.php
 *                                           and by One-Page Checkout's
 *                                           registration page. Verifies the
 *                                           token with Cloudflare and sets
 *                                           $error when it is missing or
 *                                           rejected, so the form comes back
 *                                           with a message and no account is
 *                                           created.
 *
 * If Cloudflare cannot be reached, or rejects the Secret Key itself, the
 * account is allowed and a myDEBUG log is written: a Cloudflare outage or a
 * typo in the admin must not stop real customers signing up.
 *
 * @package  Turnstile
 * @license  http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */

if (!defined('IS_ADMIN_FLAG')) {
    die('Illegal Access');
}

class zcObserverTurnstile extends base
{
    public const SCRIPT_URL = 'https://challenges.cloudflare.com/turnstile/v0/api.js';
    public const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    /**
     * Pages whose templates can hold a create-account form.
     */
    public const PAGES = ['create_account', 'login'];

    /**
     * Cloudflare error codes that mean the store's own configuration is
     * wrong, not the visitor.
     */
    public const CONFIG_ERRORS = ['missing-input-secret', 'invalid-input-secret'];

    public function __construct()
    {
        $this->attach($this, [
            'NOTIFY_HTML_HEAD_END',
            'NOTIFY_CREATE_ACCOUNT_VALIDATION_CHECK',
        ]);
    }

    public static function isActive(): bool
    {
        return defined('TURNSTILE_STATUS') && TURNSTILE_STATUS === 'true'
            && defined('TURNSTILE_SITE_KEY') && trim(TURNSTILE_SITE_KEY) !== ''
            && defined('TURNSTILE_SECRET_KEY') && trim(TURNSTILE_SECRET_KEY) !== '';
    }

    public function updateNotifyHtmlHeadEnd(&$class, $eventID, $pageBase = '')
    {
        if (!self::isActive()) {
            return;
        }
        $page = $GLOBALS['current_page_base'] ?? $pageBase;
        if (!in_array($page, self::PAGES, true)) {
            return;
        }

        $flags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES;
        $siteKey = json_encode(trim(TURNSTILE_SITE_KEY), $flags);
        $scriptUrl = json_encode(self::SCRIPT_URL, $flags);

        // A form that creates an account posts to create_account with a
        // hidden action=process: the Create Account page, the Login page's
        // inline form and OPC's registration form all do. The widget goes
        // just above the submit button's row; Cloudflare's implicit render
        // adds the cf-turnstile-response field inside it.
        echo <<<HTML
<script>
document.addEventListener('DOMContentLoaded', function () {
    var placed = 0;
    document.querySelectorAll('form').forEach(function (form) {
        if ((form.getAttribute('action') || '').indexOf('create_account') === -1
            || !form.querySelector('input[name="action"][value="process"]')
            || form.querySelector('.cf-turnstile')) {
            return;
        }
        var box = document.createElement('div');
        box.className = 'cf-turnstile';
        box.setAttribute('data-sitekey', {$siteKey});
        box.setAttribute('data-theme', 'auto');
        box.style.margin = '1rem 0';
        var submit = form.querySelector('[type="submit"], input[type="image"]');
        if (submit) {
            var anchor = (submit.parentNode === form) ? submit : submit.parentNode;
            anchor.parentNode.insertBefore(box, anchor);
        } else {
            form.appendChild(box);
        }
        // The normal widget is a fixed 300px; below that use the compact one
        // (150px) rather than push the page sideways on a narrow phone.
        box.setAttribute('data-size', box.getBoundingClientRect().width < 300 ? 'compact' : 'normal');
        placed++;
    });
    if (placed > 0) {
        var s = document.createElement('script');
        s.src = {$scriptUrl};
        s.async = true;
        document.head.appendChild(s);
    }
});
</script>

HTML;
    }

    public function updateNotifyCreateAccountValidationCheck(&$class, $eventID, $unused, &$error, &$sendWelcomeEmail)
    {
        if (!self::isActive()) {
            return;
        }
        if ($this->verify($_POST['cf-turnstile-response'] ?? '')) {
            return;
        }

        $error = true;
        $message = defined('TURNSTILE_TEXT_ERROR')
            ? TURNSTILE_TEXT_ERROR
            : 'Please complete the security check above the Submit button and try again.';
        $GLOBALS['messageStack']->add('create_account', $message, 'error');
    }

    /**
     * True when the token passes, or when Cloudflare could not give an answer
     * about the visitor (unreachable, unreadable reply, bad Secret Key).
     */
    protected function verify($token): bool
    {
        // Cloudflare's tokens are at most 2048 characters.
        if (!is_string($token) || $token === '' || strlen($token) > 2048) {
            return false;
        }

        $fields = [
            'secret' => trim(TURNSTILE_SECRET_KEY),
            'response' => $token,
        ];
        $ip = function_exists('zen_get_ip_address') ? zen_get_ip_address() : ($_SERVER['REMOTE_ADDR'] ?? '');
        if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP) !== false) {
            $fields['remoteip'] = $ip;
        }

        $body = $this->post(http_build_query($fields));
        $reply = ($body === null) ? null : json_decode($body, true);
        if (!is_array($reply) || !array_key_exists('success', $reply)) {
            trigger_error('Turnstile: no usable reply from Cloudflare siteverify; the account was allowed without a check.', E_USER_WARNING);
            return true;
        }
        if ($reply['success'] === true) {
            return true;
        }

        $codes = (array)($reply['error-codes'] ?? []);
        if (array_intersect($codes, self::CONFIG_ERRORS) !== []) {
            trigger_error('Turnstile: Cloudflare rejected the Secret Key (' . implode(', ', $codes) . '); the account was allowed without a check. Fix it under Configuration, Cloudflare Turnstile.', E_USER_WARNING);
            return true;
        }

        return false;
    }

    /**
     * POST to siteverify. Returns the response body, or null on a transport
     * failure. Cloudflare answers 200 for a failed check too, with
     * success:false in the JSON, so the status code is not used.
     */
    protected function post(string $payload): ?string
    {
        if (function_exists('curl_init')) {
            $ch = curl_init(self::VERIFY_URL);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
            ]);
            $response = curl_exec($ch);
            unset($ch);

            return is_string($response) ? $response : null;
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => $payload,
                'timeout' => 10,
                'ignore_errors' => true,
            ],
        ]);
        $response = @file_get_contents(self::VERIFY_URL, false, $context);

        return is_string($response) ? $response : null;
    }
}
