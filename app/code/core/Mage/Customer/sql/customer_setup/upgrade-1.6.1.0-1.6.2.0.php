<?php

/**
 * Maho
 *
 * @package    Mage_Customer
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/** @var Mage_Customer_Model_Resource_Setup $this */
$installer = $this;

$disableAGCAttributeCode = 'disable_auto_group_change';

$installer->addAttribute('customer', $disableAGCAttributeCode, [
    'type'      => 'static',
    'label'     => 'Disable Automatic Group Change Based on VAT ID',
    'input'     => 'boolean',
    'backend'   => 'customer/attribute_backend_data_boolean',
    'position'  => 28,
    'required'  => false,
]);

$disableAGCAttribute = Mage::getSingleton('eav/config')
    ->getAttribute('customer', $disableAGCAttributeCode);
$disableAGCAttribute->setData('used_in_forms', [
    'adminhtml_customer',
]);
$disableAGCAttribute->save();

$attributesInfo = [
    'vat_id' => [
        'label'     => 'VAT number',
        'type'      => 'varchar',
        'input'     => 'text',
        'position'  => 140,
        'visible'   => true,
        'required'  => false,
    ],
    'vat_is_valid' => [
        'label'     => 'VAT number validity',
        'visible'   => false,
        'required'  => false,
        'type'      => 'int',
    ],
    'vat_request_id' => [
        'label'     => 'VAT number validation request ID',
        'type'      => 'varchar',
        'visible'   => false,
        'required'  => false,
    ],
    'vat_request_date' => [
        'label'     => 'VAT number validation request date',
        'type'      => 'varchar',
        'visible'   => false,
        'required'  => false,
    ],
    'vat_request_success' => [
        'label'     => 'VAT number validation request success',
        'visible'   => false,
        'required'  => false,
        'type'      => 'int',
    ],
];

foreach ($attributesInfo as $attributeCode => $attributeParams) {
    $installer->addAttribute('customer_address', $attributeCode, $attributeParams);
}

$vatAttribute = Mage::getSingleton('eav/config')->getAttribute('customer_address', 'vat_id');
$vatAttribute->setData('used_in_forms', [
    'adminhtml_customer_address',
    'customer_address_edit',
    'customer_register_address',
]);
$vatAttribute->save();
