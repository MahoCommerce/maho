<?php

/**
 * Maho
 *
 * @package    Mage_Catalog
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * @method Mage_Catalog_Model_Resource_Product_Website _getResource()
 * @method Mage_Catalog_Model_Resource_Product_Website getResource()
 * @method int getWebsiteId()
 * @method $this setWebsiteId(int $value)
 */
class Mage_Catalog_Model_Product_Website extends Mage_Core_Model_Abstract
{
    /**
     * Initialize resource model
     */
    #[\Override]
    protected function _construct()
    {
        $this->_init('catalog/product_website');
    }

    /**
     * Removes products from websites
     *
     * @param array $websiteIds
     * @param array $productIds
     * @return $this
     */
    public function removeProducts($websiteIds, $productIds)
    {
        try {
            $this->_getResource()->removeProducts($websiteIds, $productIds);
        } catch (Exception $e) {
            Mage::throwException(
                Mage::helper('catalog')->__('An error occurred while removing products from websites.'),
            );
        }
        return $this;
    }

    /**
     * Add products to websites
     *
     * @param array $websiteIds
     * @param array $productIds
     * @return $this
     */
    public function addProducts($websiteIds, $productIds)
    {
        try {
            $this->_getResource()->addProducts($websiteIds, $productIds);
        } catch (Exception $e) {
            Mage::throwException(
                Mage::helper('catalog')->__('An error occurred while adding products to websites.'),
            );
        }
        return $this;
    }

    /**
     * Retrieve product websites
     * Return array with key as product ID and value array of websites
     *
     * @param int|array $productIds
     * @return array
     */
    public function getWebsites($productIds)
    {
        return $this->_getResource()->getWebsites($productIds);
    }
}
