<?php

/**
 * Maho
 *
 * @package    Mage_Sitemap
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2025 Maho (https://mahocommerce.com)
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

        // Join name attribute and base image from media gallery for sitemap with store fallback
        $this->_loadAttribute('name');

        $nameAttribute = $this->_attributesCache['name'];

        // Join name attribute with store fallback
        $this->_select->joinLeft(
            ['name_attr_global' => $nameAttribute['table']],
            'main_table.entity_id=name_attr_global.entity_id AND name_attr_global.store_id=0 AND name_attr_global.attribute_id=' . $nameAttribute['attribute_id'],
            [],
        )->joinLeft(
            ['name_attr_store' => $nameAttribute['table']],
            'main_table.entity_id=name_attr_store.entity_id AND name_attr_store.store_id=' . $storeId . ' AND name_attr_store.attribute_id=' . $nameAttribute['attribute_id'],
            ['name' => 'COALESCE(name_attr_store.value, name_attr_global.value)'],
        );

        // Fallback to simple image attribute approach due to media gallery complexity
        $this->_loadAttribute('image');
        $imageAttribute = $this->_attributesCache['image'];

        // Join base image attribute with store fallback
        $this->_select->joinLeft(
            ['image_attr_global' => $imageAttribute['table']],
            'main_table.entity_id=image_attr_global.entity_id AND image_attr_global.store_id=0 AND image_attr_global.attribute_id=' . $imageAttribute['attribute_id'],
            [],
        )->joinLeft(
            ['image_attr_store' => $imageAttribute['table']],
            'main_table.entity_id=image_attr_store.entity_id AND image_attr_store.store_id=' . $storeId . ' AND image_attr_store.attribute_id=' . $imageAttribute['attribute_id'],
            ['image' => 'COALESCE(image_attr_store.value, image_attr_global.value)'],
        );

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
     * Retrieve entity url
     *
     * @param array $row
     * @param Varien_Object $entity
     * @return string
     */
    #[\Override]
    protected function _getEntityUrl($row, $entity)
    {
        return !empty($row['request_path']) ? $row['request_path'] : 'catalog/product/view/id/' . $entity->getId();
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
