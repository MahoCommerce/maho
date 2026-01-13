<?php

/**
 * Maho
 *
 * @package    Mage_Sitemap
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Sitemap_Model_Resource_Catalog_Product extends Mage_Sitemap_Model_Resource_Catalog_Abstract
{
    #[\Override]
    protected function _construct()
    {
        $this->_init('catalog/product', 'entity_id');
    }

    /**
     * Get product collection array
     *
     * @param int $storeId
     * @return array|false
     */
    #[\Override]
    public function getCollection($storeId)
    {
        $store = Mage::app()->getStore($storeId);
        if (!$store) {
            return false;
        }

        $this->_select = $this->_getWriteAdapter()->select()
            ->from(['main_table' => $this->getMainTable()], [$this->getIdFieldName()])
            ->join(
                ['w' => $this->getTable('catalog/product_website')],
                'main_table.entity_id = w.product_id',
                [],
            )
            ->where('w.website_id=?', $store->getWebsiteId());

        $storeId = (int) $store->getId();

        $urlRewrite = $this->_factory->getProductUrlRewriteHelper();
        $urlRewrite->joinTableToSelect($this->_select, $storeId);

        $this->_addFilter(
            $storeId,
            'visibility',
            Mage::getSingleton('catalog/product_visibility')->getVisibleInSiteIds(),
            'in',
        );
        $this->_addFilter(
            $storeId,
            'status',
            Mage::getSingleton('catalog/product_status')->getVisibleStatusIds(),
            'in',
        );

        return $this->_loadEntities();
    }

    /**
     * Load product attributes for specific entity IDs
     */
    public function loadAttributesForIds(array $entityIds, int $storeId): array
    {
        if (empty($entityIds)) {
            return [];
        }

        $nameAttr = Mage::getSingleton('eav/config')->getAttribute('catalog_product', 'name');
        $imageAttr = Mage::getSingleton('eav/config')->getAttribute('catalog_product', 'image');
        $attributeMap = [
            $nameAttr->getId() => 'name',
            $imageAttr->getId() => 'image',
        ];

        $read = $this->_getReadAdapter();
        // Use CASE instead of MySQL's FIELD() for cross-database compatibility (SQLite)
        // Store-specific values ($storeId) should come first, then default (0)
        $storeOrder = new \Maho\Db\Expr(
            'CASE WHEN store_id = ' . (int) $storeId . ' THEN 0 WHEN store_id = 0 THEN 1 ELSE 2 END',
        );
        $select = $read->select()
            ->from(['attr' => 'catalog_product_entity_varchar'], ['entity_id', 'attribute_id', 'value', 'store_id'])
            ->where('attr.entity_id IN (?)', $entityIds)
            ->where('attr.attribute_id IN (?)', array_keys($attributeMap))
            ->where('attr.store_id IN (?)', [0, $storeId])
            ->order(['entity_id ASC', 'attribute_id ASC', $storeOrder]);

        $results = $read->fetchAll($select);
        $attributes = [];

        // Process results with store fallback logic
        foreach ($results as $row) {
            $entityId = $row['entity_id'];
            $attributeCode = $attributeMap[$row['attribute_id']];

            // Only set if not already set (store-specific values come first due to ORDER BY)
            if (!isset($attributes[$entityId][$attributeCode])) {
                if (!isset($attributes[$entityId])) {
                    $attributes[$entityId] = [];
                }
                $attributes[$entityId][$attributeCode] = $row['value'];
            }
        }

        return $attributes;
    }

    /**
     * Retrieve entity url
     *
     * @param array $row
     * @param \Maho\DataObject $entity
     * @return string
     */
    #[\Override]
    protected function _getEntityUrl($row, $entity)
    {
        return empty($row['request_path']) ? 'catalog/product/view/id/' . $entity->getId() : $row['request_path'];
    }

    /**
     * Loads product attribute by given attribute code
     *
     * @param string $attributeCode
     * @return Mage_Sitemap_Model_Resource_Catalog_Abstract
     */
    #[\Override]
    protected function _loadAttribute($attributeCode)
    {
        $attribute = Mage::getSingleton('catalog/product')->getResource()->getAttribute($attributeCode);

        $this->_attributesCache[$attributeCode] = [
            'entity_type_id' => $attribute->getEntityTypeId(),
            'attribute_id'   => $attribute->getId(),
            'table'          => $attribute->getBackend()->getTable(),
            'is_global'      => $attribute->getIsGlobal() == Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_GLOBAL,
            'backend_type'   => $attribute->getBackendType(),
        ];
        return $this;
    }
}
