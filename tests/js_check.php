<?php
/**
 * The placement script: it parses, it can't be broken out of, and it only
 * touches the forms it's told about.
 */

require __DIR__ . '/_bootstrap.php';

define('IS_ADMIN_FLAG', false);
ts_define_environment();
$GLOBALS['zco_notifier'] = new TsNotifier();
ts_load_observer();

$forms = [
    ['actionHas' => 'create_account', 'inputName' => 'action', 'inputValue' => ['process', 'register'], 'actionParam' => ''],
    ['actionHas' => 'contact_us', 'inputName' => '', 'inputValue' => '', 'actionParam' => 'action=send'],
];
$out = zcObserverTurnstile::widgetScript('0x4AAA</script><script>alert(1)</script>', $forms);

section('emission');
check('one script block', substr_count($out, '<script>') === 1 && substr_count($out, '</script>') === 1);
check('a hostile site key cannot close the script', strpos($out, 'alert(1)</script>') === false);

section('the JavaScript parses');
$js = substr($out, strpos($out, '<script>') + 8);
$js = substr($js, 0, strpos($js, '</script>'));
$tmp = tempnam(sys_get_temp_dir(), 'tsjs') . '.js';
file_put_contents($tmp, $js);
$node = trim((string)shell_exec(stripos(PHP_OS, 'WIN') === 0 ? 'where node 2>NUL' : 'command -v node 2>/dev/null'));
if ($node !== '') {
    $result = trim((string)shell_exec('node --check ' . escapeshellarg($tmp) . ' 2>&1'));
    check('node --check accepts it', $result === '');
} else {
    echo "          node not found -- bracket balance only\n";
    $stripped = preg_replace('~"(?:\\\\.|[^"\\\\])*"|\'(?:\\\\.|[^\'\\\\])*\'|//[^\n]*~s', '', $js);
    check('braces balance', substr_count($stripped, '{') === substr_count($stripped, '}'));
}
unlink($tmp);

section('what it does');
check('waits for the DOM', strpos($js, "document.addEventListener('DOMContentLoaded'") !== false);
check('matches a form on its action, a query parameter and a hidden field', strpos($js, 'action.indexOf(f.actionHas)') !== false && strpos($js, 'action.indexOf(f.actionParam)') !== false && strpos($js, 'input[name="\' + f.inputName') !== false);
check('a field value may be one of a list (process or register)', strpos($js, 'var wanted = [].concat(f.inputValue);') !== false && strpos($js, 'wanted.indexOf(field.value) === -1') !== false);
check('never adds a second widget to a form', strpos($js, "if (form.querySelector('.cf-turnstile')) { return; }") !== false);
check('placed above the submit button', strpos($js, "form.querySelector('[type=\"submit\"], input[type=\"image\"]')") !== false && strpos($js, 'insertBefore(box, anchor)') !== false);
check('compact widget on a narrow column', strpos($js, "box.getBoundingClientRect().width < 300 ? 'compact' : 'normal'") !== false);
check('Cloudflare\'s script loaded once, only when a widget was placed', strpos($js, 'if (placed > 0 && !document.querySelector') !== false);
check('no innerHTML, eval or document.write', preg_match('~innerHTML|outerHTML|insertAdjacentHTML|document\.write|\beval\(~', $js) === 0);
check('no globals', preg_match('~\bwindow\.[a-zA-Z_]+\s*=(?!=)~', $js) === 0);

ts_done('placement script is sound');
