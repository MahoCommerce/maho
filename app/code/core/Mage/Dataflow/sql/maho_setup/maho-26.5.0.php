<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Dataflow
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/** @var Mage_Core_Model_Resource_Setup $this */
$installer = $this;
$installer->startSetup();

// Force explicit DEFAULT on TYPE_TIMESTAMP columns originally declared without one.
// On MySQL with `explicit_defaults_for_timestamp = OFF` such TIMESTAMP NOT NULL columns
// silently receive `DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP`, which is
// engine-specific (PgSQL/SQLite don't do it). See issue #857.
if ($installer->getConnection() instanceof \Maho\Db\Adapter\Pdo\Mysql) {
    $installer->getConnection()->modifyColumn(
        $installer->getTable('dataflow/batch'),
        'created_at',
        [
            'type'     => Maho\Db\Ddl\Table::TYPE_TIMESTAMP,
            'nullable' => false,
            'default'  => Maho\Db\Ddl\Table::TIMESTAMP_INIT,
            'comment'  => 'Created At',
        ],
    );
    $installer->getConnection()->modifyColumn(
        $installer->getTable('dataflow/profile'),
        'created_at',
        [
            'type'     => Maho\Db\Ddl\Table::TYPE_TIMESTAMP,
            'nullable' => false,
            'default'  => Maho\Db\Ddl\Table::TIMESTAMP_INIT,
            'comment'  => 'Created At',
        ],
    );
    $installer->getConnection()->modifyColumn(
        $installer->getTable('dataflow/profile'),
        'updated_at',
        [
            'type'     => Maho\Db\Ddl\Table::TYPE_TIMESTAMP,
            'nullable' => false,
            'default'  => Maho\Db\Ddl\Table::TIMESTAMP_INIT,
            'comment'  => 'Updated At',
        ],
    );
    $installer->getConnection()->modifyColumn(
        $installer->getTable('dataflow/profile_history'),
        'performed_at',
        [
            'type'     => Maho\Db\Ddl\Table::TYPE_TIMESTAMP,
            'nullable' => true,
            'default'  => null,
            'comment'  => 'Performed At',
        ],
    );
    $installer->getConnection()->modifyColumn(
        $installer->getTable('dataflow/session'),
        'created_date',
        [
            'type'     => Maho\Db\Ddl\Table::TYPE_TIMESTAMP,
            'nullable' => false,
            'default'  => Maho\Db\Ddl\Table::TIMESTAMP_INIT,
            'comment'  => 'Created Date',
        ],
    );
}

$installer->endSetup();
