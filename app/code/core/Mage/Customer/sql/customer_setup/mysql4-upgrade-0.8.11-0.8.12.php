<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Customer
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/** @var Mage_Customer_Model_Entity_Setup $installer */
$installer = $this;
$installer->startSetup();

$setup = $installer->getConnection();

/**
 *****************************************************************************
 * customer/account/create/
 *****************************************************************************
 */

$setup->insert($installer->getTable('eav/form_type'), [
    'code'      => 'customer_account_create',
    'label'     => 'customer_account_create',
    'is_system' => 1,
    'theme'     => '',
    'store_id'  => 0
]);
$formTypeId   = $setup->lastInsertId();
$entityTypeId = $installer->getEntityTypeId('customer');

$setup->insert($installer->getTable('eav/form_type_entity'), [
    'type_id'        => $formTypeId,
    'entity_type_id' => $entityTypeId
]);

$setup->insert($installer->getTable('eav/form_fieldset'), [
    'type_id'    => $formTypeId,
    'code'       => 'general',
    'sort_order' => 1
]);
$fieldsetId = $setup->lastInsertId();

$setup->insert($installer->getTable('eav/form_fieldset_label'), [
    'fieldset_id' => $fieldsetId,
    'store_id'    => 0,
    'label'       => 'Personal Information'
]);

$elementSort = 0;
$setup->insert($installer->getTable('eav/form_element'), [
    'type_id'       => $formTypeId,
    'fieldset_id'   => $fieldsetId,
    'attribute_id'  => $installer->getAttributeId($entityTypeId, 'firstname'),
    'sort_order'    => $elementSort++
]);
$setup->insert($installer->getTable('eav/form_element'), [
    'type_id'       => $formTypeId,
    'fieldset_id'   => $fieldsetId,
    'attribute_id'  => $installer->getAttributeId($entityTypeId, 'middlename'),
    'sort_order'    => $elementSort++
]);
$setup->insert($installer->getTable('eav/form_element'), [
    'type_id'       => $formTypeId,
    'fieldset_id'   => $fieldsetId,
    'attribute_id'  => $installer->getAttributeId($entityTypeId, 'lastname'),
    'sort_order'    => $elementSort++
]);
$setup->insert($installer->getTable('eav/form_element'), [
    'type_id'       => $formTypeId,
    'fieldset_id'   => $fieldsetId,
    'attribute_id'  => $installer->getAttributeId($entityTypeId, 'email'),
    'sort_order'    => $elementSort++
]);

/**
 *****************************************************************************
 * customer/account/edit/
 *****************************************************************************
 */

$setup->insert($installer->getTable('eav/form_type'), [
    'code'      => 'customer_account_edit',
    'label'     => 'customer_account_edit',
    'is_system' => 1,
    'theme'     => '',
    'store_id'  => 0
]);
$formTypeId   = $setup->lastInsertId();
$entityTypeId = $installer->getEntityTypeId('customer');

$setup->insert($installer->getTable('eav/form_type_entity'), [
    'type_id'        => $formTypeId,
    'entity_type_id' => $entityTypeId
]);

$setup->insert($installer->getTable('eav/form_fieldset'), [
    'type_id'    => $formTypeId,
    'code'       => 'general',
    'sort_order' => 1
]);
$fieldsetId = $setup->lastInsertId();

$setup->insert($installer->getTable('eav/form_fieldset_label'), [
    'fieldset_id' => $fieldsetId,
    'store_id'    => 0,
    'label'       => 'Account Information'
]);

$elementSort = 0;
$setup->insert($installer->getTable('eav/form_element'), [
    'type_id'       => $formTypeId,
    'fieldset_id'   => $fieldsetId,
    'attribute_id'  => $installer->getAttributeId($entityTypeId, 'firstname'),
    'sort_order'    => $elementSort++
]);
$setup->insert($installer->getTable('eav/form_element'), [
    'type_id'       => $formTypeId,
    'fieldset_id'   => $fieldsetId,
    'attribute_id'  => $installer->getAttributeId($entityTypeId, 'middlename'),
    'sort_order'    => $elementSort++
]);
$setup->insert($installer->getTable('eav/form_element'), [
    'type_id'       => $formTypeId,
    'fieldset_id'   => $fieldsetId,
    'attribute_id'  => $installer->getAttributeId($entityTypeId, 'lastname'),
    'sort_order'    => $elementSort++
]);
$setup->insert($installer->getTable('eav/form_element'), [
    'type_id'       => $formTypeId,
    'fieldset_id'   => $fieldsetId,
    'attribute_id'  => $installer->getAttributeId($entityTypeId, 'email'),
    'sort_order'    => $elementSort++
]);

/**
 *****************************************************************************
 * customer/address/edit
 *****************************************************************************
 */

$setup->insert($installer->getTable('eav/form_type'), [
    'code'      => 'customer_address_edit',
    'label'     => 'customer_address_edit',
    'is_system' => 1,
    'theme'     => '',
    'store_id'  => 0
]);
$formTypeId   = $setup->lastInsertId();
$entityTypeId = $installer->getEntityTypeId('customer_address');

$setup->insert($installer->getTable('eav/form_type_entity'), [
    'type_id'        => $formTypeId,
    'entity_type_id' => $entityTypeId
]);

$setup->insert($installer->getTable('eav/form_fieldset'), [
    'type_id'    => $formTypeId,
    'code'       => 'contact',
    'sort_order' => 1
]);
$fieldsetId = $setup->lastInsertId();

$setup->insert($installer->getTable('eav/form_fieldset_label'), [
    'fieldset_id' => $fieldsetId,
    'store_id'    => 0,
    'label'       => 'Contact Information'
]);

$elementSort = 0;
$setup->insert($installer->getTable('eav/form_element'), [
    'type_id'       => $formTypeId,
    'fieldset_id'   => $fieldsetId,
    'attribute_id'  => $installer->getAttributeId($entityTypeId, 'firstname'),
    'sort_order'    => $elementSort++
]);
$setup->insert($installer->getTable('eav/form_element'), [
    'type_id'       => $formTypeId,
    'fieldset_id'   => $fieldsetId,
    'attribute_id'  => $installer->getAttributeId($entityTypeId, 'middlename'),
    'sort_order'    => $elementSort++
]);
$setup->insert($installer->getTable('eav/form_element'), [
    'type_id'       => $formTypeId,
    'fieldset_id'   => $fieldsetId,
    'attribute_id'  => $installer->getAttributeId($entityTypeId, 'lastname'),
    'sort_order'    => $elementSort++
]);
$setup->insert($installer->getTable('eav/form_element'), [
    'type_id'       => $formTypeId,
    'fieldset_id'   => $fieldsetId,
    'attribute_id'  => $installer->getAttributeId($entityTypeId, 'company'),
    'sort_order'    => $elementSort++
]);
$setup->insert($installer->getTable('eav/form_element'), [
    'type_id'       => $formTypeId,
    'fieldset_id'   => $fieldsetId,
    'attribute_id'  => $installer->getAttributeId($entityTypeId, 'telephone'),
    'sort_order'    => $elementSort++
]);
$setup->insert($installer->getTable('eav/form_element'), [
    'type_id'       => $formTypeId,
    'fieldset_id'   => $fieldsetId,
    'attribute_id'  => $installer->getAttributeId($entityTypeId, 'fax'),
    'sort_order'    => $elementSort++
]);

$setup->insert($installer->getTable('eav/form_fieldset'), [
    'type_id'    => $formTypeId,
    'code'       => 'address',
    'sort_order' => 2
]);
$fieldsetId = $setup->lastInsertId();

$setup->insert($installer->getTable('eav/form_fieldset_label'), [
    'fieldset_id' => $fieldsetId,
    'store_id'    => 0,
    'label'       => 'Address'
]);

$elementSort = 0;
$setup->insert($installer->getTable('eav/form_element'), [
    'type_id'       => $formTypeId,
    'fieldset_id'   => $fieldsetId,
    'attribute_id'  => $installer->getAttributeId($entityTypeId, 'street'),
    'sort_order'    => $elementSort++
]);
$setup->insert($installer->getTable('eav/form_element'), [
    'type_id'       => $formTypeId,
    'fieldset_id'   => $fieldsetId,
    'attribute_id'  => $installer->getAttributeId($entityTypeId, 'city'),
    'sort_order'    => $elementSort++
]);
$setup->insert($installer->getTable('eav/form_element'), [
    'type_id'       => $formTypeId,
    'fieldset_id'   => $fieldsetId,
    'attribute_id'  => $installer->getAttributeId($entityTypeId, 'region'),
    'sort_order'    => $elementSort++
]);
$setup->insert($installer->getTable('eav/form_element'), [
    'type_id'       => $formTypeId,
    'fieldset_id'   => $fieldsetId,
    'attribute_id'  => $installer->getAttributeId($entityTypeId, 'postcode'),
    'sort_order'    => $elementSort++
]);
$setup->insert($installer->getTable('eav/form_element'), [
    'type_id'       => $formTypeId,
    'fieldset_id'   => $fieldsetId,
    'attribute_id'  => $installer->getAttributeId($entityTypeId, 'country_id'),
    'sort_order'    => $elementSort++
]);

$installer->endSetup();
