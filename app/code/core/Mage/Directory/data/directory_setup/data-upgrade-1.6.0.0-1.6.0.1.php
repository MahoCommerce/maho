<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Directory
 */

/** @var Mage_Core_Model_Resource_Setup $this */
$installer = $this;
$installer->getConnection()->insert(
    $installer->getTable('core/config_data'),
    [
        'scope'    => 'default',
        'scope_id' => 0,
        'path'     => Mage_Directory_Helper_Data::XML_PATH_DISPLAY_ALL_STATES,
        'value'    => 1,
    ],
);

$select = $installer->getConnection()->select()
    ->from($installer->getTable('directory/country_region'), 'country_id')
    ->order('country_id');
$countries = $installer->getConnection()->fetchCol($select);

$installer->getConnection()->insert(
    $installer->getTable('core/config_data'),
    [
        'scope'    => 'default',
        'scope_id' => 0,
        'path'     => Mage_Directory_Helper_Data::XML_PATH_STATES_REQUIRED,
        'value'    => implode(',', $countries),
    ],
);
