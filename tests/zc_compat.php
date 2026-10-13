<?php
/**
 * Does the plugin only use what every declared release has? Reads the real
 * installs under F:\zclab\sites (read-only); skips with a note without them.
 */

require __DIR__ . '/_bootstrap.php';

$PLUGIN = ts_plugin_dir();
$manifest = require $PLUGIN . '/manifest.php';
$branchDirs = ['v200' => 'zc200', 'v210' => 'zc210', 'v220' => 'zc222', 'v230' => 'zc230', 'v300' => 'zc300'];
$LAB = 'F:/zclab/sites';

$calls = [];
$all = '';
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($PLUGIN, FilesystemIterator::SKIP_DOTS)) as $f) {
    if (strtolower($f->getExtension()) === 'php') {
        $src = file_get_contents($f->getPathname());
        $all .= $src;
        preg_match_all('~(?<![\w$>:])(zen_[a-z0-9_]+)\s*\(~', $src, $m);
        foreach ($m[1] as $fn) {
            $calls[$fn] = true;
        }
    }
}
$calls = array_keys($calls);
sort($calls);
echo '          zen_* functions called: ' . implode(', ', $calls) . "\n";

section('source rules (PHP 8.0 floor)');
$code = preg_replace('~"(?:\\\\.|[^"\\\\])*"|\'(?:\\\\.|[^\'\\\\])*\'|/\*.*?\*/|//[^\n]*~s', '""', $all);
check('no zen_config() (3.0.0 only)', strpos($code, 'zen_config(') === false);
check('no readonly / enum / never (PHP 8.1)', preg_match('~\b(readonly|enum)\s|:\s*never\b~', $code) === 0);
check('no curl_close() (deprecated in PHP 8.5)', strpos($code, 'curl_close(') === false);

$available = array_filter(array_map(static function ($b) use ($branchDirs, $LAB) {
    return isset($branchDirs[$b]) && is_dir($LAB . '/' . $branchDirs[$b]) ? $LAB . '/' . $branchDirs[$b] : null;
}, array_combine($manifest['zcVersions'], $manifest['zcVersions'])));
if ($available === []) {
    echo "          no Zen Cart installs under $LAB -- source checks only\n";
}

foreach ($available as $branch => $dir) {
    section("branch $branch");
    $defined = [];
    foreach ([$dir . '/includes/functions', $dir . '/includes/classes', $dir . '/admin/includes/functions', $dir . '/admin/includes/classes'] as $root) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)) as $f) {
            if (strtolower($f->getExtension()) === 'php') {
                preg_match_all('~function\s+(zen_[a-z0-9_]+)\s*\(~i', file_get_contents($f->getPathname()), $m);
                foreach ($m[1] as $fn) {
                    $defined[strtolower($fn)] = true;
                }
            }
        }
    }
    $missing = array_values(array_filter($calls, static function ($fn) use ($defined) {
        return !isset($defined[strtolower($fn)]);
    }));
    check("every zen_* function exists in $branch", $missing === []);
    foreach ($missing as $fn) {
        echo "          missing: $fn\n";
    }
    check("$branch: create_account fires NOTIFY_CREATE_ACCOUNT_VALIDATION_CHECK with \$error by reference", preg_match("~notify\('NOTIFY_CREATE_ACCOUNT_VALIDATION_CHECK',\s*\[\],\s*\\\$error~", file_get_contents($dir . '/includes/modules/create_account.php')) === 1);
    $contact = file_get_contents($dir . '/includes/modules/pages/contact_us/header_php.php');
    $posNotify = strpos($contact, "notify('NOTIFY_CONTACT_US_CAPTCHA_CHECK'");
    $posTest = strpos($contact, '$error === false', (int)$posNotify);
    check("$branch: contact_us checks \$error right after NOTIFY_CONTACT_US_CAPTCHA_CHECK, before sending", $posNotify !== false && $posTest !== false && $posTest > $posNotify && strpos(substr($contact, (int)$posNotify, (int)$posTest - (int)$posNotify), 'zen_mail') === false);
    check("$branch: contact form posts to contact_us with action=send", strpos(file_get_contents($dir . '/includes/templates/template_default/templates/tpl_contact_us_default.php'), "zen_href_link(FILENAME_CONTACT_US, 'action=send'") !== false);
    foreach (['template_default', 'responsive_classic'] as $tpl) {
        $h = $dir . '/includes/templates/' . $tpl . '/common/html_header.php';
        if (is_file($h)) {
            check("$branch: $tpl fires NOTIFY_HTML_HEAD_END", strpos(file_get_contents($h), 'NOTIFY_HTML_HEAD_END') !== false);
        }
    }
    $index = file_get_contents($dir . '/index.php');
    $loop = strpos($index, 'foreach ($directory_array as $value)');
    check("$branch: index.php loads page header_php files at top level, not in a function (so \$error is \$GLOBALS['error'])", $loop !== false && strpos(substr($index, 0, $loop), 'function ') === false && preg_match('~require\s*\(?\s*\$~', substr($index, $loop, 600)) === 1);
}

ts_done('compatible with every declared release');
