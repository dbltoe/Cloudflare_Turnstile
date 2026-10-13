<?php
/**
 * Stand-in for Zencart\PluginSupport\ScriptedInstaller: the three hooks every
 * supported release calls, and executeInstallerSql() through the fake database.
 */

namespace Zencart\PluginSupport;

class ScriptedInstaller
{
    protected $dbConn;
    protected $errorContainer;

    public function __construct($dbConn, $errorContainer)
    {
        $this->dbConn = $dbConn;
        $this->errorContainer = $errorContainer;
    }

    public function doInstall()
    {
        return $this->executeInstall();
    }

    public function doUninstall()
    {
        return $this->executeUninstall();
    }

    public function doUpgrade($oldVersion = null)
    {
        return $this->executeUpgrade($oldVersion);
    }

    protected function executeInstall()
    {
        return true;
    }

    protected function executeUninstall()
    {
        return true;
    }

    protected function executeUpgrade($oldVersion = null)
    {
        return true;
    }

    protected function executeInstallerSql($sql)
    {
        return $this->dbConn->Execute($sql) !== false;
    }
}
