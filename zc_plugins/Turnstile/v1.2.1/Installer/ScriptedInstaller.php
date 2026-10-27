<?php
/**
 * Cloudflare Turnstile -- Plugin Manager installer.
 *
 * Only `executeInstallerSql()` is used for database work, and every step is
 * idempotent, so an upgrade is simply a re-run of the install.
 *
 * @package  Turnstile
 * @license  https://www.gnu.org/licenses/old-licenses/gpl-2.0.html GNU Public License V2.0
 */

if (!defined('IS_ADMIN_FLAG')) {
    die('Illegal Access');
}

use Zencart\PluginSupport\ScriptedInstaller as ScriptedInstallBase;

class ScriptedInstaller extends ScriptedInstallBase
{
    public const CONFIG_GROUP_TITLE = 'Cloudflare Turnstile';

    public const ADMIN_PAGE_KEYS = ['configTurnstile'];

    /** plugin_control.unique_key: the zc_plugins folder name. */
    public const PLUGIN_KEY = 'Turnstile';

    protected function executeInstall()
    {
        $configGroupId = $this->tsGetOrCreateConfigGroup();
        if ($configGroupId === 0) {
            return false;
        }

        if ($this->tsAddConfigurationKeys($configGroupId) === false) {
            return false;
        }
        $this->tsRegisterAdminPages($configGroupId);
        $this->tsWarnIfTemplateLacksHook();

        $this->tsLog('Cloudflare Turnstile: installed/upgraded. It does nothing until a Site Key and Secret Key are entered under Configuration, Cloudflare Turnstile.', 'warning');

        // After the admin page is registered, so the saved panel has the
        // Settings button. executeUpgrade() runs this method, so an upgrade
        // reaches it too.
        $this->tsRefreshPluginDescription();

        return true;
    }

    protected function executeUpgrade($oldVersion = null)
    {
        return $this->executeInstall();
    }

    protected function executeUninstall()
    {
        zen_deregister_admin_pages(self::ADMIN_PAGE_KEYS);

        $groupId = $this->tsGetConfigGroupId();
        if ($groupId > 0) {
            $this->executeInstallerSql(
                "DELETE FROM " . TABLE_CONFIGURATION . " WHERE configuration_group_id = " . $groupId
            );
            $this->executeInstallerSql(
                "DELETE FROM " . TABLE_CONFIGURATION_GROUP . " WHERE configuration_group_id = " . $groupId
            );
        }

        $this->tsLog('Cloudflare Turnstile: uninstalled. Settings removed; no template file was ever changed.', 'warning');

        // After the admin page is deregistered, so the saved panel loses the
        // Settings button.
        $this->tsRefreshPluginDescription();

        return true;
    }

    /**
     * Save the manifest's description again, now that the Settings page has
     * been registered (install, upgrade) or removed (uninstall).
     *
     * Needed on v1.5.8, v2.0 and v2.1, which store plugin_control.description
     * only when Plugin Manager first sees the plugin, before Install, so the
     * Settings button (built in manifest.php from the registered admin page)
     * would never appear there. v2.2 and later rewrite the description from
     * the manifest on every Plugin Manager visit, so this is a no-op there in
     * effect. Core's own install/uninstall step changes only status and
     * version, so this description stays.
     *
     * @return bool
     */
    protected function tsRefreshPluginDescription()
    {
        if (!defined('TABLE_PLUGIN_CONTROL')) {
            return true;
        }
        $manifest = require dirname(__DIR__) . '/manifest.php';
        if (!is_array($manifest) || !isset($manifest['pluginDescription'])) {
            return true;
        }

        return $this->executeInstallerSql(
            "UPDATE " . TABLE_PLUGIN_CONTROL . "
                SET description = '" . $this->dbConn->prepare_input($manifest['pluginDescription']) . "'
              WHERE unique_key = '" . $this->dbConn->prepare_input(self::PLUGIN_KEY) . "'
              LIMIT 1"
        );
    }

    /**
     * The widget is placed by a script printed at NOTIFY_HTML_HEAD_END, which
     * the template's common/html_header.php fires. Every stock template (and
     * ZCA Bootstrap, and the Bootstrap 5 plugin template) does; an old custom
     * template may not. Without it the check would be required but never
     * shown, and nobody could sign up, so say so loudly. A warning, not a
     * refusal: the store owner may be about to fix the template.
     */
    protected function tsWarnIfTemplateLacksHook()
    {
        $file = $this->tsActiveTemplateHeader();
        if ($file === '' || strpos((string)file_get_contents($file), 'NOTIFY_HTML_HEAD_END') !== false) {
            return;
        }
        $message = "Cloudflare Turnstile: your template's " . str_replace(DIR_FS_CATALOG, '', $file)
            . " doesn't fire NOTIFY_HTML_HEAD_END, so the security check can't appear on your forms. Leave Turnstile switched off"
            . ' (Configuration &gt; Cloudflare Turnstile) until that file has the line'
            . " <code>\$zco_notifier->notify('NOTIFY_HTML_HEAD_END', \$current_page_base);</code> just before &lt;/head&gt;, as Zen Cart's own templates do.";
        $this->tsLog($message, 'warning');
        if (isset($GLOBALS['messageStack']) && is_object($GLOBALS['messageStack'])) {
            $GLOBALS['messageStack']->add_session($message, 'warning');
        }
    }

    /**
     * The html_header.php the storefront actually uses: the active template's
     * own, one supplied by a template plugin, or template_default's. '' when
     * none can be found.
     */
    protected function tsActiveTemplateHeader()
    {
        if (!defined('DIR_FS_CATALOG')) {
            return '';
        }
        $dir = 'template_default';
        $row = $this->dbConn->Execute("SELECT template_dir FROM " . TABLE_TEMPLATE_SELECT . " WHERE template_language = 0 LIMIT 1");
        if (!$row->EOF && preg_match('/^[A-Za-z0-9_-]+$/', (string)$row->fields['template_dir'])) {
            $dir = (string)$row->fields['template_dir'];
        }
        $candidates = [DIR_FS_CATALOG . 'includes/templates/' . $dir . '/common/html_header.php'];
        foreach (glob(DIR_FS_CATALOG . 'zc_plugins/*/*/catalog/includes/templates/' . $dir . '/common/html_header.php') ?: [] as $pluginFile) {
            $candidates[] = $pluginFile;
        }
        $candidates[] = DIR_FS_CATALOG . 'includes/templates/template_default/common/html_header.php';
        foreach ($candidates as $file) {
            if (is_file($file)) {
                return $file;
            }
        }
        return '';
    }

    protected function tsLog($message, $severity = 'info')
    {
        if (function_exists('zen_record_admin_activity')) {
            zen_record_admin_activity($message, $severity);
        }
    }

    protected function tsGetConfigGroupId()
    {
        $check = $this->dbConn->Execute(
            "SELECT configuration_group_id
               FROM " . TABLE_CONFIGURATION_GROUP . "
              WHERE configuration_group_title = '" . $this->dbConn->prepare_input(self::CONFIG_GROUP_TITLE) . "'
              LIMIT 1"
        );

        return ($check->EOF) ? 0 : (int)$check->fields['configuration_group_id'];
    }

    protected function tsGetOrCreateConfigGroup()
    {
        $groupId = $this->tsGetConfigGroupId();
        if ($groupId > 0) {
            return $groupId;
        }

        $created = $this->executeInstallerSql(
            "INSERT INTO " . TABLE_CONFIGURATION_GROUP . "
                (configuration_group_title, configuration_group_description, sort_order, visible)
             VALUES
                ('" . $this->dbConn->prepare_input(self::CONFIG_GROUP_TITLE) . "',
                 'Cloudflare Turnstile bot check on the forms bots abuse.',
                 0, 1)"
        );
        if ($created === false) {
            return 0;
        }

        $groupId = $this->tsGetConfigGroupId();
        if ($groupId > 0) {
            // Zen Cart convention: a configuration group's sort_order matches its id.
            $this->executeInstallerSql(
                "UPDATE " . TABLE_CONFIGURATION_GROUP . "
                    SET sort_order = $groupId
                  WHERE configuration_group_id = $groupId
                  LIMIT 1"
            );
        }

        return $groupId;
    }

    protected function tsRegisterAdminPages($configGroupId)
    {
        // zen_register_admin_page() performs a plain INSERT, so clear first.
        zen_deregister_admin_pages(self::ADMIN_PAGE_KEYS);

        zen_register_admin_page(
            'configTurnstile',
            'BOX_CONFIGURATION_TURNSTILE',
            'FILENAME_CONFIGURATION',
            'gID=' . $configGroupId,
            'configuration',
            'Y',
            $configGroupId
        );
    }

    protected function tsSqlOrNull($value)
    {
        if ($value === '' || $value === null) {
            return 'NULL';
        }

        return "'" . $this->dbConn->prepare_input($value) . "'";
    }

    /**
     * INSERT IGNORE leaves a value the store owner has already set alone; the
     * UPDATE that follows refreshes only the plugin-owned metadata.
     */
    protected function tsAddConfigurationKeys($configGroupId)
    {
        foreach ($this->tsConfigurationKeys() as $key) {
            $sql =
                "INSERT IGNORE INTO " . TABLE_CONFIGURATION . "
                    (configuration_title, configuration_key, configuration_value, configuration_description,
                     configuration_group_id, sort_order, date_added, use_function, set_function, val_function)
                 VALUES
                    ('" . $this->dbConn->prepare_input($key['title']) . "',
                     '" . $this->dbConn->prepare_input($key['key']) . "',
                     '" . $this->dbConn->prepare_input($key['value']) . "',
                     '" . $this->dbConn->prepare_input($key['description']) . "',
                     " . (int)$configGroupId . ",
                     " . (int)$key['sort_order'] . ",
                     now(),
                     " . $this->tsSqlOrNull($key['use_function']) . ",
                     " . $this->tsSqlOrNull($key['set_function']) . ",
                     NULL)";
            if ($this->executeInstallerSql($sql) === false) {
                return false;
            }

            $update =
                "UPDATE " . TABLE_CONFIGURATION . "
                    SET configuration_title = '" . $this->dbConn->prepare_input($key['title']) . "',
                        configuration_description = '" . $this->dbConn->prepare_input($key['description']) . "',
                        configuration_group_id = " . (int)$configGroupId . ",
                        sort_order = " . (int)$key['sort_order'] . ",
                        use_function = " . $this->tsSqlOrNull($key['use_function']) . ",
                        set_function = " . $this->tsSqlOrNull($key['set_function']) . ",
                        last_modified = now()
                  WHERE configuration_key = '" . $this->dbConn->prepare_input($key['key']) . "'
                  LIMIT 1";
            if ($this->executeInstallerSql($update) === false) {
                return false;
            }
        }

        return true;
    }

    protected function tsConfigurationKeys()
    {
        return [
            [
                'key' => 'TURNSTILE_STATUS',
                'title' => 'Enable Cloudflare Turnstile?',
                'value' => 'true',
                'description' => 'Master switch. <strong>true</strong> shows the Turnstile check on the forms chosen below and refuses a submission whose check is missing or fails. Nothing happens until both keys below are filled in. <strong>false</strong> turns it off without uninstalling.',
                'sort_order' => 10,
                'use_function' => '',
                'set_function' => "zen_cfg_select_option(array('true', 'false'), ",
            ],
            [
                'key' => 'TURNSTILE_SITE_KEY',
                'title' => 'Site Key:',
                'value' => '',
                'description' => 'The <strong>Site Key</strong> from the Cloudflare dashboard, Turnstile, for a widget whose hostnames include this store\'s domain. It is public: it appears in the page.',
                'sort_order' => 20,
                'use_function' => '',
                'set_function' => '',
            ],
            [
                'key' => 'TURNSTILE_SECRET_KEY',
                'title' => 'Secret Key:',
                'value' => '',
                'description' => 'The <strong>Secret Key</strong> for the same widget. It is only ever sent to Cloudflare, from the server, to check each submission.',
                'sort_order' => 30,
                'use_function' => 'zen_cfg_password_display',
                'set_function' => '',
            ],
            [
                'key' => 'TURNSTILE_CREATE_ACCOUNT',
                'title' => 'Protect Create Account',
                'value' => 'true',
                'description' => "Check every form that creates an account: the Create Account page, the Login page's new-customer form and One Page Checkout's registration form.",
                'sort_order' => 40,
                'use_function' => '',
                'set_function' => "zen_cfg_select_option(array('true', 'false'), ",
            ],
            [
                'key' => 'TURNSTILE_CONTACT_US',
                'title' => 'Protect Contact Us',
                'value' => 'true',
                'description' => 'Check the Contact Us form before its email is sent.',
                'sort_order' => 50,
                'use_function' => '',
                'set_function' => "zen_cfg_select_option(array('true', 'false'), ",
            ],
            [
                'key' => 'TURNSTILE_ASK_A_QUESTION',
                'title' => 'Protect Ask a Question',
                'value' => 'true',
                'description' => 'Check the Ask a Question form on a product page before its email is sent.',
                'sort_order' => 60,
                'use_function' => '',
                'set_function' => "zen_cfg_select_option(array('true', 'false'), ",
            ],
            [
                'key' => 'TURNSTILE_REVIEWS',
                'title' => 'Protect Write a Review',
                'value' => 'true',
                'description' => 'Check the review form before the review is stored.',
                'sort_order' => 70,
                'use_function' => '',
                'set_function' => "zen_cfg_select_option(array('true', 'false'), ",
            ],
        ];
    }
}
