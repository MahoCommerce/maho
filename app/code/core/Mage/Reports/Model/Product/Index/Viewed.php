<?php

/**
 * Maho
 *
 * @package    Mage_Reports
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * @method Mage_Reports_Model_Resource_Product_Index_Viewed _getResource()
 * @method Mage_Reports_Model_Resource_Product_Index_Viewed getResource()
 * @method $this setVisitorId(int $value)
 * @method $this setCustomerId(int $value)
 * @method int getProductId()
 * @method $this setProductId(int $value)
 * @method $this setStoreId(int $value)
 * @method string getAddedAt()
 * @method $this setAddedAt(string $value)
 */
class Mage_Reports_Model_Product_Index_Viewed extends Mage_Reports_Model_Product_Index_Abstract
{
    /**
     * Cache key name for Count of product index
     *
     * @var string
     */
    protected $_countCacheKey   = 'product_index_viewed_count';

    /**
     * Initialize resource model
     *
     */
    #[\Override]
    protected function _construct()
    {
        $this->_init('reports/product_index_viewed');
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

        if (Mage::registry('current_product')) {
            $productIds[] = Mage::registry('current_product')->getId();
        }

        return $productIds;
    }
}
