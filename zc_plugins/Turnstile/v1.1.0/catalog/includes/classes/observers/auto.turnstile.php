<?php
/**
 * Cloudflare Turnstile -- storefront observer.
 *
 * Auto-loaded and instantiated by includes/init_includes/init_observers.php;
 * the class name must stay 'zcObserver' . camelize(<filename>), and nothing
 * may list this file in an auto_loader as well.
 *
 *   NOTIFY_HTML_HEAD_END                    On a page that holds a protected
 *                                           form, prints a small script that
 *                                           puts the widget into each such
 *                                           form, then loads Cloudflare's
 *                                           api.js. No template file is
 *                                           changed.
 *   NOTIFY_CREATE_ACCOUNT_VALIDATION_CHECK  Fired by core create_account.php
 *                                           and by One Page Checkout's
 *                                           registration page. Sets $error
 *                                           when the check is missing or
 *                                           fails, so no account is created.
 *   NOTIFY_CONTACT_US_CAPTCHA_CHECK         Fired by core's contact_us page
 *                                           (1.5.8 through 3.0.0). Sets the
 *                                           page's $error, so no email is
 *                                           sent.
 *
 * Each protected form is described once in forms(): the pages it can appear
 * on, how the script recognizes it, and the messageStack area its error goes
 * to. An add-on (Turnstile Pro) adds forms through NOTIFY_TURNSTILE_FORMS and
 * checks its own submissions with zcObserverTurnstile::verifyToken().
 *
 * If Cloudflare can't be reached, or rejects the Secret Key itself, the
 * submission is allowed and a myDEBUG log is written: a Cloudflare outage or
 * a typo in the admin must never stop real customers.
 *
 * @package  Turnstile
 * @license  https://www.gnu.org/licenses/old-licenses/gpl-2.0.html GNU Public License V2.0
 */

if (!defined('IS_ADMIN_FLAG')) {
    die('Illegal Access');
}

class zcObserverTurnstile extends base
{
    public const VERSION = '1.1.0';
    public const SCRIPT_URL = 'https://challenges.cloudflare.com/turnstile/v0/api.js';
    public const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
    public const RESPONSE_FIELD = 'cf-turnstile-response';

    /**
     * Cloudflare error codes that mean the store's own configuration is
     * wrong, not the visitor.
     */
    public const CONFIG_ERRORS = ['missing-input-secret', 'invalid-input-secret'];

    /** @var array|null */
    protected static $formsCache = null;

    public function __construct()
    {
        $this->attach($this, [
            'NOTIFY_HTML_HEAD_END',
            'NOTIFY_CREATE_ACCOUNT_VALIDATION_CHECK',
            'NOTIFY_CONTACT_US_CAPTCHA_CHECK',
        ]);
    }

    /** True once switched on and both keys are filled in. */
    public static function isActive(): bool
    {
        return defined('TURNSTILE_STATUS') && TURNSTILE_STATUS === 'true'
            && defined('TURNSTILE_SITE_KEY') && trim(TURNSTILE_SITE_KEY) !== ''
            && defined('TURNSTILE_SECRET_KEY') && trim(TURNSTILE_SECRET_KEY) !== '';
    }

    /**
     * The protected forms, keyed by name. Each entry:
     *   setting      the configuration key that switches it on ('true')
     *   pages        main_page values whose templates can hold the form
     *   actionHas    text the form's action URL must contain
     *   inputName,   a field the form must carry, with one of these values
     *   inputValue   (a string or a list; both '' when the action URL is enough)
     *   actionParam  a query parameter the action URL must carry ('' for none)
     *   errorStack   the messageStack area that shows the refusal
     *
     * The create-account form is recognized by its action field: 'process'
     * on core's Create Account page and the Login page's inline form,
     * 'register' on One Page Checkout's account-registration form, which
     * replaces core's form when OPC's registered accounts are on and fires
     * the same validation notifier.
     */
    public static function forms(): array
    {
        if (self::$formsCache !== null) {
            return self::$formsCache;
        }
        $forms = [
            'create_account' => [
                'setting' => 'TURNSTILE_CREATE_ACCOUNT',
                'pages' => ['create_account', 'login'],
                'actionHas' => 'create_account',
                'inputName' => 'action',
                'inputValue' => ['process', 'register'],
                'actionParam' => '',
                'errorStack' => 'create_account',
            ],
            'contact_us' => [
                'setting' => 'TURNSTILE_CONTACT_US',
                'pages' => ['contact_us'],
                'actionHas' => 'contact_us',
                'inputName' => '',
                'inputValue' => '',
                'actionParam' => 'action=send',
                'errorStack' => 'contact',
            ],
        ];
        $GLOBALS['zco_notifier']->notify('NOTIFY_TURNSTILE_FORMS', [], $forms);
        return self::$formsCache = $forms;
    }

    /** True when the form exists and its setting is on (a form with no setting is always on). */
    public static function formEnabled(string $name): bool
    {
        $forms = self::forms();
        if (!isset($forms[$name])) {
            return false;
        }
        $setting = (string)($forms[$name]['setting'] ?? '');
        if ($setting === '') {
            return true;
        }
        // A setting that isn't defined yet (an upgrade not yet run) counts as on,
        // matching v1.0.0, which always protected the create-account form.
        return !defined($setting) || constant($setting) === 'true';
    }

    public function updateNotifyHtmlHeadEnd(&$class, $eventID, $pageBase = '')
    {
        if (!self::isActive()) {
            return;
        }
        $page = (string)($GLOBALS['current_page_base'] ?? $pageBase);
        $here = [];
        foreach (self::forms() as $name => $form) {
            if (self::formEnabled($name) && in_array($page, (array)$form['pages'], true)) {
                $here[] = [
                    'actionHas' => (string)$form['actionHas'],
                    'inputName' => (string)$form['inputName'],
                    'inputValue' => array_values(array_map('strval', (array)$form['inputValue'])),
                    'actionParam' => (string)$form['actionParam'],
                ];
            }
        }
        if ($here === []) {
            return;
        }
        echo self::widgetScript(trim(TURNSTILE_SITE_KEY), $here);
    }

    /**
     * The script that places the widget. Public so an add-on can print it on
     * a page this plugin doesn't know about.
     *
     * Every value goes through json_encode with the HEX flags, so nothing
     * from the settings can close the script block.
     */
    public static function widgetScript(string $siteKey, array $forms): string
    {
        $flags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES;
        $config = json_encode([
            'siteKey' => $siteKey,
            'scriptUrl' => self::SCRIPT_URL,
            'forms' => array_values($forms),
        ], $flags);

        // The widget goes just above the submit button's row; Cloudflare's
        // implicit render adds the cf-turnstile-response field inside it.
        return <<<HTML
<script>
document.addEventListener('DOMContentLoaded', function () {
    var cfg = {$config};
    var boxes = [];
    function matches(form, f) {
        var action = form.getAttribute('action') || '';
        if (action.indexOf(f.actionHas) === -1) { return false; }
        if (f.actionParam && action.indexOf(f.actionParam) === -1) { return false; }
        if (f.inputName) {
            var field = form.querySelector('input[name="' + f.inputName + '"]');
            var wanted = [].concat(f.inputValue);
            if (!field || wanted.indexOf(field.value) === -1) { return false; }
        }
        return true;
    }
    document.querySelectorAll('form').forEach(function (form) {
        if (form.querySelector('.cf-turnstile')) { return; }
        var hit = cfg.forms.some(function (f) { return matches(form, f); });
        if (!hit) { return; }
        var box = document.createElement('div');
        box.className = 'cf-turnstile';
        box.setAttribute('data-sitekey', cfg.siteKey);
        box.setAttribute('data-theme', 'auto');
        box.style.margin = '1rem 0';
        var submit = form.querySelector('[type="submit"], input[type="image"]');
        if (submit) {
            var anchor = (submit.parentNode === form) ? submit : submit.parentNode;
            anchor.parentNode.insertBefore(box, anchor);
        } else {
            form.appendChild(box);
        }
        boxes.push(box);
    });
    if (boxes.length === 0) { return; }
    // The normal widget is a fixed 300px; below that use the compact one
    // (150px) rather than push the page sideways on a narrow phone. The size
    // has to be set before Cloudflare renders, and responsive_classic keeps
    // the whole page display:none until its own ready handler runs, when
    // every box measures 0. So wait (up to 2 seconds) for the boxes to be
    // laid out; if they never are, go by the window's width.
    var waited = 0;
    function render() {
        var unmeasured = boxes.some(function (b) { return b.getBoundingClientRect().width === 0; });
        if (unmeasured && waited < 2000) {
            waited += 50;
            setTimeout(render, 50);
            return;
        }
        boxes.forEach(function (b) {
            var width = b.getBoundingClientRect().width || document.documentElement.clientWidth;
            b.setAttribute('data-size', width < 300 ? 'compact' : 'normal');
        });
        if (!document.querySelector('script[src="' + cfg.scriptUrl + '"]')) {
            var s = document.createElement('script');
            s.src = cfg.scriptUrl;
            s.async = true;
            document.head.appendChild(s);
        }
    }
    render();
});
</script>

HTML;
    }

    public function updateNotifyCreateAccountValidationCheck(&$class, $eventID, $unused, &$error, &$sendWelcomeEmail)
    {
        if (!self::isActive() || !self::formEnabled('create_account')) {
            return;
        }
        if (self::verifyToken($_POST[self::RESPONSE_FIELD] ?? '', 'create_account')) {
            return;
        }
        $error = true;
        self::refuse('create_account');
    }

    /**
     * Core's contact_us page runs in the global scope and passes only $_POST,
     * so the refusal is made through the page's own $error variable, which
     * core checks right after this notifier before sending anything.
     */
    public function updateNotifyContactUsCaptchaCheck(&$class, $eventID, $post = [])
    {
        if (!self::isActive() || !self::formEnabled('contact_us')) {
            return;
        }
        if (self::verifyToken($_POST[self::RESPONSE_FIELD] ?? '', 'contact_us')) {
            return;
        }
        $GLOBALS['error'] = true;
        self::refuse('contact_us');
    }

    /** Put the form's refusal message on its messageStack area. */
    public static function refuse(string $formName): void
    {
        $forms = self::forms();
        $stack = (string)($forms[$formName]['errorStack'] ?? $formName);
        $message = defined('TURNSTILE_TEXT_ERROR')
            ? TURNSTILE_TEXT_ERROR
            : 'Please complete the security check above the Submit button and try again.';
        if (isset($GLOBALS['messageStack']) && is_object($GLOBALS['messageStack'])) {
            $GLOBALS['messageStack']->add($stack, $message, 'error');
        }
    }

    /**
     * True when the token passes, or when Cloudflare couldn't give an answer
     * about the visitor (unreachable, unreadable reply, bad Secret Key).
     * False when the token is missing, malformed or rejected.
     */
    public static function verifyToken($token, string $formName = ''): bool
    {
        // Cloudflare's tokens are at most 2048 characters.
        if (!is_string($token) || $token === '' || strlen($token) > 2048) {
            self::announce(false, $formName, ['missing-input-response']);
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

        $body = static::post(http_build_query($fields));
        $reply = ($body === null) ? null : json_decode($body, true);
        if (!is_array($reply) || !array_key_exists('success', $reply)) {
            trigger_error('Turnstile: no usable reply from Cloudflare siteverify; the ' . ($formName !== '' ? $formName : 'form') . ' submission was allowed without a check.', E_USER_WARNING);
            self::announce(true, $formName, ['no-reply']);
            return true;
        }
        if ($reply['success'] === true) {
            self::announce(true, $formName, []);
            return true;
        }

        $codes = array_map('strval', (array)($reply['error-codes'] ?? []));
        if (array_intersect($codes, self::CONFIG_ERRORS) !== []) {
            trigger_error('Turnstile: Cloudflare rejected the Secret Key (' . implode(', ', $codes) . '); the submission was allowed without a check. Fix it under Configuration, Cloudflare Turnstile.', E_USER_WARNING);
            self::announce(true, $formName, $codes);
            return true;
        }

        self::announce(false, $formName, $codes);
        return false;
    }

    /**
     * Tells any listener (Turnstile Pro's report) how a check went.
     */
    protected static function announce(bool $passed, string $formName, array $codes): void
    {
        if (isset($GLOBALS['zco_notifier']) && is_object($GLOBALS['zco_notifier'])) {
            $result = ['passed' => $passed, 'form' => $formName, 'codes' => $codes];
            $GLOBALS['zco_notifier']->notify('NOTIFY_TURNSTILE_CHECKED', $result);
        }
    }

    /**
     * POST to siteverify. Returns the response body, or null on a transport
     * failure. Cloudflare answers 200 for a failed check too, with
     * success:false in the JSON, so the status code isn't used.
     */
    protected static function post(string $payload): ?string
    {
        if (function_exists('curl_init')) {
            $ch = curl_init(self::VERIFY_URL);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
            ]);
            $response = curl_exec($ch);
            // No curl_close(): a no-op since PHP 8.0 and deprecated in 8.5.
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
