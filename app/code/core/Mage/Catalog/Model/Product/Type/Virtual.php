<?php

/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Catalog
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Virtual product type implementation
 *
 * @category   Mage
 * @package    Mage_Catalog
 */
class Mage_Catalog_Model_Product_Type_Virtual extends Mage_Catalog_Model_Product_Type_Abstract
{
    /**
     * Check is virtual product
     *
     * @param Mage_Catalog_Model_Product $product
     * @return bool
     */
    #[\Override]
    public function isVirtual($product = null)
    {
        return true;
    }
}
