<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_FeedManager
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/** @var Mage_Core_Model_Resource_Setup $this */
$installer = $this;
$installer->startSetup();

// Drop MySQL-only `ON UPDATE CURRENT_TIMESTAMP` clause on updated_at columns that were originally
// declared with TIMESTAMP_INIT_UPDATE. Value is now managed explicitly in PHP via _beforeSave()
// for cross-engine parity (see issue #856).
if ($installer->getConnection() instanceof \Maho\Db\Adapter\Pdo\Mysql) {
    $tables = [
        'feedmanager/destination',
        'feedmanager/feed',
        'feedmanager/dynamic_rule',
    ];
    foreach ($tables as $alias) {
        $installer->getConnection()->modifyColumn(
            $installer->getTable($alias),
            'updated_at',
            [
                'type'     => Maho\Db\Ddl\Table::TYPE_TIMESTAMP,
                'nullable' => false,
                'default'  => Maho\Db\Ddl\Table::TIMESTAMP_INIT,
                'comment'  => 'Updated At',
            ],
        );
    }
}

$installer->endSetup();
