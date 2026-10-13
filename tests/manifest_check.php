<?php
/**
 * manifest.php: the Plugin Manager panel and the release identity.
 */

require __DIR__ . '/_bootstrap.php';

define('DIR_WS_CATALOG', '/shop/');

$PLUGIN = ts_plugin_dir();
$version = basename($PLUGIN);
$manifest = require $PLUGIN . '/manifest.php';
$desc = $manifest['pluginDescription'];

section('manifest shape');
foreach (['pluginVersion', 'pluginName', 'pluginDescription', 'pluginAuthor', 'pluginId', 'zcVersions', 'changelog', 'github_repo', 'pluginGroups'] as $key) {
    check("has $key", array_key_exists($key, $manifest));
}
check('pluginVersion matches the directory: ' . $version, $manifest['pluginVersion'] === $version);
check('the observer reports the same version', strpos(file_get_contents($PLUGIN . '/catalog/includes/classes/observers/auto.turnstile.php'), "VERSION = '" . ltrim($version, 'v') . "'") !== false);
check('changelog exists and names this version', is_file($PLUGIN . '/' . $manifest['changelog']) && strpos(file_get_contents($PLUGIN . '/' . $manifest['changelog']), $version) !== false);
check('declares 2.0 through 3.0', $manifest['zcVersions'] === ['v200', 'v210', 'v220', 'v230', 'v300']);
check('author is the agreed string', $manifest['pluginAuthor'] === 'My Zen Cart Host (dbltoe)');
check('pluginId is a bare integer', is_int($manifest['pluginId']) && preg_match("~'pluginId' => \d+,~", file_get_contents($PLUGIN . '/manifest.php')) === 1);

section('panel buttons');
check('Read Me honors DIR_WS_CATALOG and exists', strpos($desc, 'href="/shop/zc_plugins/Turnstile/' . $version . '/readme.html"') !== false && is_file($PLUGIN . '/readme.html'));
check('GitHub button and github_repo agree', strpos($desc, 'href="' . $manifest['github_repo'] . '"') !== false && $manifest['github_repo'] === 'https://github.com/dbltoe/Cloudflare_Turnstile');
check('Forum Support Thread button wired (shown once the thread URL is set)', strpos(file_get_contents($PLUGIN . '/manifest.php'), 'Forum Support Thread') !== false);
check('buttons are btn btn-primary, opening safely', substr_count($desc, 'class="btn btn-primary" role="button"') >= 2 && substr_count($desc, 'rel="noopener noreferrer"') >= 2);
check('the description names Contact Us and the dormant-until-keys rule', strpos($desc, 'Contact Us') !== false && strpos($desc, 'Configuration &gt; Cloudflare Turnstile') !== false);

section('license');
$all = '';
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($PLUGIN, FilesystemIterator::SKIP_DOTS)) as $f) {
    $all .= file_get_contents($f->getPathname());
}
check('no zen-cart.com license URL anywhere (GNU URL only)', strpos($all, 'zen-cart.com/license') === false && strpos(file_get_contents(ts_repo_root() . '/LICENSE'), 'gnu.org') !== false);

ts_done('manifest is consistent');
