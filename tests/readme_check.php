<?php
/**
 * README.md, the admin ReadMe page that shows it, the docs, and the packaged
 * copy under dist/.
 *
 * README.md is Markdown, kept in the plugin folder and at the package root
 * (byte-identical). It isn't a public file: the ReadMe button in Plugin
 * Manager opens it through admin/turnstile_readme.php, behind the admin
 * login, for superusers only (zencart/zencart#8031).
 */

require __DIR__ . '/_bootstrap.php';

$PLUGIN = ts_plugin_dir();
$ROOT = ts_repo_root();
$readme = (string)file_get_contents($PLUGIN . '/README.md');
$version = basename($PLUGIN);

section('README.md, in two places');
check('README.md is in the plugin', is_file($PLUGIN . '/README.md'));
check('no readme.html anywhere (it was served publicly from zc_plugins/)',
    !is_file($PLUGIN . '/readme.html') && !is_file($ROOT . '/readme.html'));
check('the package-root README.md is byte-identical to the plugin\'s',
    is_file($ROOT . '/README.md') && md5_file($ROOT . '/README.md') === md5_file($PLUGIN . '/README.md'));
check('the top block has the agreed shape', strpos($readme, "# Cloudflare Turnstile\n\nVersion " . ltrim($version, 'v')
    . " \u{00B7} Zen Cart 1.5.8 through 3.0.0 \u{00B7} PHP 7.4 through 8.5 \u{00B7} GPL v3 \u{00B7} by My Zen Cart Host (dbltoe)\n\n") === 0);
check('every path it quotes uses this version directory',
    preg_match_all('~zc_plugins/Turnstile/(v[0-9.]+)~', $readme, $m) > 0
    && count(array_unique($m[1])) === 1 && $m[1][0] === $version);
check('it has at least 12 sections', preg_match_all('~^## ~m', $readme) >= 12);
check('it ends with Changelog, then License', preg_match('~\n## Changelog\n\nSee `CHANGELOG\.md`\.[^\n]+\n\n## License\n\n~', $readme) === 1);
check('it names the license: GPL version 3, with the GNU URL',
    strpos($readme, 'Cloudflare Turnstile is free software under the GNU General Public License, version 3 (https://www.gnu.org/licenses/gpl-3.0.html); the full text is in `LICENSE`.') !== false);
check('it carries the copyright sentence', strpos($readme, "Copyright \u{00A9} 2026 My Zen Cart Host (dbltoe).") !== false);
check('LICENSE is the GNU GPL version 3 text', strpos((string)file_get_contents($ROOT . '/LICENSE'), 'Version 3, 29 June 2007') !== false);
check('it says how to open it once installed', strpos($readme, "Once it's installed, a superuser can open this guide from the **ReadMe** button in Plugin Manager.") !== false);
check('LF line endings, no tabs', strpos($readme, "\r") === false && strpos($readme, "\t") === false);

section('only the Markdown the admin page renders');
check('no raw HTML', preg_match('~<(?!store root>)/?[a-z][^>\n]*>~i', preg_replace('~`[^`\n]*`~', '', $readme)) === 0);
check('no [text](url) links and no images', preg_match('~\]\(|!\[~', $readme) === 0);
check('no nested lists', preg_match('~^[ \t]+(-|\d+\.)[ \t]~m', $readme) === 0);
check('no heading deeper than ###', preg_match('~^#{4,}~m', $readme) === 0);
check('nothing points at readme.html or the old labels as a place to read',
    preg_match('~Read Me|\(`?docs/|\]\(docs/~', $readme) === 0
    && preg_match_all('~readme\.html~', $readme) === preg_match_all('~replaces readme\.html with this guide~', $readme));

section('the admin ReadMe page');
$pageFile = $PLUGIN . '/admin/turnstile_readme.php';
$page = (string)file_get_contents($pageFile);
$guard = strpos($page, "if (!defined('IS_ADMIN_FLAG'))");
$top = strpos($page, "require 'includes/application_top.php';");
check('404 guard, then application_top (the login check)', $guard !== false && $top !== false && $guard < $top);
check('superusers only', strpos($page, "if (!function_exists('zen_is_superuser') || !zen_is_superuser())") !== false && strpos($page, 'FILENAME_DENIED') !== false
    && strpos($page, 'zen_is_superuser()') > $top && strpos($page, 'zen_is_superuser()') < strpos($page, 'file_get_contents('));
foreach ([
    'HTML' => "header('Content-Type: text/html; charset=utf-8');",
    'nosniff' => "header('X-Content-Type-Options: nosniff');",
    'no framing' => "header('X-Frame-Options: DENY');",
    'a CSP with no scripts, no framing, sandboxed' => "header(\"Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; frame-ancestors 'none'; base-uri 'none'; form-action 'none'; sandbox allow-popups allow-popups-to-escape-sandbox\");",
    'no referrer' => "header('Referrer-Policy: no-referrer');",
    'no caching' => "header('Cache-Control: no-store, private');",
] as $what => $line) {
    check("it sends $what", strpos($page, $line) !== false);
}
check('it reads only the plugin\'s own README.md', strpos($page, "dirname(__DIR__) . '/README.md'") !== false && substr_count($page, 'file_get_contents(') === 1);
check('it outputs only the escaped Markdown render',
    substr_count($page, 'echo ') === 1 && strpos($page, '<?php echo $tsReadmeHtml; ?>') !== false);
check('it has no script', stripos($page, '<script') === false);
check('it takes nothing from the request', preg_match('~\$_(GET|POST|REQUEST|COOKIE|SERVER|FILES)~', $page) === 0);
check('it carries the GPL v3 docblock', strpos($page, '@package  Turnstile') !== false
    && strpos($page, '@license  https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0') !== false);
check('it is not registered in admin_pages (reached by cmd, gated by its own superuser check)',
    strpos(file_get_contents($PLUGIN . '/Installer/ScriptedInstaller.php'), 'turnstile_readme') === false);

// The renderer itself: lift the two functions out of the page and run them.
$a = strpos($page, "if (!function_exists('ts_markdown_html'))");
$b = strpos($page, '$tsReadmeFile');
eval(substr($page, $a, $b - $a));
$html = ts_markdown_html($readme);
check('the guide renders with its headings', substr_count($html, '<h2>') === preg_match_all('~^## ~m', $readme)
    && substr_count($html, '<h3>') === preg_match_all('~^### ~m', $readme) && substr_count($html, '<h1>') === 1);
check('the settings render as a table', strpos($html, '<th>Setting</th>') !== false && strpos($html, '<td>Enable Cloudflare Turnstile?</td>') !== false);
check('all four tables render, every row with two cells', substr_count($html, '<table>') === 4
    && preg_match_all('~<tr>~', $html) === preg_match_all('~<tr><t([hd])>.*?</t\1><t\1>.*?</t\1></tr>~', $html));
check('the numbered steps render as lists', substr_count($html, '<ol>') === 5 && substr_count($html, '<ul>') === 1);
check('the offer and the Secret key note render as quotes', substr_count($html, '<blockquote>') === 2
    && strpos($html, '<blockquote><strong>Rather not do the Cloudflare part yourself?</strong>') !== false
    && strpos($html, '<blockquote><strong>Keep the Secret key secret.</strong>') !== false);
check('no Markdown is left unrendered', strpos($html, '**') === false && strpos($html, '`') === false);
check('nothing in the render but the tags the renderer makes',
    preg_match('~<(?!/?(h[1-3]|p|ul|ol|li|blockquote|pre|code|table|tr|th|td|strong|em|a)[ >])~', $html) === 0);
check('every link opens in a new window with no opener or referrer',
    preg_match_all('~<a ~', $html) >= 5
    && preg_match_all('~<a ~', $html) === preg_match_all('~<a href="https?://[^"]+" target="_blank" rel="noopener noreferrer">~', $html));
$evil = ts_markdown_html("# <script>alert(1)</script>\n\n<img src=x onerror=alert(1)> [x](javascript:alert(1))\n\n| <b>a</b> | `<i>` |\n| --- | --- |\n| \"onmouseover=\"x | javascript:alert(1) |\n\n```\n</code></pre><script>x</script>\n```\n");
check('markup in the file comes out escaped, never live',
    stripos($evil, '<script') === false && stripos($evil, '<img') === false && stripos($evil, '<b>') === false
    && stripos($evil, '<i>') === false && strpos($evil, 'href="javascript') === false && strpos($evil, '"onmouseover') === false);

section('the Cloudflare guide');
foreach (['Application security', 'Add widget manually', 'custom hostname', 'Managed', 'pre-clearance', 'Site key', 'Secret key', 'Configuration > Cloudflare Turnstile', 'Manage account > Members', '1x00000000000000000000AA', 'NOTIFY_HTML_HEAD_END'] as $needle) {
    check("covers: $needle", strpos($readme, $needle) !== false);
}
check('says the site does not need to run through Cloudflare', strpos($readme, '**not** need to run through Cloudflare') !== false);
$installer = file_get_contents($PLUGIN . '/Installer/ScriptedInstaller.php');
foreach (['Enable Cloudflare Turnstile?', 'Site Key', 'Secret Key', 'Protect Create Account', 'Protect Contact Us', 'Protect Ask a Question', 'Protect Write a Review'] as $title) {
    check("the settings table names \"$title\", as the installer does", strpos($readme, '| ' . $title . ' |') !== false && strpos($installer, $title) !== false);
}

section('the paid setup offer');
check('offered near the top and in the docs', strpos($readme, '> **Rather not do the Cloudflare part yourself?**') !== false && strpos($readme, '> **Rather not') < strpos($readme, "\n## ") && strpos($readme, 'flat fee') !== false && strpos(file_get_contents($ROOT . '/docs/CONFIGURATION.md'), 'flat fee') !== false);
check('no password sharing: invite as a member', strpos($readme, 'never have to share your Cloudflare password') !== false);
check('the offer links our setup product (56), our own site only', strpos($readme, 'https://myzencartzone.com/index.php?main_page=product_info&products_id=56') !== false
    && strpos($html, '<a href="https://myzencartzone.com/index.php?main_page=product_info&amp;products_id=56" target="_blank" rel="noopener noreferrer">') !== false);
check('the readme links the forum support thread by its permalink', strpos($readme, 'https://www.zen-cart.com/threads/207429?page=1#post-1347614') !== false);
preg_match_all('~https?://[^\s<>()"\']+~', $readme, $urls);
$hosts = array_values(array_unique(array_map(static function ($u) {
    return (string)parse_url($u, PHP_URL_HOST);
}, $urls[0])));
sort($hosts);
check('it links only our site, the forum thread, GitHub and the GNU license', $hosts === ['github.com', 'myzencartzone.com', 'www.gnu.org', 'www.zen-cart.com']);

section('American English, plain register');
$texts = ['README.md (plugin)' => $readme];
foreach (['README.md', 'CHANGELOG.md', 'docs/INSTALL.md', 'docs/CONFIGURATION.md', 'docs/COMPATIBILITY.md', 'docs/CUSTOMIZING.md'] as $doc) {
    $texts[$doc] = (string)@file_get_contents($ROOT . '/' . $doc);
    check("$doc exists", $texts[$doc] !== '');
}
// LICENSE is the FSF's text, word for word ("shall" and all), so it is checked
// for being that text rather than for our register.
check('LICENSE exists and is the canonical gnu.org GPL v3 text', md5(str_replace("\r\n", "\n", (string)@file_get_contents($ROOT . '/LICENSE'))) === '1ebbd3e34237af26da5dc08a4e440464');
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

section('version, changelog and license across the repository');
$changelogTxt = (string)file_get_contents($PLUGIN . '/changelog.txt');
check('changelog.txt and CHANGELOG.md agree', strpos($texts['CHANGELOG.md'], '## ' . $version) !== false && strpos($changelogTxt, $version) !== false);
check('both lead with this version', preg_match('~^## (v[0-9.]+)~m', $texts['CHANGELOG.md'], $c1) === 1 && $c1[1] === $version
    && preg_match('~^(v[0-9.]+) \(~m', $changelogTxt, $c2) === 1 && $c2[1] === $version);
check('both record the license change and the new ReadMe', strpos($texts['CHANGELOG.md'], 'GNU General Public License, version 3') !== false && strpos($changelogTxt, 'GNU General Public License, version 3') !== false
    && strpos($texts['CHANGELOG.md'], 'index.php?cmd=turnstile_readme') !== false && strpos($changelogTxt, 'index.php?cmd=turnstile_readme') !== false);
foreach (['README.md', 'docs/INSTALL.md', 'docs/CONFIGURATION.md', 'docs/COMPATIBILITY.md', 'docs/CUSTOMIZING.md'] as $doc) {
    preg_match_all('~Turnstile/(v[0-9.]+)~', $texts[$doc], $m);
    check("$doc quotes only $version as the plugin folder", $m[1] === [] || (count(array_unique($m[1])) === 1 && $m[1][0] === $version));
}
check('INSTALL.md installs this version', strpos($texts['docs/INSTALL.md'], 'zc_plugins/Turnstile/' . $version . '/') !== false);
$stale = [];
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ROOT, FilesystemIterator::SKIP_DOTS)) as $f) {
    $rel = substr(str_replace('\\', '/', $f->getPathname()), strlen($ROOT) + 1);
    if (preg_match('~^(\.git|dist)/|\.(zip|png|jpg)$|^LICENSE$|^tests/~', $rel) === 1) {
        continue;
    }
    $text = file_get_contents($f->getPathname());
    if (preg_match('~^(CHANGELOG\.md|zc_plugins/.*/changelog\.txt)$~', $rel) === 1) {
        // Older entries stay as they were written; only this version's is checked.
        $cut = preg_match('~^(## )?v[0-9.]+~m', $text, $mm, PREG_OFFSET_CAPTURE, (int)strpos($text, $version) + 1) === 1 ? $mm[0][1] : strlen($text);
        $text = substr($text, 0, $cut);
    }
    // The two sentences that say what this release did are the only mentions allowed.
    $text = preg_replace('~readme\.html\s+is\s+replaced\s+by\s+README\.md|replaces\s+readme\.html\s+with\s+this\s+guide|\(was\s+version\s+2\)~', '', $text);
    if (preg_match('~readme\.html|GPL[ -]?(v|version )?2|gpl-2\.0|Public License,? (v|version )? ?2|zc_plugins/Turnstile/v(?!' . preg_quote(ltrim($version, 'v'), '~') . '/)[0-9]~i', $text) === 1) {
        $stale[] = $rel;
    }
}
check('nothing outside old changelog entries mentions readme.html, GPL v2 or an old version folder'. ($stale ? ': ' . implode(', ', $stale) : ''), $stale === []);

section('the packaged copy');
$distDir = $ROOT . '/dist/cloudflare_turnstile_' . $version;
if (!is_dir($distDir)) {
    echo "          dist/ not built yet (tools\\build_package.ps1); skipping\n";
} else {
    check('the README.md at the package root is byte-identical to the plugin copy', is_file($distDir . '/README.md') && file_get_contents($distDir . '/README.md') === $readme);
    check('the package has no readme.html', !is_file($distDir . '/readme.html') && !is_file($distDir . '/zc_plugins/Turnstile/' . $version . '/readme.html'));
    $stale = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($PLUGIN, FilesystemIterator::SKIP_DOTS)) as $f) {
        $rel = substr(str_replace('\\', '/', $f->getPathname()), strlen($PLUGIN));
        $p = $distDir . '/zc_plugins/Turnstile/' . $version . $rel;
        if (!is_file($p) || md5_file($p) !== md5_file($f->getPathname())) {
            $stale[] = $rel;
        }
    }
    check('every plugin file in dist/ matches the source', $stale === []);
    check('the package carries the admin ReadMe page', is_file($distDir . '/zc_plugins/Turnstile/' . $version . '/admin/turnstile_readme.php'));
    foreach (['LICENSE', 'CHANGELOG.md', 'docs/INSTALL.md', 'docs/CONFIGURATION.md', 'docs/CUSTOMIZING.md', 'docs/COMPATIBILITY.md'] as $doc) {
        check("the package carries the current $doc", is_file($distDir . '/' . $doc) && md5_file($distDir . '/' . $doc) === md5_file($ROOT . '/' . $doc));
    }
    check('no tests or Pro notes in the package', !is_dir($distDir . '/tests') && !is_file($distDir . '/docs/PRO_SCOPE.md'));
    check('the release zip is in the repository root (gitignored)', is_file($ROOT . '/cloudflare_turnstile_' . $version . '.zip'));
}

ts_done('readme and docs are complete');
