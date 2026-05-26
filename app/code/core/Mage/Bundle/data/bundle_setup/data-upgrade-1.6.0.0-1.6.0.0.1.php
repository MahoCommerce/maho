<?php

/**
 * Maho
 *
 * @package    Mage_Bundle
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/** @var Mage_Catalog_Model_Resource_Setup $this */
$installer = $this;

$applyTo = explode(',', $installer->getAttribute(Mage_Catalog_Model_Product::ENTITY, 'group_price', 'apply_to'));
if (!in_array('bundle', $applyTo)) {
    $applyTo[] = 'bundle';
    $installer->updateAttribute(Mage_Catalog_Model_Product::ENTITY, 'group_price', 'apply_to', implode(',', $applyTo));
}
