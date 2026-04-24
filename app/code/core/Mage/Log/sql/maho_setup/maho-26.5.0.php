<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Log
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
        $installer->getTable('log/customer'),
        'login_at',
        [
            'type'     => Maho\Db\Ddl\Table::TYPE_TIMESTAMP,
            'nullable' => true,
            'default'  => null,
            'comment'  => 'Login Time',
        ],
    );
    $installer->getConnection()->modifyColumn(
        $installer->getTable('log/customer'),
        'logout_at',
        [
            'type'     => Maho\Db\Ddl\Table::TYPE_TIMESTAMP,
            'nullable' => true,
            'default'  => null,
            'comment'  => 'Logout Time',
        ],
    );
    $installer->getConnection()->modifyColumn(
        $installer->getTable('log/quote_table'),
        'created_at',
        [
            'type'     => Maho\Db\Ddl\Table::TYPE_TIMESTAMP,
            'nullable' => false,
            'default'  => Maho\Db\Ddl\Table::TIMESTAMP_INIT,
            'comment'  => 'Creation Time',
        ],
    );
    $installer->getConnection()->modifyColumn(
        $installer->getTable('log/quote_table'),
        'deleted_at',
        [
            'type'     => Maho\Db\Ddl\Table::TYPE_TIMESTAMP,
            'nullable' => true,
            'default'  => null,
            'comment'  => 'Deletion Time',
        ],
    );
    $installer->getConnection()->modifyColumn(
        $installer->getTable('log/summary_table'),
        'add_date',
        [
            'type'     => Maho\Db\Ddl\Table::TYPE_TIMESTAMP,
            'nullable' => true,
            'default'  => null,
            'comment'  => 'Date',
        ],
    );
    $installer->getConnection()->modifyColumn(
        $installer->getTable('log/url_table'),
        'visit_time',
        [
            'type'     => Maho\Db\Ddl\Table::TYPE_TIMESTAMP,
            'nullable' => true,
            'default'  => null,
            'comment'  => 'Visit Time',
        ],
    );
    $installer->getConnection()->modifyColumn(
        $installer->getTable('log/visitor'),
        'first_visit_at',
        [
            'type'     => Maho\Db\Ddl\Table::TYPE_TIMESTAMP,
            'nullable' => true,
            'default'  => null,
            'comment'  => 'First Visit Time',
        ],
    );
    $installer->getConnection()->modifyColumn(
        $installer->getTable('log/visitor'),
        'last_visit_at',
        [
            'type'     => Maho\Db\Ddl\Table::TYPE_TIMESTAMP,
            'nullable' => true,
            'default'  => null,
            'comment'  => 'Last Visit Time',
        ],
    );
    $installer->getConnection()->modifyColumn(
        $installer->getTable('log/visitor_online'),
        'first_visit_at',
        [
            'type'     => Maho\Db\Ddl\Table::TYPE_TIMESTAMP,
            'nullable' => true,
            'default'  => null,
            'comment'  => 'First Visit Time',
        ],
    );
    $installer->getConnection()->modifyColumn(
        $installer->getTable('log/visitor_online'),
        'last_visit_at',
        [
            'type'     => Maho\Db\Ddl\Table::TYPE_TIMESTAMP,
            'nullable' => true,
            'default'  => null,
            'comment'  => 'Last Visit Time',
        ],
    );
}

$installer->endSetup();
