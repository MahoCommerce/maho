<?php

/**
 * Maho
 *
 * @package    Mage_CatalogSearch
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

class Mage_CatalogSearch_Block_Autocomplete_Category_List extends Mage_Core_Block_Template
{
    protected ?Mage_Catalog_Model_Resource_Category_Collection $_categoryCollection = null;

    public function getCategoryCollection(): Mage_Catalog_Model_Resource_Category_Collection
    {
        if ($this->_categoryCollection === null) {
            /** @var Mage_CatalogSearch_Helper_Data $helper */
            $helper = Mage::helper('catalogsearch');
            $query = $helper->getQueryText();

            /** @var Mage_Catalog_Model_Resource_Category_Collection $collection */
            $collection = Mage::getModel('catalog/category')->getCollection();

            // Add category name attribute to select
            $collection->addAttributeToSelect('name')
                ->addAttributeToSelect('url_path')
                ->addAttributeToSelect('url_key');

            // Apply search filter based on configured search type
            $searchType = (int) Mage::getStoreConfig(Mage_CatalogSearch_Model_Fulltext::XML_PATH_CATALOG_SEARCH_TYPE);
            $searchSeparator = strtoupper(Mage::getStoreConfig(Mage_CatalogSearch_Model_Fulltext::XML_PATH_CATALOG_SEARCH_SEPARATOR));

            // Get max query words from configuration
            $maxQueryWords = (int) Mage::getStoreConfig(Mage_CatalogSearch_Model_Query::XML_PATH_MAX_QUERY_WORDS);

            switch ($searchType) {
                case Mage_CatalogSearch_Model_Fulltext::SEARCH_TYPE_FULLTEXT:
                    // Fulltext search: split into words and use AND/OR based on separator config
                    $words = Mage::helper('core/string')->splitWords($query, true, $maxQueryWords);
                    if ($words) {
                        if ($searchSeparator === 'AND') {
                            // AND logic: all words must be present
                            foreach ($words as $word) {
                                $collection->addAttributeToFilter('name', ['like' => "%{$word}%"]);
                            }
                        } else {
                            // OR logic: any word can match
                            $conditions = [];
                            foreach ($words as $word) {
                                $conditions[] = ['like' => "%{$word}%"];
                            }
                            $collection->addAttributeToFilter('name', $conditions);
                        }
                    }
                    break;

                case Mage_CatalogSearch_Model_Fulltext::SEARCH_TYPE_COMBINE:
                    // Combined search: match either the full phrase OR individual words (based on separator)
                    $words = Mage::helper('core/string')->splitWords($query, true, $maxQueryWords);
                    if ($words && count($words) > 1) {
                        if ($searchSeparator === 'AND') {
                            // AND logic for individual words, but also try full phrase
                            // This is complex with EAV, so we'll prioritize full phrase
                            $collection->addAttributeToFilter('name', ['like' => "%{$query}%"]);
                        } else {
                            // OR logic: match full phrase OR any individual word
                            $conditions = [
                                ['like' => "%{$query}%"], // Full phrase match
                            ];
                            foreach ($words as $word) {
                                if ($word !== $query) {
                                    $conditions[] = ['like' => "%{$word}%"];
                                }
                            }
                            $collection->addAttributeToFilter('name', $conditions);
                        }
                    } else {
                        // Single word search
                        $collection->addAttributeToFilter('name', ['like' => "%{$query}%"]);
                    }
                    break;

                case Mage_CatalogSearch_Model_Fulltext::SEARCH_TYPE_LIKE:
                default:
                    // LIKE search: split into words and use AND/OR based on separator config
                    $words = Mage::helper('core/string')->splitWords($query, true, $maxQueryWords);
                    if ($words && count($words) > 1) {
                        if ($searchSeparator === 'AND') {
                            // AND logic: all words must be present
                            foreach ($words as $word) {
                                $collection->addAttributeToFilter('name', ['like' => "%{$word}%"]);
                            }
                        } else {
                            // OR logic: any word can match
                            $conditions = [];
                            foreach ($words as $word) {
                                $conditions[] = ['like' => "%{$word}%"];
                            }
                            $collection->addAttributeToFilter('name', $conditions);
                        }
                    } else {
                        // Single word search
                        $collection->addAttributeToFilter('name', ['like' => "%{$query}%"]);
                    }
                    break;
            }

            // Only show active categories
            $collection->addAttributeToFilter('is_active', 1);

            // Exclude root categories (level 0 and 1)
            $collection->addAttributeToFilter('level', ['gt' => 1]);

            // Only show categories that are included in menu (optional, but often desired)
            $collection->addAttributeToFilter('include_in_menu', 1);

            // Apply store filter
            $collection->setStoreId(Mage::app()->getStore()->getId());

            // Limit results based on configuration
            $limit = (int) Mage::getStoreConfig('catalog/search/category_autosuggest_limit');
            if ($limit <= 0) {
                $limit = 5; // Default fallback
            }
            $collection->setPageSize($limit);

            // Order by level (show higher-level categories first) and then by name
            $collection->setOrder('level', 'ASC')
                ->setOrder('name', 'ASC');

            $this->_categoryCollection = $collection;
        }

        return $this->_categoryCollection;
    }

    /**
     * Check if category autosuggest is enabled
     */
    public function isEnabled(): bool
    {
        return (bool) Mage::getStoreConfig('catalog/search/enable_category_autosuggest');
    }

    public function getCategoryUrl(Mage_Catalog_Model_Category $category): string
    {
        return $category->getUrl();
    }

    /**
     * Get category path as array (excluding root categories and current category)
     */
    public function getCategoryPath(Mage_Catalog_Model_Category $category): array
    {
        $path = [];
        $pathIds = explode('/', $category->getPath());

        // Remove root category IDs (typically 1 and 2)
        $pathIds = array_slice($pathIds, 2);

        foreach ($pathIds as $categoryId) {
            if ($categoryId == $category->getId()) {
                // Skip the current category
                continue;
            }
            $parentCategory = Mage::getModel('catalog/category')->load($categoryId);
            if ($parentCategory->getId() && $parentCategory->getIsActive()) {
                $path[] = $parentCategory->getName();
            }
        }

        return $path;
    }
}
