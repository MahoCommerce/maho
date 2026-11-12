<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

/**
 * Catalog Product Mass Action processing model
 *
 * @package    Mage_Catalog
 *
 * @method Mage_Catalog_Model_Resource_Product_Action _getResource()
 * @method Mage_Catalog_Model_Resource_Product_Action getResource()
 */
class Mage_Catalog_Model_Product_Action extends Mage_Core_Model_Abstract
{
    #[\Override]
    protected function _construct()
    {
        $this->_init('catalog/product_action');
    }

    /**
     * Update attribute values for entity list per store
     *
     * @param array $productIds
     * @param array $attrData
     * @param int $storeId
     * @return $this
     */
    public function updateAttributes($productIds, $attrData, $storeId)
    {
        Mage::dispatchEvent('catalog_product_attribute_update_before', [
            'attributes_data' => &$attrData,
            'product_ids'   => &$productIds,
            'store_id'      => &$storeId,
        ]);

        $this->_getResource()->updateAttributes($productIds, $attrData, $storeId);
        $this->setData([
            'product_ids'       => array_unique($productIds),
            'attributes_data'   => $attrData,
            'store_id'          => $storeId,
        ]);

        // register mass action indexer event
        Mage::getSingleton('index/indexer')->processEntityAction(
            $this,
            Mage_Catalog_Model_Product::ENTITY,
            Mage_Index_Model_Event::TYPE_MASS_ACTION,
        );

        Mage::dispatchEvent('catalog_product_attribute_update_after', [
            'product_ids'   => $productIds,
        ]);

        $this->_cleanProductCache($productIds);

        return $this;
    }

    /**
     * Move the products $productIds to the attribute set $setId, without a save of each product. The indexes and
     * the product cache follow, as for updateAttributes().
     *
     * @param list<int> $productIds
     * @return int the number of products
     */
    public function updateAttributeSet(array $productIds, int $setId): int
    {
        $resource = Mage::getSingleton('core/resource');
        $write = $resource->getConnection('core_write');
        $count = 0;
        foreach (array_chunk($productIds, 1000) as $chunk) {
            $count += (int) $write->update(
                $resource->getTableName('catalog/product'),
                ['attribute_set_id' => $setId, 'updated_at' => Mage::app()->getLocale()->formatDateForDb('now')],
                ['entity_id IN (?)' => $chunk],
            );
        }

        $this->setData([
            'product_ids' => $productIds,
            'attributes_data' => ['attribute_set_id' => $setId],
            'store_id' => Mage_Core_Model_App::ADMIN_STORE_ID,
        ]);
        Mage::getSingleton('index/indexer')->processEntityAction(
            $this,
            Mage_Catalog_Model_Product::ENTITY,
            Mage_Index_Model_Event::TYPE_MASS_ACTION,
        );
        Mage::dispatchEvent('catalog_product_attribute_update_after', [
            'product_ids' => $productIds,
        ]);
        $this->_cleanProductCache($productIds);

        return $count;
    }

    /**
     * A mass update bypasses the product model, so its cache tags are cleaned here.
     *
     * @param array<int, int|string> $productIds
     */
    protected function _cleanProductCache(array $productIds): void
    {
        $tags = array_map(
            static fn(int|string $id): string => Mage_Catalog_Model_Product::CACHE_TAG . '_' . $id,
            array_unique($productIds),
        );
        if ($tags !== []) {
            Mage::app()->cleanCache($tags);
        }
    }

    /**
     * Put the product $productId in the categories $categoryIds only, without a save of the product. The category
     * product index and the URL rewrites of the product follow.
     *
     * @param list<int> $categoryIds
     */
    public function updateCategories(int $productId, array $categoryIds): static
    {
        $resource = Mage::getSingleton('core/resource');
        $write = $resource->getConnection('core_write');
        $table = $resource->getTableName('catalog/category_product');

        $existing = array_map(intval(...), $write->fetchCol("SELECT category_id FROM {$table} WHERE product_id = ?", [$productId]));
        $added = array_values(array_diff($categoryIds, $existing));
        $removed = array_values(array_diff($existing, $categoryIds));
        foreach ($added as $categoryId) {
            $write->insertOnDuplicate($table, ['category_id' => $categoryId, 'product_id' => $productId, 'position' => 0]);
        }
        if ($removed !== []) {
            $write->delete($table, ['product_id = ?' => $productId, 'category_id IN (?)' => $removed]);
        }
        if ($added === [] && $removed === []) {
            return $this;
        }

        $this->setData([
            'product_ids' => [$productId],
            'category_ids' => $categoryIds,
        ]);
        Mage::getSingleton('index/indexer')->processEntityAction(
            $this,
            Mage_Catalog_Model_Product::ENTITY,
            Mage_Index_Model_Event::TYPE_MASS_ACTION,
        );
        Mage::dispatchEvent('catalog_product_category_update', [
            'product_ids' => [$productId],
            'category_ids' => $categoryIds,
        ]);
        $this->_cleanProductCache([$productId]);

        return $this;
    }

    /**
     * Delete the values of the store view $storeId of each attribute in $attributes for the product $productId,
     * without a save of the product. The indexes and the URL rewrites of the product follow, as for updateAttributes().
     *
     * @param list<Mage_Catalog_Model_Resource_Eav_Attribute> $attributes
     */
    public function deleteStoreValues(int $productId, array $attributes, int $storeId): static
    {
        if ($attributes === []) {
            return $this;
        }
        $write = Mage::getSingleton('core/resource')->getConnection('core_write');
        $attrData = [];
        foreach ($attributes as $attribute) {
            $write->delete($attribute->getBackend()->getTable(), [
                'entity_id = ?' => $productId,
                'attribute_id = ?' => (int) $attribute->getId(),
                'store_id = ?' => $storeId,
            ]);
            $attrData[$attribute->getAttributeCode()] = null;
        }

        $this->setData([
            'product_ids' => [$productId],
            'attributes_data' => $attrData,
            'store_id' => $storeId,
        ]);
        Mage::getSingleton('index/indexer')->processEntityAction(
            $this,
            Mage_Catalog_Model_Product::ENTITY,
            Mage_Index_Model_Event::TYPE_MASS_ACTION,
        );
        Mage::dispatchEvent('catalog_product_attribute_update_after', [
            'product_ids' => [$productId],
        ]);
        $this->_cleanProductCache([$productId]);

        return $this;
    }

    /**
     * Update websites for product action
     *
     * allowed types:
     * - add
     * - remove
     *
     * @param array $productIds
     * @param array $websiteIds
     * @param string $type
     */
    public function updateWebsites($productIds, $websiteIds, $type)
    {
        Mage::dispatchEvent('catalog_product_website_update_before', [
            'website_ids'   => $websiteIds,
            'product_ids'   => $productIds,
            'action'        => $type,
        ]);

        if ($type === 'add') {
            Mage::getModel('catalog/product_website')->addProducts($websiteIds, $productIds);
        } elseif ($type === 'remove') {
            Mage::getModel('catalog/product_website')->removeProducts($websiteIds, $productIds);
        }

        $this->setData([
            'product_ids' => array_unique($productIds),
            'website_ids' => $websiteIds,
            'action_type' => $type,
        ]);

        // register mass action indexer event
        Mage::getSingleton('index/indexer')->processEntityAction(
            $this,
            Mage_Catalog_Model_Product::ENTITY,
            Mage_Index_Model_Event::TYPE_MASS_ACTION,
        );

        // add back compatibility system event
        Mage::dispatchEvent('catalog_product_website_update', [
            'website_ids'   => $websiteIds,
            'product_ids'   => $productIds,
            'action'        => $type,
        ]);
    }
}
