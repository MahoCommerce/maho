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

// Drop MySQL-only `ON UPDATE CURRENT_TIMESTAMP` clause on timestamp columns — value is now
// managed via PHP _beforeSave for cross-engine parity. `core/flag.last_update` was originally
// declared with TIMESTAMP_INIT_UPDATE (#856); `core/config_data.updated_at` was added via
// upgrade-1.6.0.8-1.6.0.9 without options, which on MySQL receives the implicit
// `ON UPDATE CURRENT_TIMESTAMP` injection (#857).
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
