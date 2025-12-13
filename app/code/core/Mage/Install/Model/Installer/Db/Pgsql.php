<?php

/**
 * Maho
 *
 * @package    Mage_Install
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

class Mage_Install_Model_Installer_Db_Pgsql extends Mage_Install_Model_Installer_Db_Abstract
{
    /**
     * Retrieve DB server version
     *
     * @return string (string version number | 'undefined')
     */
    public function getVersion()
    {
        $version = $this->_getConnection()
            ->fetchOne('SELECT version()');
        $version = $version ?: 'undefined';
        $match = [];
        // PostgreSQL version format: "PostgreSQL 16.1 on ..."
        if (preg_match('#PostgreSQL\s+([0-9\.]+)#i', $version, $match)) {
            $version = $match[1];
        }
        return $version;
    }

    /**
     * Check storage engine support
     * PostgreSQL doesn't have pluggable storage engines like MySQL,
     * it uses its own storage system which is always available.
     *
     * @return bool
     */
    #[\Override]
    public function supportEngine()
    {
        // PostgreSQL always supports transactional storage
        return true;
    }
}
