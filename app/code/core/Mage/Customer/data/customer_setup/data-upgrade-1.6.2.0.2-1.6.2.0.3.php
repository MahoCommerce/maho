<?php

/**
 * Maho
 *
 * @package    Mage_Customer
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/** @var Mage_Customer_Model_Resource_Setup $this */
$installer = $this;
$datetimeType = 'datetime';
// implementation new type for static date attributes
$installer->updateAttribute('customer', 'created_at', 'frontend_input', $datetimeType);

// implement new input filter for datetime type attribute
$attribute = $installer->getAttribute('customer', 'created_at');

$attributeBind = [
    'input_filter' => $datetimeType,
];

$attributeWhere = $installer->getConnection()->quoteInto('attribute_id=?', $attribute['attribute_id']);
$installer->getConnection()->update($installer->getTable('customer/eav_attribute'), $attributeBind, $attributeWhere);
