<?php
/**
 * Cloudflare Turnstile -- plugin manifest.
 *
 * The description is echoed as raw HTML in the Plugin Manager's info panel,
 * beside the Install / Uninstall buttons, so the Read Me, GitHub and forum
 * buttons go here. The Read Me link is built from DIR_WS_CATALOG so it works
 * wherever the store lives.
 *
 * On v2.2 and later the description is refreshed from this file on every
 * Plugin Manager scan; on v2.0/2.1 it is captured once, when the plugin_control
 * row is first created. So nothing state-dependent belongs here.
 *
 * @package  Turnstile
 * @license  https://www.gnu.org/licenses/old-licenses/gpl-2.0.html GNU Public License V2.0
 */

$tsPluginDir = 'zc_plugins/Turnstile/v1.2.0/';
$tsReadmeUrl = (defined('DIR_WS_CATALOG') ? DIR_WS_CATALOG : '/') . $tsPluginDir . 'readme.html';
$tsGithubUrl = 'https://github.com/dbltoe/Cloudflare_Turnstile';

/**
 * The Zen Cart forum support thread: the opening-post permalink, set before
 * the first public release. Empty renders no button, which beats a link that 404s.
 */
$tsForumUrl = 'https://www.zen-cart.com/threads/207429?page=1#post-1347614';

$tsGap = '6px';
$tsLinks =
    '<div style="margin:10px 0 0;padding:0 0 0 ' . $tsGap . '">'
    . '<a href="' . $tsReadmeUrl . '" target="_blank" rel="noopener noreferrer"'
    . ' class="btn btn-primary" role="button" style="margin:0 ' . $tsGap . ' 0 0">Read Me</a>'
    . '<a href="' . $tsGithubUrl . '" target="_blank" rel="noopener noreferrer"'
    . ' class="btn btn-primary" role="button" style="margin:0 ' . $tsGap . ' 0 0">GitHub</a>'
    . ($tsForumUrl !== ''
        ? '<a href="' . $tsForumUrl . '" target="_blank" rel="noopener noreferrer"'
          . ' class="btn btn-primary" role="button" style="margin:0 ' . $tsGap . ' 0 0">Forum Support Thread</a>'
        : '')
    . '</div>';

return [
    'pluginVersion' => 'v1.2.0',
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
