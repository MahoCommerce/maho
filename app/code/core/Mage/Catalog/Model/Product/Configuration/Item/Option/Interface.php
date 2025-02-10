<?php

/**
 * Maho
 *
 * @package    Mage_Catalog
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Interface of product configurational item option
 *
 * @package    Mage_Catalog
 */
interface Mage_Catalog_Model_Product_Configuration_Item_Option_Interface
{
    /**
     * Retrieve value associated with this option
     *
     * @return mixed
     */
    public function getValue();
}
