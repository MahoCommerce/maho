<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2017-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Checkout
 */

/** @var Mage_Checkout_Model_Resource_Setup $this */
$installer = $this;
$installer->startSetup();

$setup = $installer->getConnection();

$select = $setup->select()
    ->from($installer->getTable('core/config_data'), 'COUNT(*)')
    ->where('path=?', 'customer/address/prefix_show')
    ->where('value NOT LIKE ?', '0');
$showPrefix = (bool) Mage::helper('customer/address')->getConfig('prefix_show')
    || ($setup->fetchOne($select) > 0);

$select = $setup->select()
    ->from($installer->getTable('core/config_data'), 'COUNT(*)')
    ->where('path=?', 'customer/address/middlename_show')
    ->where('value NOT LIKE ?', '0');
$showMiddlename = (bool) Mage::helper('customer/address')->getConfig('middlename_show')
    || ($setup->fetchOne($select) > 0);

$select = $setup->select()
    ->from($installer->getTable('core/config_data'), 'COUNT(*)')
    ->where('path=?', 'customer/address/suffix_show')
    ->where('value NOT LIKE ?', '0');
$showSuffix = (bool) Mage::helper('customer/address')->getConfig('suffix_show')
    || ($setup->fetchOne($select) > 0);

$select = $setup->select()
    ->from($installer->getTable('core/config_data'), 'COUNT(*)')
    ->where('path=?', 'customer/address/dob_show')
    ->where('value NOT LIKE ?', '0');
$showDob = (bool) Mage::helper('customer/address')->getConfig('dob_show')
    || ($setup->fetchOne($select) > 0);

$select = $setup->select()
    ->from($installer->getTable('core/config_data'), 'COUNT(*)')
    ->where('path=?', 'customer/address/taxvat_show')
    ->where('value NOT LIKE ?', '0');
$showTaxVat = (bool) Mage::helper('customer/address')->getConfig('taxvat_show')
    || ($setup->fetchOne($select) > 0);

$customerEntityTypeId = $installer->getEntityTypeId('customer');
$addressEntityTypeId  = $installer->getEntityTypeId('customer_address');

/**
 *****************************************************************************
 * checkout/onepage/register
 *****************************************************************************
 */

$setup->insert($installer->getTable('eav/form_type'), [
    'code'      => 'checkout_onepage_register',
    'label'     => 'checkout_onepage_register',
    'is_system' => 1,
    'theme'     => '',
    'store_id'  => 0,
]);
$formTypeId   = $setup->lastInsertId($installer->getTable('eav/form_type'));

$setup->insert($installer->getTable('eav/form_type_entity'), [
    'type_id'        => $formTypeId,
    'entity_type_id' => $customerEntityTypeId,
]);
$setup->insert($installer->getTable('eav/form_type_entity'), [
    'type_id'        => $formTypeId,
    'entity_type_id' => $addressEntityTypeId,
]);

$elementSort = 0;
if ($showPrefix) {
    $setup->insert($installer->getTable('eav/form_element'), [
        'type_id'       => $formTypeId,
        'fieldset_id'   => null,
        'attribute_id'  => $installer->getAttributeId($addressEntityTypeId, 'prefix'),
        'sort_order'    => $elementSort++,
    ]);
}
$setup->insert($installer->getTable('eav/form_element'), [
    'type_id'       => $formTypeId,
    'fieldset_id'   => null,
    'attribute_id'  => $installer->getAttributeId($addressEntityTypeId, 'firstname'),
    'sort_order'    => $elementSort++,
]);
if ($showMiddlename) {
    $setup->insert($installer->getTable('eav/form_element'), [
        'type_id'       => $formTypeId,
        'fieldset_id'   => null,
        'attribute_id'  => $installer->getAttributeId($addressEntityTypeId, 'middlename'),
        'sort_order'    => $elementSort++,
    ]);
}
$setup->insert($installer->getTable('eav/form_element'), [
    'type_id'       => $formTypeId,
    'fieldset_id'   => null,
    'attribute_id'  => $installer->getAttributeId($addressEntityTypeId, 'lastname'),
    'sort_order'    => $elementSort++,
]);
if ($showSuffix) {
    $setup->insert($installer->getTable('eav/form_element'), [
        'type_id'       => $formTypeId,
        'fieldset_id'   => null,
        'attribute_id'  => $installer->getAttributeId($addressEntityTypeId, 'suffix'),
        'sort_order'    => $elementSort++,
    ]);
}
$setup->insert($installer->getTable('eav/form_element'), [
    'type_id'       => $formTypeId,
    'fieldset_id'   => null,
    'attribute_id'  => $installer->getAttributeId($addressEntityTypeId, 'company'),
    'sort_order'    => $elementSort++,
]);
$setup->insert($installer->getTable('eav/form_element'), [
    'type_id'       => $formTypeId,
    'fieldset_id'   => null,
    'attribute_id'  => $installer->getAttributeId($customerEntityTypeId, 'email'),
    'sort_order'    => $elementSort++,
]);
$setup->insert($installer->getTable('eav/form_element'), [
    'type_id'       => $formTypeId,
    'fieldset_id'   => null,
    'attribute_id'  => $installer->getAttributeId($addressEntityTypeId, 'street'),
    'sort_order'    => $elementSort++,
]);
$setup->insert($installer->getTable('eav/form_element'), [
    'type_id'       => $formTypeId,
    'fieldset_id'   => null,
    'attribute_id'  => $installer->getAttributeId($addressEntityTypeId, 'city'),
    'sort_order'    => $elementSort++,
]);
$setup->insert($installer->getTable('eav/form_element'), [
    'type_id'       => $formTypeId,
    'fieldset_id'   => null,
    'attribute_id'  => $installer->getAttributeId($addressEntityTypeId, 'region'),
    'sort_order'    => $elementSort++,
]);
$setup->insert($installer->getTable('eav/form_element'), [
    'type_id'       => $formTypeId,
    'fieldset_id'   => null,
    'attribute_id'  => $installer->getAttributeId($addressEntityTypeId, 'postcode'),
    'sort_order'    => $elementSort++,
]);
$setup->insert($installer->getTable('eav/form_element'), [
    'type_id'       => $formTypeId,
    'fieldset_id'   => null,
    'attribute_id'  => $installer->getAttributeId($addressEntityTypeId, 'country_id'),
    'sort_order'    => $elementSort++,
]);
$setup->insert($installer->getTable('eav/form_element'), [
    'type_id'       => $formTypeId,
    'fieldset_id'   => null,
    'attribute_id'  => $installer->getAttributeId($addressEntityTypeId, 'telephone'),
    'sort_order'    => $elementSort++,
]);
$setup->insert($installer->getTable('eav/form_element'), [
    'type_id'       => $formTypeId,
    'fieldset_id'   => null,
    'attribute_id'  => $installer->getAttributeId($addressEntityTypeId, 'fax'),
    'sort_order'    => $elementSort++,
]);
if ($showDob) {
    $setup->insert($installer->getTable('eav/form_element'), [
        'type_id'       => $formTypeId,
        'fieldset_id'   => null,
        'attribute_id'  => $installer->getAttributeId($customerEntityTypeId, 'dob'),
        'sort_order'    => $elementSort++,
    ]);
}
if ($showTaxVat) {
    $setup->insert($installer->getTable('eav/form_element'), [
        'type_id'       => $formTypeId,
        'fieldset_id'   => null,
        'attribute_id'  => $installer->getAttributeId($customerEntityTypeId, 'taxvat'),
        'sort_order'    => $elementSort++,
    ]);
}

/**
 *****************************************************************************
 * checkout/onepage/register_guest
 *****************************************************************************
 */

$setup->insert($installer->getTable('eav/form_type'), [
    'code'      => 'checkout_onepage_register_guest',
    'label'     => 'checkout_onepage_register_guest',
    'is_system' => 1,
    'theme'     => '',
    'store_id'  => 0,
]);
$formTypeId   = $setup->lastInsertId($installer->getTable('eav/form_type'));

$setup->insert($installer->getTable('eav/form_type_entity'), [
    'type_id'        => $formTypeId,
    'entity_type_id' => $customerEntityTypeId,
]);
$setup->insert($installer->getTable('eav/form_type_entity'), [
    'type_id'        => $formTypeId,
    'entity_type_id' => $addressEntityTypeId,
]);

$elementSort = 0;
if ($showPrefix) {
    $setup->insert($installer->getTable('eav/form_element'), [
        'type_id'       => $formTypeId,
        'fieldset_id'   => null,
        'attribute_id'  => $installer->getAttributeId($addressEntityTypeId, 'prefix'),
        'sort_order'    => $elementSort++,
    ]);
}
$setup->insert($installer->getTable('eav/form_element'), [
    'type_id'       => $formTypeId,
    'fieldset_id'   => null,
    'attribute_id'  => $installer->getAttributeId($addressEntityTypeId, 'firstname'),
    'sort_order'    => $elementSort++,
]);
if ($showMiddlename) {
    $setup->insert($installer->getTable('eav/form_element'), [
        'type_id'       => $formTypeId,
        'fieldset_id'   => null,
        'attribute_id'  => $installer->getAttributeId($addressEntityTypeId, 'middlename'),
        'sort_order'    => $elementSort++,
    ]);
}
$setup->insert($installer->getTable('eav/form_element'), [
    'type_id'       => $formTypeId,
    'fieldset_id'   => null,
    'attribute_id'  => $installer->getAttributeId($addressEntityTypeId, 'lastname'),
    'sort_order'    => $elementSort++,
]);
if ($showSuffix) {
    $setup->insert($installer->getTable('eav/form_element'), [
        'type_id'       => $formTypeId,
        'fieldset_id'   => null,
        'attribute_id'  => $installer->getAttributeId($addressEntityTypeId, 'suffix'),
        'sort_order'    => $elementSort++,
    ]);
}
$setup->insert($installer->getTable('eav/form_element'), [
    'type_id'       => $formTypeId,
    'fieldset_id'   => null,
    'attribute_id'  => $installer->getAttributeId($addressEntityTypeId, 'company'),
    'sort_order'    => $elementSort++,
]);
$setup->insert($installer->getTable('eav/form_element'), [
    'type_id'       => $formTypeId,
    'fieldset_id'   => null,
    'attribute_id'  => $installer->getAttributeId($customerEntityTypeId, 'email'),
    'sort_order'    => $elementSort++,
]);
$setup->insert($installer->getTable('eav/form_element'), [
    'type_id'       => $formTypeId,
    'fieldset_id'   => null,
    'attribute_id'  => $installer->getAttributeId($addressEntityTypeId, 'street'),
    'sort_order'    => $elementSort++,
]);
$setup->insert($installer->getTable('eav/form_element'), [
    'type_id'       => $formTypeId,
    'fieldset_id'   => null,
    'attribute_id'  => $installer->getAttributeId($addressEntityTypeId, 'city'),
    'sort_order'    => $elementSort++,
]);
$setup->insert($installer->getTable('eav/form_element'), [
    'type_id'       => $formTypeId,
    'fieldset_id'   => null,
    'attribute_id'  => $installer->getAttributeId($addressEntityTypeId, 'region'),
    'sort_order'    => $elementSort++,
]);
$setup->insert($installer->getTable('eav/form_element'), [
    'type_id'       => $formTypeId,
    'fieldset_id'   => null,
    'attribute_id'  => $installer->getAttributeId($addressEntityTypeId, 'postcode'),
    'sort_order'    => $elementSort++,
]);
$setup->insert($installer->getTable('eav/form_element'), [
    'type_id'       => $formTypeId,
    'fieldset_id'   => null,
    'attribute_id'  => $installer->getAttributeId($addressEntityTypeId, 'country_id'),
    'sort_order'    => $elementSort++,
]);
$setup->insert($installer->getTable('eav/form_element'), [
    'type_id'       => $formTypeId,
    'fieldset_id'   => null,
    'attribute_id'  => $installer->getAttributeId($addressEntityTypeId, 'telephone'),
    'sort_order'    => $elementSort++,
]);
$setup->insert($installer->getTable('eav/form_element'), [
    'type_id'       => $formTypeId,
    'fieldset_id'   => null,
    'attribute_id'  => $installer->getAttributeId($addressEntityTypeId, 'fax'),
    'sort_order'    => $elementSort++,
]);
if ($showDob) {
    $setup->insert($installer->getTable('eav/form_element'), [
        'type_id'       => $formTypeId,
        'fieldset_id'   => null,
        'attribute_id'  => $installer->getAttributeId($customerEntityTypeId, 'dob'),
        'sort_order'    => $elementSort++,
    ]);
}
if ($showTaxVat) {
    $setup->insert($installer->getTable('eav/form_element'), [
        'type_id'       => $formTypeId,
        'fieldset_id'   => null,
        'attribute_id'  => $installer->getAttributeId($customerEntityTypeId, 'taxvat'),
        'sort_order'    => $elementSort++,
    ]);
}

/**
 *****************************************************************************
 * checkout/onepage/billing_address
 *****************************************************************************
 */

$setup->insert($installer->getTable('eav/form_type'), [
    'code'      => 'checkout_onepage_billing_address',
    'label'     => 'checkout_onepage_billing_address',
    'is_system' => 1,
    'theme'     => '',
    'store_id'  => 0,
]);
$formTypeId   = $setup->lastInsertId($installer->getTable('eav/form_type'));

$setup->insert($installer->getTable('eav/form_type_entity'), [
    'type_id'        => $formTypeId,
    'entity_type_id' => $addressEntityTypeId,
]);

$elementSort = 0;
if ($showPrefix) {
    $setup->insert($installer->getTable('eav/form_element'), [
        'type_id'       => $formTypeId,
        'fieldset_id'   => null,
        'attribute_id'  => $installer->getAttributeId($addressEntityTypeId, 'prefix'),
        'sort_order'    => $elementSort++,
    ]);
}
$setup->insert($installer->getTable('eav/form_element'), [
    'type_id'       => $formTypeId,
    'fieldset_id'   => null,
    'attribute_id'  => $installer->getAttributeId($addressEntityTypeId, 'firstname'),
    'sort_order'    => $elementSort++,
]);
if ($showMiddlename) {
    $setup->insert($installer->getTable('eav/form_element'), [
        'type_id'       => $formTypeId,
        'fieldset_id'   => null,
        'attribute_id'  => $installer->getAttributeId($addressEntityTypeId, 'middlename'),
        'sort_order'    => $elementSort++,
    ]);
}
$setup->insert($installer->getTable('eav/form_element'), [
    'type_id'       => $formTypeId,
    'fieldset_id'   => null,
    'attribute_id'  => $installer->getAttributeId($addressEntityTypeId, 'lastname'),
    'sort_order'    => $elementSort++,
]);
if ($showSuffix) {
    $setup->insert($installer->getTable('eav/form_element'), [
        'type_id'       => $formTypeId,
        'fieldset_id'   => null,
        'attribute_id'  => $installer->getAttributeId($addressEntityTypeId, 'suffix'),
        'sort_order'    => $elementSort++,
    ]);
}
$setup->insert($installer->getTable('eav/form_element'), [
    'type_id'       => $formTypeId,
    'fieldset_id'   => null,
    'attribute_id'  => $installer->getAttributeId($addressEntityTypeId, 'company'),
    'sort_order'    => $elementSort++,
]);
$setup->insert($installer->getTable('eav/form_element'), [
    'type_id'       => $formTypeId,
    'fieldset_id'   => null,
    'attribute_id'  => $installer->getAttributeId($addressEntityTypeId, 'street'),
    'sort_order'    => $elementSort++,
]);
$setup->insert($installer->getTable('eav/form_element'), [
    'type_id'       => $formTypeId,
    'fieldset_id'   => null,
    'attribute_id'  => $installer->getAttributeId($addressEntityTypeId, 'city'),
    'sort_order'    => $elementSort++,
]);
$setup->insert($installer->getTable('eav/form_element'), [
    'type_id'       => $formTypeId,
    'fieldset_id'   => null,
    'attribute_id'  => $installer->getAttributeId($addressEntityTypeId, 'region'),
    'sort_order'    => $elementSort++,
]);
$setup->insert($installer->getTable('eav/form_element'), [
    'type_id'       => $formTypeId,
    'fieldset_id'   => null,
    'attribute_id'  => $installer->getAttributeId($addressEntityTypeId, 'postcode'),
    'sort_order'    => $elementSort++,
]);
$setup->insert($installer->getTable('eav/form_element'), [
    'type_id'       => $formTypeId,
    'fieldset_id'   => null,
    'attribute_id'  => $installer->getAttributeId($addressEntityTypeId, 'country_id'),
    'sort_order'    => $elementSort++,
]);
$setup->insert($installer->getTable('eav/form_element'), [
    'type_id'       => $formTypeId,
    'fieldset_id'   => null,
    'attribute_id'  => $installer->getAttributeId($addressEntityTypeId, 'telephone'),
    'sort_order'    => $elementSort++,
]);
$setup->insert($installer->getTable('eav/form_element'), [
    'type_id'       => $formTypeId,
    'fieldset_id'   => null,
    'attribute_id'  => $installer->getAttributeId($addressEntityTypeId, 'fax'),
    'sort_order'    => $elementSort++,
]);

/**
 *****************************************************************************
 * checkout/onepage/shipping_address
 *****************************************************************************
 */

$setup->insert($installer->getTable('eav/form_type'), [
    'code'      => 'checkout_onepage_shipping_address',
    'label'     => 'checkout_onepage_shipping_address',
    'is_system' => 1,
    'theme'     => '',
    'store_id'  => 0,
]);
$formTypeId   = $setup->lastInsertId($installer->getTable('eav/form_type'));

$setup->insert($installer->getTable('eav/form_type_entity'), [
    'type_id'        => $formTypeId,
    'entity_type_id' => $addressEntityTypeId,
]);

$elementSort = 0;
if ($showPrefix) {
    $setup->insert($installer->getTable('eav/form_element'), [
        'type_id'       => $formTypeId,
        'fieldset_id'   => null,
        'attribute_id'  => $installer->getAttributeId($addressEntityTypeId, 'prefix'),
        'sort_order'    => $elementSort++,
    ]);
}
$setup->insert($installer->getTable('eav/form_element'), [
    'type_id'       => $formTypeId,
    'fieldset_id'   => null,
    'attribute_id'  => $installer->getAttributeId($addressEntityTypeId, 'firstname'),
    'sort_order'    => $elementSort++,
]);
if ($showMiddlename) {
    $setup->insert($installer->getTable('eav/form_element'), [
        'type_id'       => $formTypeId,
        'fieldset_id'   => null,
        'attribute_id'  => $installer->getAttributeId($addressEntityTypeId, 'middlename'),
        'sort_order'    => $elementSort++,
    ]);
}
$setup->insert($installer->getTable('eav/form_element'), [
    'type_id'       => $formTypeId,
    'fieldset_id'   => null,
    'attribute_id'  => $installer->getAttributeId($addressEntityTypeId, 'lastname'),
    'sort_order'    => $elementSort++,
]);
if ($showSuffix) {
    $setup->insert($installer->getTable('eav/form_element'), [
        'type_id'       => $formTypeId,
        'fieldset_id'   => null,
        'attribute_id'  => $installer->getAttributeId($addressEntityTypeId, 'suffix'),
        'sort_order'    => $elementSort++,
    ]);
}
$setup->insert($installer->getTable('eav/form_element'), [
    'type_id'       => $formTypeId,
    'fieldset_id'   => null,
    'attribute_id'  => $installer->getAttributeId($addressEntityTypeId, 'company'),
    'sort_order'    => $elementSort++,
]);
$setup->insert($installer->getTable('eav/form_element'), [
    'type_id'       => $formTypeId,
    'fieldset_id'   => null,
    'attribute_id'  => $installer->getAttributeId($addressEntityTypeId, 'street'),
    'sort_order'    => $elementSort++,
]);
$setup->insert($installer->getTable('eav/form_element'), [
    'type_id'       => $formTypeId,
    'fieldset_id'   => null,
    'attribute_id'  => $installer->getAttributeId($addressEntityTypeId, 'city'),
    'sort_order'    => $elementSort++,
]);
$setup->insert($installer->getTable('eav/form_element'), [
    'type_id'       => $formTypeId,
    'fieldset_id'   => null,
    'attribute_id'  => $installer->getAttributeId($addressEntityTypeId, 'region'),
    'sort_order'    => $elementSort++,
]);
$setup->insert($installer->getTable('eav/form_element'), [
    'type_id'       => $formTypeId,
    'fieldset_id'   => null,
    'attribute_id'  => $installer->getAttributeId($addressEntityTypeId, 'postcode'),
    'sort_order'    => $elementSort++,
]);
$setup->insert($installer->getTable('eav/form_element'), [
    'type_id'       => $formTypeId,
    'fieldset_id'   => null,
    'attribute_id'  => $installer->getAttributeId($addressEntityTypeId, 'country_id'),
    'sort_order'    => $elementSort++,
]);
$setup->insert($installer->getTable('eav/form_element'), [
    'type_id'       => $formTypeId,
    'fieldset_id'   => null,
    'attribute_id'  => $installer->getAttributeId($addressEntityTypeId, 'telephone'),
    'sort_order'    => $elementSort++,
]);
$setup->insert($installer->getTable('eav/form_element'), [
    'type_id'       => $formTypeId,
    'fieldset_id'   => null,
    'attribute_id'  => $installer->getAttributeId($addressEntityTypeId, 'fax'),
    'sort_order'    => $elementSort++,
]);

$table = $installer->getTable('core/config_data');

$select = $setup->select()
    ->from($table, ['config_id', 'value'])
    ->where('path = ?', 'checkout/options/onepage_checkout_disabled');

$data = $setup->fetchAll($select);

if ($data) {
    try {
        $setup->beginTransaction();

        foreach ($data as $value) {
            $bind = [
                'path'  => 'checkout/options/onepage_checkout_enabled',
                'value' => !((bool) $value['value']),
            ];
            $where = 'config_id = ' . $value['config_id'];
            $setup->update($table, $bind, $where);
        }

        $setup->commit();
    } catch (Exception $e) {
        $setup->rollBack();
        throw $e;
    }
}

$installer->endSetup();
