<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Catalog
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2022 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/** @var Mage_Catalog_Model_Resource_Setup $installer */
$installer = $this;

$attribute = 'special_price';
$installer
    ->updateAttribute(
        Mage_Catalog_Model_Product::ENTITY,
        'special_price',
        'note',
        null
    )
    ->updateAttribute(
        Mage_Catalog_Model_Product::ENTITY,
        'special_price',
        'frontend_class',
        'validate-special-price'
    )
;
