<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Directory
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/** @var Mage_Core_Model_Resource_Setup $installer */
$installer = $this;
$installer->startSetup();

// Delete non-existent and unofficial iso-3166-1 codes
$installer->run("
    DELETE FROM {$installer->getTable('directory/country')}
    WHERE country_id IN('FX','CS')
");

$installer->endSetup();
