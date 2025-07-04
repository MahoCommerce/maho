<?php

/**
 * Maho
 *
 * @package    Mage_Directory
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
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
