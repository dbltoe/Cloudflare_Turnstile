<?php
/**
 * The storefront observer: where the widget is placed, what each form's
 * check does with every kind of Cloudflare answer, and the add-on seams.
 */

require __DIR__ . '/_bootstrap.php';

define('IS_ADMIN_FLAG', false);
ts_define_environment();
$GLOBALS['zco_notifier'] = new TsNotifier();
ts_load_observer();

function ts_fresh()
{
    base::reset();
    TsObserver::resetForms();
    TsObserver::$replies = [];
    TsObserver::$sent = [];
    $GLOBALS['messageStack'] = new TsMessageStack();
    $GLOBALS['zco_notifier'] = new TsNotifier();
    $obs = new TsObserver();
    return $obs;
}

function ts_head($page)
{
    $GLOBALS['current_page_base'] = $page;
    ob_start();
    $GLOBALS['zco_notifier']->notify('NOTIFY_HTML_HEAD_END', $page);
    return ob_get_clean();
}

section('active only when switched on with both keys');
check('active with the test settings', zcObserverTurnstile::isActive());

section('where the widget is placed');
ts_fresh();
$out = ts_head('create_account');
check('script printed on create_account', strpos($out, '<script>') !== false && strpos($out, 'challenges.cloudflare.com/turnstile/v0/api.js') !== false);
check('the site key goes to the page, the secret key never does', strpos($out, '0x4AAAAAAAsiteKEY') !== false && strpos($out, 'secretKEY') === false);
check('the create-account form is recognized by its action and action=process (core) or action=register (One Page Checkout)', strpos($out, '"actionHas":"create_account","inputName":"action","inputValue":["process","register"]') !== false);
check('login page too (its new-customer form)', strpos(ts_head('login'), 'create_account') !== false);
$contact = ts_head('contact_us');
check('contact_us page gets the Contact Us form', strpos($contact, '"actionHas":"contact_us"') !== false && strpos($contact, '"actionParam":"action=send"') !== false);
check('nothing on an unrelated page', ts_head('product_info') === '');

section('the per-form settings');
$probe = new class {
    public function run()
    {
        $script = '<?php define("IS_ADMIN_FLAG", false); require ' . var_export(__DIR__ . '/_bootstrap.php', true) . ';'
            . 'ts_define_environment(["TURNSTILE_CONTACT_US" => "false"]); $GLOBALS["zco_notifier"] = new TsNotifier(); ts_load_observer();'
            . '$o = new TsObserver(); $GLOBALS["messageStack"] = new TsMessageStack(); $GLOBALS["current_page_base"] = "contact_us";'
            . 'ob_start(); $GLOBALS["zco_notifier"]->notify("NOTIFY_HTML_HEAD_END", "contact_us"); $h = ob_get_clean();'
            . '$GLOBALS["error"] = false; $_POST = []; $GLOBALS["zco_notifier"]->notify("NOTIFY_CONTACT_US_CAPTCHA_CHECK", $_POST);'
            . 'echo json_encode(["head" => $h, "error" => $GLOBALS["error"], "sent" => count(TsObserver::$sent)]);';
        $tmp = tempnam(sys_get_temp_dir(), 'tsc') . '.php';
        file_put_contents($tmp, $script);
        $out = shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($tmp));
        unlink($tmp);
        return json_decode((string)$out, true);
    }
};
$r = $probe->run();
check('Protect Contact Us = false: no widget on contact_us and no check', is_array($r) && $r['head'] === '' && $r['error'] === false && $r['sent'] === 0);
check('a form whose setting is not defined yet (mid-upgrade) counts as on', (static function () {
    ts_fresh();
    $addon = new class {
        public function updateNotifyTurnstileForms(&$class, $eventID, $p1, &$forms)
        {
            $forms['later'] = ['setting' => 'TURNSTILE_NOT_DEFINED_YET', 'pages' => ['x'], 'actionHas' => 'x', 'inputName' => '', 'inputValue' => '', 'actionParam' => '', 'errorStack' => 'x'];
        }
    };
    $GLOBALS['zco_notifier']->attach($addon, ['NOTIFY_TURNSTILE_FORMS']);
    TsObserver::resetForms();
    return !defined('TURNSTILE_NOT_DEFINED_YET') && zcObserverTurnstile::formEnabled('later');
})());
check('an unknown form is never enabled', !zcObserverTurnstile::formEnabled('no_such_form'));

section('create account: every kind of answer');
$cases = [
    ['no token at all', null, [], true, 0],
    ['an over-long token', str_repeat('a', 2049), [], true, 0],
    ['Cloudflare says success', 'tok-ok', ['{"success":true}'], false, 1],
    ['Cloudflare says the token is invalid', 'tok-bad', ['{"success":false,"error-codes":["invalid-input-response"]}'], true, 1],
    ['Cloudflare says the token timed out or was reused', 'tok-dup', ['{"success":false,"error-codes":["timeout-or-duplicate"]}'], true, 1],
    ['the Secret Key is wrong: allowed (fail open)', 'tok', ['{"success":false,"error-codes":["invalid-input-secret"]}'], false, 1],
    ['Cloudflare unreachable: allowed (fail open)', 'tok', [null], false, 1],
    ['a reply that isn\'t JSON: allowed (fail open)', 'tok', ['<html>502</html>'], false, 1],
];
foreach ($cases as [$label, $token, $replies, $expectError, $calls]) {
    ts_fresh();
    TsObserver::$replies = $replies;
    $_POST = ($token === null) ? [] : ['cf-turnstile-response' => $token];
    $error = false;
    $send = true;
    $warned = false;
    set_error_handler(static function ($no, $str) use (&$warned) {
        $warned = ($no === E_USER_WARNING && strpos($str, 'Turnstile:') === 0);
        return true;
    });
    $GLOBALS['zco_notifier']->notify('NOTIFY_CREATE_ACCOUNT_VALIDATION_CHECK', [], $error, $send);
    restore_error_handler();
    $msg = $GLOBALS['messageStack']->messages[0] ?? null;
    check("$label: " . ($expectError ? 'refused' : 'allowed'), $error === $expectError && count(TsObserver::$sent) === $calls);
    if ($expectError) {
        check("$label: message on the create_account stack", $msg !== null && $msg['class'] === 'create_account' && $msg['message'] === TURNSTILE_TEXT_ERROR);
    }
    if (in_array($replies, [[null], ['<html>502</html>'], ['{"success":false,"error-codes":["invalid-input-secret"]}']], true)) {
        check("$label: a warning goes to the log", $warned);
    }
}

section('what is sent to Cloudflare');
ts_fresh();
TsObserver::$replies = ['{"success":true}'];
$_POST = ['cf-turnstile-response' => 'tok-ok'];
$_SERVER['REMOTE_ADDR'] = '203.0.113.9';
$error = false;
$send = true;
$GLOBALS['zco_notifier']->notify('NOTIFY_CREATE_ACCOUNT_VALIDATION_CHECK', [], $error, $send);
$sent = TsObserver::$sent[0] ?? [];
check('secret, response and the visitor IP', ($sent['secret'] ?? '') === '0x4AAAAAAAsecretKEYdoNotLeak' && ($sent['response'] ?? '') === 'tok-ok' && ($sent['remoteip'] ?? '') === '203.0.113.9');
$_SERVER['REMOTE_ADDR'] = 'not-an-ip';
TsObserver::$replies = ['{"success":true}'];
TsObserver::$sent = [];
TsObserver::verifyToken('tok-ok');
check('an invalid IP is left out', count(TsObserver::$sent) === 1 && !isset(TsObserver::$sent[0]['remoteip']));
$src = file_get_contents(ts_plugin_dir() . '/catalog/includes/classes/observers/auto.turnstile.php');
check('siteverify is called over https with certificate checks', strpos($src, "VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify'") !== false && strpos($src, 'CURLOPT_SSL_VERIFYPEER => true') !== false);

section('Contact Us');
ts_fresh();
TsObserver::$replies = ['{"success":false,"error-codes":["invalid-input-response"]}'];
$_POST = ['cf-turnstile-response' => 'tok-bad', 'contactname' => 'Bot'];
$GLOBALS['error'] = false;
$GLOBALS['zco_notifier']->notify('NOTIFY_CONTACT_US_CAPTCHA_CHECK', $_POST);
check('a failed check sets the page\'s $error, so core sends nothing', $GLOBALS['error'] === true);
check('message on the contact stack', ($GLOBALS['messageStack']->messages[0]['class'] ?? '') === 'contact');
ts_fresh();
TsObserver::$replies = ['{"success":true}'];
$_POST = ['cf-turnstile-response' => 'tok-ok'];
$GLOBALS['error'] = false;
$GLOBALS['zco_notifier']->notify('NOTIFY_CONTACT_US_CAPTCHA_CHECK', $_POST);
check('a passed check leaves $error alone', $GLOBALS['error'] === false && $GLOBALS['messageStack']->messages === []);

section('Ask a Question and Write a Review');
foreach ([
    ['ask_a_question', 'NOTIFY_ASK_A_QUESTION_CAPTCHA_CHECK', 'contact', '"actionHas":"ask_a_question"', '"actionParam":"action=send"'],
    ['product_reviews_write', 'NOTIFY_REVIEWS_WRITE_CAPTCHA_CHECK', 'review_text', '"actionHas":"product_reviews_write"', '"actionParam":"action=process"'],
] as $case) {
    list($page, $event, $stack, $actionHas, $actionParam) = $case;

    ts_fresh();
    $head = ts_head($page);
    check("$page: the widget script names the form and its action", strpos($head, $actionHas) !== false && strpos($head, $actionParam) !== false);

    TsObserver::$replies = ['{"success":false,"error-codes":["invalid-input-response"]}'];
    $_POST = ['cf-turnstile-response' => 'tok-bad'];
    $GLOBALS['error'] = false;
    $GLOBALS['zco_notifier']->notify($event, $_POST);
    check("$page: a failed check sets the page's \$error, so core keeps nothing", $GLOBALS['error'] === true);
    check("$page: the refusal lands on the $stack stack", ($GLOBALS['messageStack']->messages[0]['class'] ?? '') === $stack);

    ts_fresh();
    TsObserver::$replies = ['{"success":true}'];
    $_POST = ['cf-turnstile-response' => 'tok-ok'];
    $GLOBALS['error'] = false;
    $GLOBALS['zco_notifier']->notify($event, $_POST);
    check("$page: a passed check leaves \$error alone", $GLOBALS['error'] === false && $GLOBALS['messageStack']->messages === []);
}

section('the add-on seams');
ts_fresh();
$addon = new class {
    public $seen = [];
    public function updateNotifyTurnstileForms(&$class, $eventID, $p1, &$forms)
    {
        $forms['review'] = ['setting' => '', 'pages' => ['product_reviews_write'], 'actionHas' => 'product_reviews_write', 'inputName' => '', 'inputValue' => '', 'actionParam' => 'action=process', 'errorStack' => 'review_text'];
    }
    public function updateNotifyTurnstileChecked(&$class, $eventID, $result)
    {
        $this->seen[] = $result;
    }
};
$GLOBALS['zco_notifier']->attach($addon, ['NOTIFY_TURNSTILE_FORMS', 'NOTIFY_TURNSTILE_CHECKED']);
TsObserver::resetForms();
check('NOTIFY_TURNSTILE_FORMS adds a form', isset(zcObserverTurnstile::forms()['review']) && zcObserverTurnstile::formEnabled('review'));
check('the added form gets the widget on its page', strpos(ts_head('product_reviews_write'), '"actionHas":"product_reviews_write"') !== false);
TsObserver::$replies = ['{"success":false,"error-codes":["invalid-input-response"]}'];
check('verifyToken() refuses for an add-on', TsObserver::verifyToken('tok-bad', 'review') === false);
check('NOTIFY_TURNSTILE_CHECKED reports form, result and codes', ($addon->seen[0] ?? null) === ['passed' => false, 'form' => 'review', 'codes' => ['invalid-input-response']]);
zcObserverTurnstile::refuse('review');
check('refuse() uses the form\'s own message area', (end($GLOBALS['messageStack']->messages)['class'] ?? '') === 'review_text');

ts_done('observer behaves');
