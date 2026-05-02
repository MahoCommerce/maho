<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Payment
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
    $installer->getConnection()->modifyColumn(
        $installer->getTable('payment/restriction'),
        'updated_at',
        ['default' => Maho\Db\Ddl\Table::TIMESTAMP_INIT],
    );
}

$installer->endSetup();
