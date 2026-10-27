<?php
/**
 * Cloudflare Turnstile -- plugin manifest.
 *
 * The description is echoed as raw HTML in the Plugin Manager's info panel,
 * beside the Install / Uninstall buttons, so the ReadMe, Settings, Forum
 * Thread and GitHub buttons go here. The ReadMe link is built from
 * DIR_WS_CATALOG so it works wherever the store lives.
 *
 * On v2.2 and later the description is refreshed from this file on every
 * Plugin Manager scan; on v1.5.8, v2.0 and v2.1 it is captured once, when the
 * plugin_control row is first created, and only this plugin's installer
 * refreshes it there (at Install, Upgrade and Uninstall). The one
 * state-dependent part, the Settings button, is handled below.
 *
 * @package  Turnstile
 * @license  https://www.gnu.org/licenses/old-licenses/gpl-2.0.html GNU Public License V2.0
 */

$tsPluginDir = 'zc_plugins/Turnstile/v1.2.1/';
$tsReadmeUrl = (defined('DIR_WS_CATALOG') ? DIR_WS_CATALOG : '/') . $tsPluginDir . 'readme.html';
$tsGithubUrl = 'https://github.com/dbltoe/Cloudflare_Turnstile';

/**
 * The Zen Cart forum support thread: the opening-post permalink, set before
 * the first public release. Empty renders no button, which beats a link that 404s.
 */
$tsForumUrl = 'https://www.zen-cart.com/threads/207429?page=1#post-1347614';

/**
 * The Settings button: Configuration > Cloudflare Turnstile, shown only once
 * the plugin is installed. Its gID is assigned at install, so it differs from
 * store to store and can't be written here; it's read from the admin page the
 * installer registers (page key configTurnstile). The link is relative
 * ("index.php?cmd=configuration&gID=N") because Plugin Manager is an admin
 * page, so it survives a domain or admin-folder rename.
 *
 * Zen Cart reads manifests only on the Plugin Manager page, but the lookup is
 * still guarded so a manifest read anywhere else (no database, catalog side,
 * CLI) just leaves the button out. On v2.2 and later this file is re-read on
 * every Plugin Manager visit, so the button follows the install state by
 * itself; on v1.5.8, v2.0 and v2.1 the installer saves the re-read
 * description at Install, Upgrade and Uninstall
 * (tsRefreshPluginDescription).
 */
$tsSettingsUrl = '';
if (defined('IS_ADMIN_FLAG') && IS_ADMIN_FLAG === true && defined('TABLE_ADMIN_PAGES')
    && isset($GLOBALS['db']) && is_object($GLOBALS['db'])) {
    $tsPage = $GLOBALS['db']->Execute(
        "SELECT main_page, page_params FROM " . TABLE_ADMIN_PAGES
        . " WHERE page_key = 'configTurnstile' LIMIT 1"
    );
    if (!$tsPage->EOF && defined($tsPage->fields['main_page'])) {
        $tsSettingsUrl = 'index.php?cmd=' . constant($tsPage->fields['main_page'])
            . '&' . $tsPage->fields['page_params'];
    }
}

/**
 * The buttons are styled like Plugin Manager's own controls, which use
 * exactly `class="btn btn-primary" role="button"`. The whole description is a
 * single setBoxContent() item, so the inline-block buttons line up on one row.
 *
 * One row: ReadMe | Settings | Forum Thread | GitHub. A second line would push
 * the Author line and the core buttons further down the panel. Spacing is set
 * inline, which beats any theme `.btn` margin: every gap is 4px (the wrapper
 * supplies the space before the first button, each button the space after
 * itself) and the side padding is 8px rather than Bootstrap's 12px, so all
 * four fit on one line in Plugin Manager's side panel from a 1366px-wide
 * window up. The wrapper's 10px top margin separates the row from the
 * description text above it.
 */
$tsGap = '4px';
$tsButtonStyle = ' style="margin:0 ' . $tsGap . ' 0 0;padding-left:8px;padding-right:8px"';
$tsButton = static function ($url, $label, $newTab) use ($tsButtonStyle) {
    return '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"'
        . ($newTab ? ' target="_blank" rel="noopener noreferrer"' : '')
        . ' class="btn btn-primary" role="button"' . $tsButtonStyle . '>' . $label . '</a>';
};

$tsLinks =
    '<div style="margin:10px 0 0;padding:0 0 0 ' . $tsGap . '">'
    . $tsButton($tsReadmeUrl, 'ReadMe', true)
    . ($tsSettingsUrl !== '' ? $tsButton($tsSettingsUrl, 'Settings', false) : '')
    // Renders nothing while $tsForumUrl is empty.
    . ($tsForumUrl !== '' ? $tsButton($tsForumUrl, 'Forum Thread', true) : '')
    . $tsButton($tsGithubUrl, 'GitHub', true)
    . '</div>';

return [
    'pluginVersion' => 'v1.2.1',
    'pluginName' => 'Cloudflare Turnstile',
    'pluginDescription' =>
        'Adds Cloudflare\'s free Turnstile bot check to the forms bots abuse: every create-account form (the '
        . 'Create Account page, the Login page\'s new-customer form and One Page Checkout\'s registration form), '
        . 'Contact Us, Ask a Question and Write a Review. A submission whose check is missing or fails is refused. '
        . 'Each form has its own switch and no template file is changed. Stays dormant until a Site Key and Secret '
        . 'Key are entered under Configuration &gt; Cloudflare Turnstile.'
        . $tsLinks,
    'pluginAuthor' => 'My Zen Cart Host (dbltoe)',
    'pluginId' => 3000, // the Plugins Library listing id (not the per-release id in a download URL)
    'zcVersions' => ['v158', 'v200', 'v210', 'v220', 'v230', 'v300'],
    'changelog' => 'changelog.txt',
    'github_repo' => $tsGithubUrl,
    'pluginGroups' => [],
];
