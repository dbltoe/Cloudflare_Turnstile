<?php
/**
 * Cloudflare Turnstile -- plugin manifest.
 *
 * @package  Turnstile
 * @license  http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */

if (!defined('IS_ADMIN_FLAG')) {
    die('Illegal Access');
}

return [
    'pluginVersion' => 'v1.0.0',
    'pluginName' => 'Cloudflare Turnstile',
    'pluginDescription' =>
        'Adds a Cloudflare Turnstile check to every create-account form (the Create Account page, '
        . 'the Login page\'s inline form and One-Page Checkout\'s registration form) and refuses the '
        . 'account when the check is missing or fails. No template file is changed. Stays dormant '
        . 'until a Site Key and Secret Key are entered under Configuration &gt; Cloudflare Turnstile.',
    'pluginAuthor' => 'My Zen Cart Host (dbltoe)',
    'pluginId' => 0,
    'zcVersions' => ['v200', 'v210', 'v220', 'v230', 'v300'],
    'changelog' => '',
    'github_repo' => '',
    'pluginGroups' => [],
];
