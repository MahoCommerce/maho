<?php

/**
 * Maho
 *
 * @package    Mage_Catalog
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Catalog_Model_Resource_Product_Attribute_Backend_Groupprice extends Mage_Catalog_Model_Resource_Product_Attribute_Backend_Groupprice_Abstract
{
    #[\Override]
    protected function _construct()
    {
        $this->_init('catalog/product_attribute_group_price', 'value_id');
    }

    /**
     * Add is_percent column
     *
     * @param array $columns
     * @return array
     */
    #[\Override]
    protected function _loadPriceDataColumns($columns)
    {
        $columns               = parent::_loadPriceDataColumns($columns);
        $columns['is_percent'] = 'is_percent';
        return $columns;
    }
}
