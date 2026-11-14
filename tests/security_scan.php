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

// The admin ReadMe page is the one file allowed to read a local file and echo a
// variable; what it reads and echoes is pinned down by its own checks below.
$READMEPAGE = 'admin/turnstile_readme.php';
$readmePageSrc = (string)file_get_contents($PLUGIN . '/' . $READMEPAGE);
$notReadmePage = array_values(array_filter($files, static function ($p) use ($READMEPAGE) {
    return rel($p) !== $READMEPAGE;
}));
check('the ReadMe page is the only file set aside, and it is there', count($files) - count($notReadmePage) === 1 && $readmePageSrc !== '');

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
report('remote calls only in the observer\'s post()', scan($notReadmePage,'~\b(unserialize|file_get_contents|curl_init|fsockopen)\s*\(~i', [
    '~auto\.turnstile\.php:\s*(if \(function_exists\(\'curl_init\'\)\)|\$ch = curl_init\(self::VERIFY_URL\);|\$response = @file_get_contents\(self::VERIFY_URL, false, \$context\);)~',
    '~ScriptedInstaller\.php:.*strpos\(\(string\)file_get_contents\(\$file\)~',
]));
check('the one exception, the admin ReadMe page, reads only its own README.md (a fixed local path)',
    substr_count($readmePageSrc, 'file_get_contents(') === 1
    && strpos($readmePageSrc, '$tsReadmeFile = dirname(__DIR__) . \'/README.md\';') !== false
    && strpos($readmePageSrc, 'file_get_contents($tsReadmeFile)') !== false
    && preg_match('~\b(unserialize|curl_init|curl_exec|fsockopen|stream_socket_client|fopen|readfile|include|include_once|require_once)\s*\(~i', $readmePageSrc) === 0
    && substr_count($readmePageSrc, 'require ') === 1 && strpos($readmePageSrc, "require 'includes/application_top.php';") !== false
    && preg_match('~\$_(GET|POST|REQUEST|COOKIE|SERVER|FILES)~', $readmePageSrc) === 0);
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
report('no variable echoed outside the ReadMe page', scan($notReadmePage, '~\becho\s+\$[a-z_]~i'));
check('the one exception, the admin ReadMe page, echoes only the escape-first Markdown render',
    substr_count($readmePageSrc, 'echo ') === 1 && strpos($readmePageSrc, '<?php echo $tsReadmeHtml; ?>') !== false
    && strpos($readmePageSrc, '$text = htmlspecialchars((string)$text, ENT_QUOTES, \'UTF-8\');') !== false
    && preg_match('~\bprint(f|_r)?\s*\(|\bprint\s~', $readmePageSrc) === 0 && stripos($readmePageSrc, '<script') === false);
check('the ReadMe page is behind the login and for superusers only',
    strpos($readmePageSrc, "require 'includes/application_top.php';") !== false
    && strpos($readmePageSrc, "require 'includes/application_top.php';") < strpos($readmePageSrc, "if (!function_exists('zen_is_superuser') || !zen_is_superuser())")
    && strpos($readmePageSrc, "zen_redirect(zen_href_link(FILENAME_DENIED, '', 'SSL'));") < strpos($readmePageSrc, 'file_get_contents('));
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

section('license');
check('the readme names the GPL license', preg_match('~GNU General Public License, version 2~', (string)file_get_contents($PLUGIN . '/README.md')) === 1);
check('every PHP file carries the GPL v2 header', count(array_filter($files, static function ($p) {
    return substr($p, -4) === '.php' && strpos(file_get_contents($p), 'https://www.gnu.org/licenses/old-licenses/gpl-2.0.html GNU General Public License v2.0') === false;
})) === 0);

ts_done('security scan clean');
