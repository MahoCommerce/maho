<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Newsletter
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2022 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/** @var Mage_Core_Model_Resource_Setup $installer */
$installer = $this;

$subscriberTable = $installer->getTable('newsletter/subscriber');

$select = $installer->getConnection()->select()
    ->from(['main_table' => $subscriberTable])
    ->join(
        ['customer' => $installer->getTable('customer/entity')],
        'main_table.customer_id = customer.entity_id',
        ['website_id']
    )
    ->where('customer.website_id = 0');

$installer->getConnection()->query(
    $installer->getConnection()->deleteFromSelect($select, 'main_table')
);
