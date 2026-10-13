<?php
/**
 * readme.html, the docs, and the packaged copy under dist/.
 */

require __DIR__ . '/_bootstrap.php';

$PLUGIN = ts_plugin_dir();
$ROOT = ts_repo_root();
$readme = file_get_contents($PLUGIN . '/readme.html');
$version = basename($PLUGIN);

section('readme.html stands alone');
check('no external stylesheet or script, no images', preg_match('~<link[^>]+rel="stylesheet"|<script|<img~i', $readme) === 0);
check('charset, viewport, language and title', stripos($readme, 'charset="utf-8"') !== false && stripos($readme, 'name="viewport"') !== false && preg_match('~<html[^>]+lang=~i', $readme) === 1 && preg_match('~<title>[^<]+</title>~i', $readme) === 1);
preg_match_all('~<h2 id="([^"]+)"~', $readme, $h2);
preg_match_all('~href="#([^"]+)"~', $readme, $links);
check('every contents link lands on a section (' . count($h2[1]) . ' sections)', count($h2[1]) >= 10 && array_diff($links[1], $h2[1]) === []);
check('the version on the page is this version', strpos($readme, 'Cloudflare Turnstile ' . $version) !== false);

section('the Cloudflare guide');
foreach (['Application security', 'Add widget manually', 'custom hostname', 'Managed', 'pre-clearance', 'Site key', 'Secret key', 'Configuration &gt; Cloudflare Turnstile', 'Manage account &gt; Members', '1x00000000000000000000AA', 'NOTIFY_HTML_HEAD_END'] as $needle) {
    check("covers: $needle", strpos($readme, $needle) !== false);
}
check('says the site does not need to run through Cloudflare', strpos($readme, '<strong>not</strong> need to run through Cloudflare') !== false);

section('the paid setup offer');
check('offered near the top and in the docs', strpos($readme, 'class="offer"') !== false && strpos($readme, 'class="offer"') < strpos($readme, 'class="toc"') && strpos(file_get_contents($ROOT . '/docs/CONFIGURATION.md'), 'flat fee') !== false && strpos(file_get_contents($ROOT . '/README.md'), 'flat fee') !== false);
check('no password sharing: invite as a member', strpos($readme, 'never have to share your Cloudflare password') !== false);
check('the offer links our setup product (56), our own site only', strpos($readme, 'https://myzencartzone.com/index.php?main_page=product_info&amp;products_id=56') !== false);
check('the readme links the forum support thread by its permalink', strpos($readme, 'https://www.zen-cart.com/threads/207429?page=1#post-1347614') !== false);

section('American English, plain register');
$texts = ['readme.html' => $readme];
foreach (['README.md', 'CHANGELOG.md', 'LICENSE', 'docs/INSTALL.md', 'docs/CONFIGURATION.md', 'docs/COMPATIBILITY.md', 'docs/CUSTOMIZING.md'] as $doc) {
    $texts[$doc] = (string)@file_get_contents($ROOT . '/' . $doc);
    check("$doc exists", $texts[$doc] !== '');
}
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($PLUGIN, FilesystemIterator::SKIP_DOTS)) as $f) {
    if (strtolower($f->getExtension()) === 'php') {
        $texts[$f->getFilename()] = file_get_contents($f->getPathname());
    }
}
$bad = [];
foreach ($texts as $name => $text) {
    if (preg_match_all('~\b(colour\w*|centre\w*|organis\w*|customis\w*|recognis\w*|behaviour\w*|licence\w*|catalogue\w*|grey|authoris\w*|cancelled|whilst|amongst|shall|in order to|worked out)\b~i', $text, $m)) {
        $bad[] = $name . ': ' . implode(', ', array_unique($m[0]));
    }
}
check('no British spellings or stiff phrasing', $bad === []);
foreach ($bad as $b) {
    echo "          $b\n";
}
check('the readme uses contractions', preg_match_all("~\\b(doesn't|isn't|can't|won't|you'll|it's|don't|that's|you're|there's)\\b~", $readme) >= 8);
check('changelog.txt and CHANGELOG.md agree', strpos($texts['CHANGELOG.md'], '## ' . $version) !== false && strpos(file_get_contents($PLUGIN . '/changelog.txt'), $version) !== false);

section('the packaged copy');
$distDir = $ROOT . '/dist/cloudflare_turnstile_' . $version;
if (!is_dir($distDir)) {
    echo "          dist/ not built yet (tools\\build_package.ps1); skipping\n";
} else {
    check('the readme at the package root is byte-identical', file_get_contents($distDir . '/readme.html') === $readme);
    $stale = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($PLUGIN, FilesystemIterator::SKIP_DOTS)) as $f) {
        $rel = substr(str_replace('\\', '/', $f->getPathname()), strlen($PLUGIN));
        $p = $distDir . '/zc_plugins/Turnstile/' . $version . $rel;
        if (!is_file($p) || md5_file($p) !== md5_file($f->getPathname())) {
            $stale[] = $rel;
        }
    }
    check('every plugin file in dist/ matches the source', $stale === []);
    check('no tests or Pro notes in the package', !is_dir($distDir . '/tests') && !is_file($distDir . '/docs/PRO_SCOPE.md'));
    check('the release zip is in the repository root (gitignored)', is_file($ROOT . '/cloudflare_turnstile_' . $version . '.zip'));
}

ts_done('readme and docs are complete');
