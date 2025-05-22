<?php

/**
 * Maho
 *
 * @package    Mage_Catalog
 * @copyright  Copyright (c) 2024-2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Dynamic Category Processor
 *
 * @package    Mage_Catalog
 */
class Mage_Catalog_Model_Category_Dynamic_Processor
{
    /**
     * Process all dynamic categories
     *
     * @return $this
     */
    public function processAllDynamicCategories()
    {
        /** @var Mage_Catalog_Model_Resource_Category_Collection $categoryCollection */
        $categoryCollection = Mage::getModel('catalog/category')->getCollection()
            ->addAttributeToSelect(['entity_id', 'name', 'is_dynamic'])
            ->addAttributeToFilter('is_dynamic', 1);

        foreach ($categoryCollection as $category) {
            $this->processDynamicCategory($category);
        }

        return $this;
    }

    /**
     * Process single dynamic category
     *
     * @param Mage_Catalog_Model_Category $category
     * @return $this
     */
    public function processDynamicCategory($category)
    {
        if (!$category->getIsDynamic()) {
            return $this;
        }

        try {
            $rules = $this->_getRulesForCategory($category->getId());
            $productIds = $this->_getMatchingProductIds($rules, $category);
            
            // Update category products
            $this->_updateCategoryProducts($category, $productIds);
            
            // Update last update timestamp
            $category->setDynamicLastUpdate(now());
            $category->getResource()->saveAttribute($category, 'dynamic_last_update');
            
            Mage::logException(new Exception(sprintf(
                'Processed dynamic category %d (%s) with %d products',
                $category->getId(),
                $category->getName(),
                count($productIds)
            )));
            
        } catch (Exception $e) {
            Mage::logException($e);
        }

        return $this;
    }

    /**
     * Get rules for category
     *
     * @param int $categoryId
     * @return array
     */
    protected function _getRulesForCategory($categoryId)
    {
        /** @var Mage_Catalog_Model_Resource_Category_Dynamic_Rule_Collection $collection */
        $collection = Mage::getModel('catalog/category_dynamic_rule')->getCollection()
            ->addCategoryFilter($categoryId)
            ->addActiveFilter(true);

        return $collection->getItems();
    }

    /**
     * Get matching product IDs based on rules
     *
     * @param array $rules
     * @param Mage_Catalog_Model_Category $category
     * @return array
     */
    protected function _getMatchingProductIds($rules, $category)
    {
        if (empty($rules)) {
            return [];
        }

        $allProductIds = [];

        foreach ($rules as $rule) {
            $ruleProductIds = $rule->getMatchingProductIds();
            
            // Intersection: only products that match all rules
            if (empty($allProductIds)) {
                $allProductIds = $ruleProductIds;
            } else {
                $allProductIds = array_intersect($allProductIds, $ruleProductIds);
            }
        }

        return array_unique($allProductIds);
    }

    /**
     * Update category products
     *
     * @param Mage_Catalog_Model_Category $category
     * @param array $productIds
     * @return $this
     */
    protected function _updateCategoryProducts($category, $productIds)
    {
        // Get current category products
        $currentProductIds = $category->getResource()->getProductsPosition($category);
        $currentProductIds = is_array($currentProductIds) ? array_keys($currentProductIds) : [];

        // Calculate changes
        $toAdd = array_diff($productIds, $currentProductIds);
        $toRemove = array_diff($currentProductIds, $productIds);

        if (!empty($toAdd) || !empty($toRemove)) {
            // Prepare new product positions (all with position 0 for dynamic assignment)
            $newPositions = [];
            foreach ($productIds as $productId) {
                $newPositions[$productId] = 0;
            }

            // Save new product associations
            $category->setPostedProducts($newPositions);
            $category->save();

            // Clear category cache
            Mage::app()->cleanCache([Mage_Catalog_Model_Category::CACHE_TAG . '_' . $category->getId()]);
        }

        return $this;
    }

    /**
     * Process dynamic categories for specific product
     *
     * @param Mage_Catalog_Model_Product $product
     * @return $this
     */
    public function processProductUpdate($product)
    {
        /** @var Mage_Catalog_Model_Resource_Category_Collection $categoryCollection */
        $categoryCollection = Mage::getModel('catalog/category')->getCollection()
            ->addAttributeToSelect(['entity_id', 'name', 'is_dynamic'])
            ->addAttributeToFilter('is_dynamic', 1);

        foreach ($categoryCollection as $category) {
            $rules = $this->_getRulesForCategory($category->getId());
            
            foreach ($rules as $rule) {
                // Set products filter to only this product for efficiency
                $rule->setProductsFilter([$product->getId()]);
                
                $matchingIds = $rule->getMatchingProductIds();
                $isMatching = in_array($product->getId(), $matchingIds);
                
                // Get current category products
                $currentProductIds = $category->getResource()->getProductsPosition($category);
                $currentProductIds = is_array($currentProductIds) ? array_keys($currentProductIds) : [];
                $isCurrentlyInCategory = in_array($product->getId(), $currentProductIds);
                
                // Update if status changed
                if ($isMatching && !$isCurrentlyInCategory) {
                    // Add product to category
                    $positions = $category->getResource()->getProductsPosition($category) ?: [];
                    $positions[$product->getId()] = 0;
                    $category->setPostedProducts($positions);
                    $category->save();
                } elseif (!$isMatching && $isCurrentlyInCategory) {
                    // Remove product from category (only if it was dynamically added)
                    $positions = $category->getResource()->getProductsPosition($category) ?: [];
                    unset($positions[$product->getId()]);
                    $category->setPostedProducts($positions);
                    $category->save();
                }
            }
        }

        return $this;
    }
}