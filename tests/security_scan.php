<?php
/**
 * The pre-release security scan, as a test. A hit is a question; where a
 * pattern stands legitimately, the exemption says why.
 */

require __DIR__ . '/_bootstrap.php';

$PLUGIN = ts_plugin_dir();
$files = [];
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($PLUGIN, FilesystemIterator::SKIP_DOTS)) as $f) {
    $files[] = str_replace('\\', '/', $f->getPathname());
}

function rel($p)
{
    global $PLUGIN;
    return substr($p, strlen($PLUGIN) + 1);
}

function scan(array $files, $pattern, array $exempt = [], $exts = ['php'])
{
    $hits = [];
    foreach ($files as $path) {
        if (!in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), $exts, true)) {
            continue;
        }
        foreach (file($path, FILE_IGNORE_NEW_LINES) as $i => $line) {
            if (preg_match($pattern, $line) !== 1) {
                continue;
            }
            foreach ($exempt as $ex) {
                if (preg_match($ex, rel($path) . ':' . $line) === 1) {
                    continue 2;
                }
            }
            $hits[] = rel($path) . ':' . ($i + 1) . '  ' . trim($line);
        }
    }
    return $hits;
}

function report($label, array $hits)
{
    check($label, $hits === []);
    foreach (array_slice($hits, 0, 6) as $h) {
        echo "          $h\n";
    }
}

$obs = file_get_contents($PLUGIN . '/catalog/includes/classes/observers/auto.turnstile.php');
$inst = file_get_contents($PLUGIN . '/Installer/ScriptedInstaller.php');
echo 'scanning ' . count($files) . " shipped files\n";

section('dynamic code, obfuscation, DOM injection');
report('no eval/exec/system/shell_exec/proc_open/assert', scan($files, '~(?<![a-z_$>:])(eval|exec|system|passthru|shell_exec|proc_open|create_function|assert|popen)\s*\(~i'));
report('no base64/gzinflate/str_rot13', scan($files, '~\b(base64_(en|de)code|gzinflate|gzuncompress|str_rot13)\s*\(~i'));
report('no innerHTML/outerHTML/document.write', scan($files, '~\b(innerHTML|outerHTML|document\.write|insertAdjacentHTML)\b~i', [], ['php', 'html']));

section('remote calls');
/* The siteverify POST is the plugin's purpose; curl, with a file_get_contents
 * fallback for hosts without curl, both to Cloudflare's fixed URL only. */
report('remote calls only in the observer\'s post()', scan($files, '~\b(unserialize|file_get_contents|curl_init|fsockopen)\s*\(~i', [
    '~auto\.turnstile\.php:\s*(if \(function_exists\(\'curl_init\'\)\)|\$ch = curl_init\(self::VERIFY_URL\);|\$response = @file_get_contents\(self::VERIFY_URL, false, \$context\);)~',
    '~ScriptedInstaller\.php:.*strpos\(\(string\)file_get_contents\(\$file\)~',
]));
preg_match_all('~https://[a-z0-9./_-]+~i', preg_replace('~/\*.*?\*/|(?<=\s)//[^\n]*~s', '', $obs), $m);
$urls = array_values(array_unique($m[0]));
sort($urls);
check('the observer only knows Cloudflare\'s two Turnstile URLs', $urls === ['https://challenges.cloudflare.com/turnstile/v0/api.js', 'https://challenges.cloudflare.com/turnstile/v0/siteverify']);
check('certificate checks on', strpos($obs, 'CURLOPT_SSL_VERIFYPEER => true') !== false && strpos($obs, 'CURLOPT_SSL_VERIFYHOST => 2') !== false);
check('timeouts on both paths', strpos($obs, 'CURLOPT_TIMEOUT => 10') !== false && strpos($obs, "'timeout' => 10") !== false);

section('the Secret Key');
check('masked in the admin', preg_match("~'TURNSTILE_SECRET_KEY'.*?'use_function' => 'zen_cfg_password_display'~s", $inst) === 1);
$scriptPart = substr($obs, strpos($obs, 'public static function widgetScript'), 2500);
check('never in the page: the widget script gets the site key only', strpos($scriptPart, 'SECRET') === false && strpos($obs, "'secret' => trim(TURNSTILE_SECRET_KEY)") !== false);
check('never in a log message', preg_match('~trigger_error\([^;]*SECRET~', $obs) === 0);

section('output and input');
check('the page script is built with json_encode and the HEX flags', strpos($obs, 'JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT') !== false);
check('the token is length-checked before use', strpos($obs, 'strlen($token) > 2048') !== false);
check('the visitor IP is validated before it is sent', strpos($obs, 'filter_var($ip, FILTER_VALIDATE_IP)') !== false);
check('the template name from the database is whitelisted before it becomes a path', strpos($inst, "preg_match('/^[A-Za-z0-9_-]+\$/', (string)\$row->fields['template_dir'])") !== false);
report('no request data in SQL', scan($files, '~(SELECT|INSERT|UPDATE|DELETE|WHERE|VALUES)\b[^;]*\$_(GET|POST|REQUEST|COOKIE)~i'));
report('every table through a constant', scan($files, '~"\s*(SELECT|INSERT[A-Z ]*INTO|UPDATE|DELETE FROM)\b[^"]*\b(FROM|INTO|UPDATE)\s+[a-z_]{3,}\b~i'));

section('direct-access guards');
$noGuard = [];
foreach ($files as $p) {
    if (substr($p, -4) === '.php' && preg_match('~(manifest\.php|languages/)~', rel($p)) !== 1
        && strpos(implode("\n", array_slice(file($p), 0, 50)), "defined('IS_ADMIN_FLAG')") === false) {
        $noGuard[] = rel($p);
    }
}
report('every included file carries the guard', $noGuard);

ts_done('security scan clean');
