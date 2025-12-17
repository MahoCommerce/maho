<?php

/**
 * Maho
 *
 * @package    Mage_Install
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

class Mage_Install_Model_Installer_Db_Sqlite extends Mage_Install_Model_Installer_Db_Abstract
{
    /**
     * Retrieve connection data from config
     *
     * SQLite uses file path instead of host/user/password
     */
    #[\Override]
    public function getConnectionData(): array
    {
        if (!$this->_connectionData) {
            // SQLite uses path instead of host/user/password
            $dbPath = $this->_configData['db_path'] ?? $this->_configData['db_name'] ?? '';

            // If path is relative, make it absolute from Mage root
            if ($dbPath && $dbPath !== ':memory:' && !str_starts_with($dbPath, '/')) {
                $dbPath = Mage::getBaseDir('var') . DS . 'db' . DS . $dbPath;
            }

            // Ensure the directory exists
            if ($dbPath && $dbPath !== ':memory:') {
                $dir = dirname($dbPath);
                if (!is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }
            }

            $connectionData = [
                'path'      => $dbPath,
                'dbname'    => $dbPath,
            ];
            $this->_connectionData = $connectionData;
        }
        return $this->_connectionData;
    }

    /**
     * Retrieve DB server version
     *
     * @return string (string version number | 'undefined')
     */
    public function getVersion()
    {
        $version = $this->_getConnection()
            ->fetchOne('SELECT sqlite_version()');
        return $version ?: 'undefined';
    }

    /**
     * Check storage engine support
     * SQLite uses a single integrated storage engine that is always available.
     */
    #[\Override]
    public function supportEngine(): bool
    {
        // SQLite always supports its integrated storage engine
        return true;
    }
}
