<?php
/**
 * The Plugin Manager installer: settings created without overwriting what the
 * store owner set, the admin page registered, the template warning, and a
 * clean uninstall.
 */

require __DIR__ . '/_bootstrap.php';

define('IS_ADMIN_FLAG', true);
$root = str_replace('\\', '/', sys_get_temp_dir()) . '/ts_installer_' . getmypid() . '/';
define('DIR_FS_CATALOG', $root);
ts_define_environment();

$GLOBALS['ts_pages'] = [];
$GLOBALS['ts_activity'] = [];
function zen_register_admin_page($key, $lang, $file, $params, $menu, $display, $sort)
{
    $GLOBALS['ts_pages'][$key] = compact('lang', 'file', 'params', 'menu');
}
function zen_deregister_admin_pages($keys)
{
    foreach ((array)$keys as $k) {
        unset($GLOBALS['ts_pages'][$k]);
    }
}
function zen_record_admin_activity($message, $severity)
{
    $GLOBALS['ts_activity'][] = [$message, $severity];
}

require __DIR__ . '/stubs/ScriptedInstallerBase.inc';
require ts_plugin_dir() . '/Installer/ScriptedInstaller.php';

class TsErrors
{
    public $errors = [];
    public function addError($s, $m, $f = false, $fr = '')
    {
        $this->errors[] = $m;
    }
}

function ts_template($dir, $withHook)
{
    global $root;
    @mkdir($root . 'includes/templates/' . $dir . '/common', 0777, true);
    file_put_contents($root . 'includes/templates/' . $dir . '/common/html_header.php',
        "<head>\n" . ($withHook ? "<?php \$zco_notifier->notify('NOTIFY_HTML_HEAD_END', \$current_page_base); ?>\n" : '') . "</head>\n");
}

function ts_install($templateDir)
{
    $db = new TsDb();
    $db->answers = [
        "configuration_group_title = 'Cloudflare Turnstile'" => [['configuration_group_id' => 42]],
        'FROM zen_template_select' => [['template_dir' => $templateDir]],
    ];
    $GLOBALS['messageStack'] = new TsMessageStack();
    $GLOBALS['ts_activity'] = [];
    $installer = new ScriptedInstaller($db, new TsErrors());
    $ok = $installer->doInstall();
    return [$ok, $db];
}

section('install');
ts_template('good_tpl', true);
[$ok, $db] = ts_install('good_tpl');
check('install succeeds', $ok === true);
$inserts = $db->matching('INSERT IGNORE INTO zen_configuration ');
check('seven settings, each INSERT IGNORE so saved values survive', count($inserts) === 7);
foreach (['TURNSTILE_STATUS', 'TURNSTILE_SITE_KEY', 'TURNSTILE_SECRET_KEY', 'TURNSTILE_CREATE_ACCOUNT', 'TURNSTILE_CONTACT_US', 'TURNSTILE_ASK_A_QUESTION', 'TURNSTILE_REVIEWS'] as $key) {
    check("$key is created", count(array_filter($inserts, static function ($s) use ($key) {
        return strpos($s, "'" . $key . "'") !== false;
    })) === 1);
}
check('the metadata UPDATEs never touch configuration_value', array_filter($db->matching('UPDATE zen_configuration SET'), static function ($s) {
    return stripos($s, 'configuration_value') !== false;
}) === []);
check('the Secret Key is masked in the admin', count(array_filter($inserts, static function ($s) {
    return strpos($s, "'TURNSTILE_SECRET_KEY'") !== false && strpos($s, "'zen_cfg_password_display'") !== false;
})) === 1);
check('all four form switches default to on', count(array_filter($inserts, static function ($s) {
    foreach (["'TURNSTILE_CREATE_ACCOUNT'", "'TURNSTILE_CONTACT_US'", "'TURNSTILE_ASK_A_QUESTION'", "'TURNSTILE_REVIEWS'"] as $key) {
        if (strpos($s, $key) !== false) {
            return strpos($s, "'true'") !== false;
        }
    }
    return false;
})) === 4);
check('the configuration page is registered under Configuration', ($GLOBALS['ts_pages']['configTurnstile']['menu'] ?? '') === 'configuration' && ($GLOBALS['ts_pages']['configTurnstile']['params'] ?? '') === 'gID=42');
check('no template warning when the hook is there', $GLOBALS['messageStack']->messages === []);

section('upgrade from v1.0.0');
$db = new TsDb();
$db->answers = ["configuration_group_title = 'Cloudflare Turnstile'" => [['configuration_group_id' => 42]], 'FROM zen_template_select' => [['template_dir' => 'good_tpl']]];
(new ScriptedInstaller($db, new TsErrors()))->doUpgrade('v1.0.0');
check('an upgrade re-runs the idempotent install (new keys arrive, old values stay)', count($db->matching('INSERT IGNORE INTO zen_configuration ')) === 7 && $db->matching('DELETE') === []);

section('a template without the hook');
ts_template('old_tpl', false);
[$ok, $db] = ts_install('old_tpl');
$warn = $GLOBALS['messageStack']->messages[0] ?? null;
check('install still succeeds (a warning, not a refusal)', $ok === true);
check('the admin sees a warning naming the file and the fix', $warn !== null && $warn['type'] === 'warning' && strpos($warn['message'], 'includes/templates/old_tpl/common/html_header.php') !== false && strpos($warn['message'], 'NOTIFY_HTML_HEAD_END') !== false);
check('and it goes to the admin activity log', count(array_filter($GLOBALS['ts_activity'], static function ($a) {
    return strpos($a[0], 'NOTIFY_HTML_HEAD_END') !== false && $a[1] === 'warning';
})) === 1);
@mkdir($root . 'zc_plugins/TplPlugin/v1.0.0/catalog/includes/templates/plug_tpl/common', 0777, true);
file_put_contents($root . 'zc_plugins/TplPlugin/v1.0.0/catalog/includes/templates/plug_tpl/common/html_header.php', "<?php \$zco_notifier->notify('NOTIFY_HTML_HEAD_END'); ?>");
ts_install('plug_tpl');
check('a template that lives in a plugin is found and passes', $GLOBALS['messageStack']->messages === []);
ts_template('template_default', false);
ts_install('../../etc');
$warn = $GLOBALS['messageStack']->messages[0]['message'] ?? '';
check('a hostile template_dir value falls back to template_default, never a path', strpos($warn, 'includes/templates/template_default/common/html_header.php') !== false && strpos($warn, '..') === false);

section('uninstall');
$db = new TsDb();
$db->answers = ["configuration_group_title = 'Cloudflare Turnstile'" => [['configuration_group_id' => 42]]];
(new ScriptedInstaller($db, new TsErrors()))->doUninstall();
check('settings and the group are deleted by group id', count($db->matching('DELETE FROM zen_configuration WHERE configuration_group_id = 42')) === 1 && count($db->matching('DELETE FROM zen_configuration_group WHERE configuration_group_id = 42')) === 1);
check('the admin page is deregistered', !isset($GLOBALS['ts_pages']['configTurnstile']));

// clean the throwaway store root
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
foreach ($it as $f) {
    $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
}
@rmdir($root);

ts_done('installer behaves');
