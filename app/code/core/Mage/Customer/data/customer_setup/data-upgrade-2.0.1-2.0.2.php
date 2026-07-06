<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Customer
 */

declare(strict_types=1);

/** @var Mage_Customer_Model_Resource_Setup $this */
$installer = $this;
$installer->startSetup();

// The customer-address "fax" field is an anachronism, no longer created on fresh installs (it was
// removed from Mage_Customer_Model_Resource_Setup::getDefaultEntities()). This upgrade cleans up
// existing installs without ever dropping merchant data: where stored fax values exist we keep the
// attribute and just hide it; where none exist (never used) we remove the attribute entirely. Fresh
// installs never created the attribute, so this simply no-ops for them.
$attributeId = $installer->getAttribute('customer_address', 'fax', 'attribute_id');
if ($attributeId) {
    $connection = $installer->getConnection();

    // Drop it from every customer/address form (admin + frontend) regardless of the branch below.
    $connection->delete(
        $installer->getTable('customer/form_attribute'),
        ['attribute_id = ?' => $attributeId],
    );

    $valueTable = $installer->getTable('customer/address_entity') . '_varchar';
    $hasStoredData = (int) $connection->fetchOne(
        $connection->select()
            ->from($valueTable, 'COUNT(*)')
            ->where('attribute_id = ?', $attributeId),
    );

    if ($hasStoredData > 0) {
        // Preserve the data, but hide the field. is_system=1 + is_visible=0 is the canonical
        // "hidden system attribute" state (see the historical customer_setup data scripts).
        $installer->updateAttribute('customer_address', 'fax', 'is_visible', 0);
    } else {
        $installer->removeAttribute('customer_address', 'fax');
    }
}

$installer->endSetup();
