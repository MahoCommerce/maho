<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
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
     *
     * Each rule resolves its own matches (see the rule's parent resolution setting) before the rules
     * are intersected, so only products wanted by every rule end up in the category.
     */
    protected function getMatchingProductIds(array $rules, Mage_Catalog_Model_Category $category): array
    {
        $allProductIds = null;

        foreach ($rules as $rule) {
            $ruleProductIds = $rule->getResolvedProductIds();

            // Intersection: only products that match all rules. A rule matching nothing empties the
            // category, so start from null rather than treating [] as "not collected yet".
            $allProductIds = $allProductIds === null
                ? $ruleProductIds
                : array_intersect($allProductIds, $ruleProductIds);
        }

        return array_values(array_unique($allProductIds ?? []));
    }

    protected function updateCategoryProducts(Mage_Catalog_Model_Category $category, array $productIds): self
    {
        // Get current category products, positions included: manual ordering set in Category > Products
        // must survive a reindex, otherwise the default Position sort silently becomes arbitrary
        $currentPositions = $category->getResource()->getProductsPosition($category);
        $currentPositions = is_array($currentPositions) ? $currentPositions : [];
        $currentProductIds = array_keys($currentPositions);

        // Calculate changes
        $toAdd = array_diff($productIds, $currentProductIds);
        $toRemove = array_diff($currentProductIds, $productIds);

        if (!empty($toAdd) || !empty($toRemove)) {
            // Carry the existing positions forward; only products entering the category default to 0
            $newPositions = [];
            foreach ($productIds as $productId) {
                $newPositions[$productId] = $currentPositions[$productId] ?? 0;
            }

            // Save new product associations
            $category->setPostedProducts($newPositions);
            $category->save();

            // Clear category cache
            Mage::app()->cleanCache([Mage_Catalog_Model_Category::CACHE_TAG . '_' . $category->getId()]);
        }

        return $this;
    }
}
