<?php

/**
 * Maho
 *
 * @package    Mage_Rating
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/** @var Mage_Core_Model_Resource_Setup $this */
$installer = $this;
$connection = $installer->getConnection();
$installer->startSetup();

// MySQL-specific data migration: re-encode legacy numeric IPs into varbinary form
// (column type change is now handled by sql/schema.php).
if ($connection instanceof Maho\Db\Adapter\Pdo\Mysql) {
    $connection->update(
        $installer->getTable('rating/rating_option_vote'),
        [
            'remote_ip_long' => new Maho\Db\Expr('UNHEX(HEX(CAST(remote_ip_long as UNSIGNED INT)))'),
        ],
    );
}

$installer->endSetup();
