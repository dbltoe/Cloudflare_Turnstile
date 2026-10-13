<?php
/**
 * Cloudflare Turnstile -- Plugin Manager installer.
 *
 * Only `executeInstallerSql()` is used for database work, and every step is
 * idempotent, so an upgrade is simply a re-run of the install.
 *
 * @package  Turnstile
 * @license  http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */

if (!defined('IS_ADMIN_FLAG')) {
    die('Illegal Access');
}

use Zencart\PluginSupport\ScriptedInstaller as ScriptedInstallBase;

class ScriptedInstaller extends ScriptedInstallBase
{
    public const CONFIG_GROUP_TITLE = 'Cloudflare Turnstile';

    public const ADMIN_PAGE_KEYS = ['configTurnstile'];

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

        $this->tsLog('Cloudflare Turnstile: installed/upgraded. It does nothing until a Site Key and Secret Key are entered under Configuration, Cloudflare Turnstile.', 'warning');

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

        return true;
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
                 'Cloudflare Turnstile bot check on the create-account forms.',
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
                'description' => 'Master switch. <strong>true</strong> shows the Turnstile check on every create-account form and refuses an account whose check is missing or fails. Nothing happens until both keys below are filled in. <strong>false</strong> turns it off without uninstalling.',
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
        ];
    }
}
