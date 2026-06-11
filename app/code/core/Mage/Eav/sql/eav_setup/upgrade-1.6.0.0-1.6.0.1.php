<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Eav
 */

/** @var Mage_Eav_Model_Entity_Setup $this */
$installer = $this;
$installer->startSetup();

$connection = $installer->getConnection();

$connection->delete(
    $this->getTable('eav/attribute'),
    $connection->prepareSqlCondition('attribute_code', 'enable_googlecheckout'),
);

$installer->endSetup();
