<?php

/**
 * Maho
 *
 * @package    Mage_Paypal
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/** @var Mage_Paypal_Model_Resource_Setup $this */
$installer = $this;
$connection = $installer->getConnection();
$select = $connection->select()
    ->from(
        ['config' => $installer->getTable('core/config_data')],
        ['scope_id' => 'config.scope_id'],
    )
    ->where('config.path=?', 'paypal/general/merchant_country')
    ->where('config.value<>?', 'US');

$result = $connection->fetchAll($select);
foreach ($result as $row) {
    $connection->delete(
        $installer->getTable('core/config_data'),
        'path LIKE "%express_bml%"'
        . $connection->quoteInto(' AND scope_id = ?', $row['scope_id']),
    );
}

$installer->endSetup();
