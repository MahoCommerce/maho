<?php

/**
 * Maho
 *
 * @package    Mage_Catalog
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Catalog_Model_Category_Dynamic_Processor
{
    public function processAllDynamicCategories(): self
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

    public function processDynamicCategory(Mage_Catalog_Model_Category $category): self
    {
        if (!$category->getIsDynamic()) {
            return $this;
        }

        try {
            $rules = $this->getRulesForCategory($category->getId());
            $productIds = $this->getMatchingProductIds($rules, $category);
            $this->updateCategoryProducts($category, $productIds);
        } catch (Exception $e) {
            Mage::logException($e);
        }

        return $this;
    }

    protected function getRulesForCategory(int $categoryId): array
    {
        /** @var Mage_Catalog_Model_Resource_Category_Dynamic_Rule_Collection $collection */
        $collection = Mage::getResourceModel('catalog/category_dynamic_rule_collection')
            ->addCategoryFilter($categoryId)
            ->addActiveFilter(true);

        return $collection->getItems();
    }

    /**
     * Get matching product IDs based on rules
     */
    protected function getMatchingProductIds(array $rules, Mage_Catalog_Model_Category $category): array
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

    protected function updateCategoryProducts(Mage_Catalog_Model_Category $category, array $productIds): self
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
     */
    public function processProductUpdate(Mage_Catalog_Model_Product $product): self
    {
        /** @var Mage_Catalog_Model_Resource_Category_Collection $categoryCollection */
        $categoryCollection = Mage::getModel('catalog/category')->getCollection()
            ->addAttributeToSelect(['entity_id', 'name', 'is_dynamic'])
            ->addAttributeToFilter('is_dynamic', 1);

        foreach ($categoryCollection as $category) {
            $rules = $this->getRulesForCategory($category->getId());

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
