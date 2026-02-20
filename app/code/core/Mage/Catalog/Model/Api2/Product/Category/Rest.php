<?php

/**
 * Maho
 *
 * @package    Mage_Catalog
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

abstract class Mage_Catalog_Model_Api2_Product_Category_Rest extends Mage_Catalog_Model_Api2_Product_Rest
{
    /**
     * Product category assign is not available
     */
    #[\Override]
    protected function _create(array $data)
    {
        $this->_critical(self::RESOURCE_METHOD_NOT_ALLOWED);
    }

    /**
     * Product category update is not available
     */
    #[\Override]
    protected function _update(array $data)
    {
        $this->_critical(self::RESOURCE_METHOD_NOT_ALLOWED);
    }

    /**
     * Retrieve product data
     *
     * @return array
     */
    #[\Override]
    protected function _retrieveCollection()
    {
        $return = [];

        foreach ($this->_getCategoryIds() as $categoryId) {
            $return[] = ['category_id' => $categoryId];
        }
        return $return;
    }

    /**
     * Only admin have permissions for product category unassign
     */
    #[\Override]
    protected function _delete()
    {
        $this->_critical(self::RESOURCE_METHOD_NOT_ALLOWED);
    }

    /**
     * Load category by id
     *
     * @param int $categoryId
     * @return Mage_Catalog_Model_Category
     */
    #[\Override]
    protected function _getCategoryById($categoryId)
    {
        /** @var Mage_Catalog_Model_Category $category */
        $category = Mage::getModel('catalog/category')->setStoreId(0)->load($categoryId);
        if (!$category->getId()) {
            $this->_critical('Category not found', Mage_Api2_Model_Server::HTTP_NOT_FOUND);
        }

        return $category;
    }

    /**
     * Get assigned categories ids
     *
     * @return array
     */
    protected function _getCategoryIds()
    {
        return $this->_getProduct()->getCategoryCollection()->addIsActiveFilter()->getAllIds();
    }
}
