<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Core
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/** @var Mage_Core_Model_Resource_Setup $this */
$installer = $this;
$installer->startSetup();

// Drop MySQL-only `ON UPDATE CURRENT_TIMESTAMP` clause on timestamp columns that were originally
// declared with TIMESTAMP_INIT_UPDATE (value now managed via PHP _beforeSave, #856), and force
// explicit DEFAULT on TYPE_TIMESTAMP columns declared without one so MySQL's
// `explicit_defaults_for_timestamp = OFF` cannot silently inject
// `DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP` (#857).
if ($installer->getConnection() instanceof \Maho\Db\Adapter\Pdo\Mysql) {
    $installer->getConnection()->modifyColumn(
        $installer->getTable('core/flag'),
        'last_update',
        [
            'type'     => Maho\Db\Ddl\Table::TYPE_TIMESTAMP,
            'nullable' => false,
            'default'  => Maho\Db\Ddl\Table::TIMESTAMP_INIT,
            'comment'  => 'Date of Last Flag Update',
        ],
    );
    $installer->getConnection()->modifyColumn(
        $installer->getTable('core/email_queue'),
        'created_at',
        [
            'type'     => Maho\Db\Ddl\Table::TYPE_TIMESTAMP,
            'nullable' => false,
            'default'  => Maho\Db\Ddl\Table::TIMESTAMP_INIT,
            'comment'  => 'Creation Time',
        ],
    );
    $installer->getConnection()->modifyColumn(
        $installer->getTable('core/email_queue'),
        'processed_at',
        [
            'type'     => Maho\Db\Ddl\Table::TYPE_TIMESTAMP,
            'nullable' => true,
            'default'  => null,
            'comment'  => 'Finish Time',
        ],
    );
    $installer->getConnection()->modifyColumn(
        $installer->getTable('core/email_template'),
        'added_at',
        [
            'type'     => Maho\Db\Ddl\Table::TYPE_TIMESTAMP,
            'nullable' => false,
            'default'  => Maho\Db\Ddl\Table::TIMESTAMP_INIT,
            'comment'  => 'Date of Template Creation',
        ],
    );
    $installer->getConnection()->modifyColumn(
        $installer->getTable('core/email_template'),
        'modified_at',
        [
            'type'     => Maho\Db\Ddl\Table::TYPE_TIMESTAMP,
            'nullable' => false,
            'default'  => Maho\Db\Ddl\Table::TIMESTAMP_INIT,
            'comment'  => 'Date of Template Modification',
        ],
    );
    $installer->getConnection()->modifyColumn(
        $installer->getTable('core/config_data'),
        'updated_at',
        [
            'type'     => Maho\Db\Ddl\Table::TYPE_TIMESTAMP,
            'nullable' => false,
            'default'  => Maho\Db\Ddl\Table::TIMESTAMP_INIT,
            'comment'  => 'Last Update Time',
        ],
    );
}

$installer->endSetup();
