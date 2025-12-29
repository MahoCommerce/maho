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
     * Check storage engine support
     * PostgreSQL doesn't have pluggable storage engines like MySQL,
     * it uses its own storage system which is always available.
     * This method also validates the database connection.
     */
    #[\Override]
    public function supportEngine(): bool
    {
        // Test connection by running a simple query
        $this->_getConnection()->fetchOne('SELECT 1');
        return true;
    }
}
