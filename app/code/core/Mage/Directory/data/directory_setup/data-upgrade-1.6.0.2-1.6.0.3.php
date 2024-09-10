<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Directory
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2022 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/** @var Mage_Core_Model_Resource_Setup $installer */
$installer = $this;

$data = [
    ['directory/country_region', 'default_name'],
    ['directory/country_region_name', 'name']
];

foreach ($data as $row) {
    $installer->getConnection()->update(
        $installer->getTable($row[0]),
        [$row[1]          => 'Vorarlberg'],
        [$row[1] . ' = ?' => 'Voralberg']
    );
}
