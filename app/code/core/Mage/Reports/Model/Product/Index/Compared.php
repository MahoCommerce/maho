<?php

/**
 * Maho
 *
 * @package    Mage_Reports
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * @method Mage_Reports_Model_Resource_Product_Index_Compared _getResource()
 * @method Mage_Reports_Model_Resource_Product_Index_Compared getResource()
 * @method $this setVisitorId(int $value)
 * @method $this setCustomerId(int $value)
 * @method int getProductId()
 * @method $this setProductId(int $value)
 * @method $this setStoreId(int $value)
 * @method string getAddedAt()
 * @method $this setAddedAt(string $value)
 */
class Mage_Reports_Model_Product_Index_Compared extends Mage_Reports_Model_Product_Index_Abstract
{
    /**
     * Cache key name for Count of product index
     *
     * @var string
     */
    protected $_countCacheKey   = 'product_index_compared_count';

    #[\Override]
    protected function _construct()
    {
        $this->_init('reports/product_index_compared');
    }

    /**
     * Retrieve Exclude Product Ids List for Collection
     *
     * @return array
     */
    #[\Override]
    public function getExcludeProductIds()
    {
        $productIds = [];

        /** @var Mage_Catalog_Helper_Product_Compare $helper */
        $helper = Mage::helper('catalog/product_compare');

        if ($helper->hasItems()) {
            foreach ($helper->getItemCollection() as $item) {
                $productIds[] = $item->getEntityId();
            }
        }

        if (Mage::registry('current_product')) {
            $productIds[] = Mage::registry('current_product')->getId();
        }

        return array_unique($productIds);
    }
}
