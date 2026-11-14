<?php
/**
 * manifest.php: the Plugin Manager panel and the release identity.
 *
 * Run with: not-installed (default) | settings
 *
 * "settings" is an admin read with the Configuration page registered (fake
 * db), so the Settings button is in the row. A constant can't be undefined,
 * so it runs in its own process; the default run starts it and folds its
 * result into this one.
 */

require __DIR__ . '/_bootstrap.php';

define('DIR_WS_CATALOG', '/shop/');

$state = $argv[1] ?? 'not-installed';
if ($state === 'settings') {
    define('IS_ADMIN_FLAG', true);
    define('TABLE_ADMIN_PAGES', 'zen_admin_pages');
    define('FILENAME_CONFIGURATION', 'configuration');
    $db = new TsDb();
    $db->answers["page_key = 'configTurnstile'"] = [
        ['main_page' => 'FILENAME_CONFIGURATION', 'page_params' => 'gID=42'],
    ];
    $GLOBALS['db'] = $db;
}

$PLUGIN = ts_plugin_dir();
$version = basename($PLUGIN);
$manifest = require $PLUGIN . '/manifest.php';
$desc = $manifest['pluginDescription'];
$src = file_get_contents($PLUGIN . '/manifest.php');

section("manifest shape (state: $state)");
foreach (['pluginVersion', 'pluginName', 'pluginDescription', 'pluginAuthor', 'pluginId', 'zcVersions', 'changelog', 'github_repo', 'pluginGroups'] as $key) {
    check("has $key", array_key_exists($key, $manifest));
}
check('pluginVersion matches the directory: ' . $version, $manifest['pluginVersion'] === $version);
check('the observer reports the same version', strpos(file_get_contents($PLUGIN . '/catalog/includes/classes/observers/auto.turnstile.php'), "VERSION = '" . ltrim($version, 'v') . "'") !== false);
check('changelog exists and names this version', is_file($PLUGIN . '/' . $manifest['changelog']) && strpos(file_get_contents($PLUGIN . '/' . $manifest['changelog']), $version) !== false);
check('declares 1.5.8 through 3.0', $manifest['zcVersions'] === ['v158', 'v200', 'v210', 'v220', 'v230', 'v300']);
check('author is the agreed string', $manifest['pluginAuthor'] === 'My Zen Cart Host (dbltoe)');
check('pluginId is a bare integer', is_int($manifest['pluginId']) && preg_match("~'pluginId' => \d+,~", file_get_contents($PLUGIN . '/manifest.php')) === 1);

section('panel buttons');
check('ReadMe opens the admin readme page in its own window, not a public file',
    strpos($desc, '<a href="index.php?cmd=turnstile_readme" target="_blank" rel="noopener noreferrer" class="btn btn-primary" role="button"') !== false
    && strpos($desc, 'readme.html') === false && strpos($desc, '/shop/') === false && strpos($desc, 'zc_plugins/') === false);
check('the page and the README.md it shows exist', is_file($PLUGIN . '/admin/turnstile_readme.php') && is_file($PLUGIN . '/README.md') && !is_file($PLUGIN . '/readme.html'));
check('no version-specific path is hard-coded in the manifest', strpos($src, 'zc_plugins/Turnstile/v') === false && strpos($src, 'DIR_WS_CATALOG') === false && strpos($src, 'readme.html') === false);
check('the ReadMe button is for superusers, asked only once admin access exists',
    strpos($src, "\$tsShowReadme = true;\nif (function_exists('zen_is_superuser') && defined('SUPERUSER_PROFILE')) {\n    \$tsShowReadme = zen_is_superuser();\n}") !== false
    && strpos($src, "(\$tsShowReadme ? \$tsButton(\$tsReadmeUrl, 'ReadMe', true) : '')") !== false
    && substr_count($src, 'zen_is_superuser()') === 1 + substr_count($src, 'zen_is_superuser() there is'));
check('GitHub button and github_repo agree', strpos($desc, 'href="' . $manifest['github_repo'] . '"') !== false && $manifest['github_repo'] === 'https://github.com/dbltoe/Cloudflare_Turnstile');
check('Forum Thread button links the opening-post permalink', strpos($desc, 'href="https://www.zen-cart.com/threads/207429?page=1#post-1347614"') !== false && strpos($desc, '>Forum Thread</a>') !== false);
$hasSettings = ($state === 'settings');
$buttons = $hasSettings ? 4 : 3;
check("all $buttons are styled as core buttons", substr_count($desc, 'class="btn btn-primary" role="button"') === $buttons);
preg_match_all('~role="button"[^>]*>([^<]+)</a>~', $desc, $labels);
check('the row reads ' . implode(' | ', $labels[1]), $labels[1] === ($hasSettings
    ? ['ReadMe', 'Settings', 'Forum Thread', 'GitHub']
    : ['ReadMe', 'Forum Thread', 'GitHub']));
check('they share a single row (nothing block-level between them)', preg_match('~>ReadMe</a>\s*<a~', $desc) === 1 && substr_count($desc, '<div') === 1);
check('equal 4px spacing and 8px side padding on every button', strpos($desc, 'padding:0 0 0 4px') !== false
    && substr_count($desc, 'style="margin:0 4px 0 0;padding-left:8px;padding-right:8px"') === $buttons);
check('the new-window buttons open safely (ReadMe, Forum Thread, GitHub)', substr_count($desc, 'target="_blank" rel="noopener noreferrer"') === 3 && substr_count($desc, 'target="_blank"') === 3);
check('no old labels left', strpos($desc, 'Read Me') === false && strpos($desc, 'Forum Support Thread') === false);

section('the Settings button');
if ($hasSettings) {
    check('links relatively to the store\'s own Configuration group', strpos($desc, 'href="index.php?cmd=configuration&amp;gID=42" class="btn btn-primary"') !== false);
    // ReadMe is an admin link too, but opens in its own window; Settings stays in this one.
    check('opens in the same tab (it is the admin itself)', preg_match('~href="index\.php\?cmd=configuration[^"]*" target=~', $desc) === 0
        && preg_match_all('~href="index\.php[^"]*" target=~', $desc) === 1);
    check('looked up by the installer\'s page key', count($db->matching("page_key = 'configTurnstile'")) === 1);
} else {
    check('absent when the page is not registered or this is no admin read', strpos($desc, '>Settings<') === false);
}
check('the lookup is guarded (admin, admin_pages table, a database object)',
    strpos($src, "defined('IS_ADMIN_FLAG') && IS_ADMIN_FLAG === true && defined('TABLE_ADMIN_PAGES')") !== false
    && strpos($src, "isset(\$GLOBALS['db']) && is_object(\$GLOBALS['db'])") !== false);
check('the page key is the one the installer registers', strpos(file_get_contents($PLUGIN . '/Installer/ScriptedInstaller.php'), "'configTurnstile'") !== false);
check('the description names Contact Us and the dormant-until-keys rule', strpos($desc, 'Contact Us') !== false && strpos($desc, 'Configuration &gt; Cloudflare Turnstile') !== false);

section('license');
$all = '';
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($PLUGIN, FilesystemIterator::SKIP_DOTS)) as $f) {
    $all .= file_get_contents($f->getPathname());
}
check('no zen-cart.com license URL anywhere (GNU URL only)', strpos($all, 'zen-cart.com/license') === false && strpos(file_get_contents(ts_repo_root() . '/LICENSE'), 'gnu.org') !== false);
check('LICENSE is the GNU GPL version 2', strpos(file_get_contents(ts_repo_root() . '/LICENSE'), 'Version 2, June 1991') !== false);
$noHeader = [];
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($PLUGIN, FilesystemIterator::SKIP_DOTS)) as $f) {
    if (strtolower($f->getExtension()) === 'php'
        && substr_count(file_get_contents($f->getPathname()), '@license  https://www.gnu.org/licenses/old-licenses/gpl-2.0.html GNU General Public License v2.0') !== 1) {
        $noHeader[] = $f->getFilename();
    }
}
check('every PHP file carries the GPL v2 @license line, once' . ($noHeader ? ': ' . implode(', ', $noHeader) : ''), $noHeader === []);
check('nothing shipped says GPL v3', preg_match('~gpl-3\.0|Public License,? (V|version )3(?![.\d])|GPL[ -]?v?3(?![.\d])~i', $all) === 0);

if ($state === 'not-installed') {
    // Non-superusers get no ReadMe button (a child PHP, so zen_is_superuser() can be
    // defined; a temp script, because escapeshellarg() strips double quotes on Windows).
    $childScript = tempnam(sys_get_temp_dir(), 'ts');
    file_put_contents($childScript, '<?php define(\'SUPERUSER_PROFILE\', 1); function zen_is_superuser() { return false; } $m = require ' . var_export($PLUGIN . '/manifest.php', true) . ';'
        . ' echo (strpos($m[\'pluginDescription\'], \'ReadMe\') === false && strpos($m[\'pluginDescription\'], \'turnstile_readme\') === false'
        . ' && strpos($m[\'pluginDescription\'], \'>Forum Thread</a>\') !== false && strpos($m[\'pluginDescription\'], \'>GitHub</a>\') !== false) ? \'hidden\' : \'shown\';');
    $childOut = trim((string)shell_exec(escapeshellarg(PHP_BINARY) . ' -d error_reporting=E_ALL ' . escapeshellarg($childScript) . ' 2>&1'));
    unlink($childScript);
    check('a non-superuser sees no ReadMe button, and the rest of the row stays', $childOut === 'hidden');
    $childScript = tempnam(sys_get_temp_dir(), 'ts');
    file_put_contents($childScript, '<?php define(\'SUPERUSER_PROFILE\', 1); function zen_is_superuser() { return true; } $m = require ' . var_export($PLUGIN . '/manifest.php', true) . ';'
        . ' echo (strpos($m[\'pluginDescription\'], \'href="index.php?cmd=turnstile_readme"\') !== false && strpos($m[\'pluginDescription\'], \'>ReadMe</a>\') !== false) ? \'shown\' : \'hidden\';');
    $childOut = trim((string)shell_exec(escapeshellarg(PHP_BINARY) . ' -d error_reporting=E_ALL ' . escapeshellarg($childScript) . ' 2>&1'));
    unlink($childScript);
    check('a superuser sees it', $childOut === 'shown');
    // Zen Cart 3.0.0 reads manifests early on every admin page, before SUPERUSER_PROFILE
    // exists; core's zen_is_superuser() then dies on the undefined constant.
    $childScript = tempnam(sys_get_temp_dir(), 'ts');
    file_put_contents($childScript, '<?php function zen_is_superuser() { return 1 === SUPERUSER_PROFILE; } $m = require ' . var_export($PLUGIN . '/manifest.php', true) . ';'
        . ' echo (strpos($m[\'pluginDescription\'], \'>ReadMe</a>\') !== false) ? \'loaded\' : \'loaded without the button\';');
    $childOut = trim((string)shell_exec(escapeshellarg(PHP_BINARY) . ' -d error_reporting=E_ALL ' . escapeshellarg($childScript) . ' 2>&1'));
    unlink($childScript);
    check('the manifest loads before admin access exists (3.0.0 reads it on every admin page)', $childOut === 'loaded');

    $out = [];
    $status = 1;
    exec(escapeshellarg(PHP_BINARY) . ' -d error_reporting=E_ALL ' . escapeshellarg(__FILE__) . ' settings 2>&1', $out, $status);
    echo "
" . implode("
", $out) . "
";
    if ($status !== 0 || !preg_match('/^PASS:/m', implode("
", $out))) {
        $GLOBALS['ts_failures']++;
    }
}

ts_done("manifest is consistent (state: $state)");
