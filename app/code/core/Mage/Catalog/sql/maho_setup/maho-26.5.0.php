<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Catalog
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/** @var Mage_Catalog_Model_Resource_Setup $this */
$installer = $this;
$installer->startSetup();

// Drop MySQL-only `ON UPDATE CURRENT_TIMESTAMP` clause on updated_at columns that were originally
// declared with TIMESTAMP_INIT_UPDATE (#856), and force explicit DEFAULT on TYPE_TIMESTAMP columns
// that were declared without one so MySQL's `explicit_defaults_for_timestamp = OFF` cannot silently
// inject `DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP` (#857).
if ($installer->getConnection() instanceof \Maho\Db\Adapter\Pdo\Mysql) {
    $installer->getConnection()->modifyColumn(
        $installer->getTable('catalog/category_dynamic_rule'),
        'updated_at',
        [
            'type'     => Maho\Db\Ddl\Table::TYPE_TIMESTAMP,
            'nullable' => false,
            'default'  => Maho\Db\Ddl\Table::TIMESTAMP_INIT,
            'comment'  => 'Updated At',
        ],
    );
    $installer->getConnection()->modifyColumn(
        $installer->getTable('catalog/category'),
        'created_at',
        [
            'type'     => Maho\Db\Ddl\Table::TYPE_TIMESTAMP,
            'nullable' => false,
            'default'  => Maho\Db\Ddl\Table::TIMESTAMP_INIT,
            'comment'  => 'Creation Time',
        ],
    );
    $installer->getConnection()->modifyColumn(
        $installer->getTable('catalog/category'),
        'updated_at',
        [
            'type'     => Maho\Db\Ddl\Table::TYPE_TIMESTAMP,
            'nullable' => false,
            'default'  => Maho\Db\Ddl\Table::TIMESTAMP_INIT,
            'comment'  => 'Update Time',
        ],
    );
    $installer->getConnection()->modifyColumn(
        $installer->getTable('catalog/product'),
        'created_at',
        [
            'type'     => Maho\Db\Ddl\Table::TYPE_TIMESTAMP,
            'nullable' => false,
            'default'  => Maho\Db\Ddl\Table::TIMESTAMP_INIT,
            'comment'  => 'Creation Time',
        ],
    );
    $installer->getConnection()->modifyColumn(
        $installer->getTable('catalog/product'),
        'updated_at',
        [
            'type'     => Maho\Db\Ddl\Table::TYPE_TIMESTAMP,
            'nullable' => false,
            'default'  => Maho\Db\Ddl\Table::TIMESTAMP_INIT,
            'comment'  => 'Update Time',
        ],
    );
}

$installer->endSetup();
