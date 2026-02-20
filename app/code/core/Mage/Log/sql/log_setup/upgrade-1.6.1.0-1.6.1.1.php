<?php

/**
 * Maho
 *
 * @package    Mage_Log
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/** @var Mage_Core_Model_Resource_Setup $this */
$installer = $this;
$connection = $installer->getConnection();
$installer->startSetup();

// MySQL-specific migration: convert IP address columns to varbinary format
// PostgreSQL uses bytea type which is already set during initial schema creation
if ($connection instanceof Maho\Db\Adapter\Pdo\Mysql) {
    $connection->changeColumn(
        $installer->getTable('log/visitor_info'),
        'server_addr',
        'server_addr',
        'varbinary(16)',
    );

    $connection->update(
        $installer->getTable('log/visitor_info'),
        [
            'server_addr' => new Maho\Db\Expr('UNHEX(HEX(CAST(server_addr as UNSIGNED INT)))'),
        ],
    );

    $connection->changeColumn(
        $installer->getTable('log/visitor_info'),
        'remote_addr',
        'remote_addr',
        'varbinary(16)',
    );

    $connection->update(
        $installer->getTable('log/visitor_info'),
        [
            'remote_addr' => new Maho\Db\Expr('UNHEX(HEX(CAST(remote_addr as UNSIGNED INT)))'),
        ],
    );

    $connection->changeColumn(
        $installer->getTable('log/visitor_online'),
        'remote_addr',
        'remote_addr',
        'varbinary(16)',
    );

    $connection->update(
        $installer->getTable('log/visitor_online'),
        [
            'remote_addr' => new Maho\Db\Expr('UNHEX(HEX(CAST(remote_addr as UNSIGNED INT)))'),
        ],
    );
}

$installer->endSetup();
