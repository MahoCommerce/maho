<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2025 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Tax
 */

/** @var Mage_Tax_Model_Resource_Setup $this */

$this->startSetup();

$taxTable = $this->getTable('tax/sales_order_tax');
$orderTable = $this->getTable('sales/order');

// adds FK_SALES_ORDER_TAX_ORDER back again
$this->getConnection()->addForeignKey(
    $this->getFkName($taxTable, 'order_id', $orderTable, 'entity_id'),
    $taxTable,
    'order_id',
    $orderTable,
    'entity_id',
    Maho\Db\Adapter\AdapterInterface::FK_ACTION_CASCADE,
    Maho\Db\Adapter\AdapterInterface::FK_ACTION_CASCADE,
    true,
);

$this->endSetup();
