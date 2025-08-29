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

class Mage_Sitemap_Model_Resource_Catalog_Category extends Mage_Sitemap_Model_Resource_Catalog_Abstract
{
    /**
     * Init resource model (catalog/category)
     */
    #[\Override]
    protected function _construct()
    {
        $this->_init('catalog/category', 'entity_id');
    }

    /**
     * Get category collection array
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
            ->from($this->getMainTable())
            ->where($this->getIdFieldName() . '=?', $store->getRootCategoryId());

        $categoryRow = $this->_getWriteAdapter()->fetchRow($this->_select);
        if (!$categoryRow) {
            return false;
        }

        $this->_select = $this->_getWriteAdapter()->select()
            ->from(['main_table' => $this->getMainTable()], [$this->getIdFieldName()])
            ->where('main_table.path LIKE ?', $categoryRow['path'] . '/%');

        $storeId = (int) $store->getId();

        $urlRewrite = $this->_factory->getCategoryUrlRewriteHelper();
        $urlRewrite->joinTableToSelect($this->_select, $storeId);

        // Join name and image EAV attributes for sitemap with store fallback
        $this->_loadAttribute('name');
        $this->_loadAttribute('image');

        $nameAttribute = $this->_attributesCache['name'];
        $imageAttribute = $this->_attributesCache['image'];

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

        // Join image attribute with store fallback
        $this->_select->joinLeft(
            ['image_attr_global' => $imageAttribute['table']],
            'main_table.entity_id=image_attr_global.entity_id AND image_attr_global.store_id=0 AND image_attr_global.attribute_id=' . $imageAttribute['attribute_id'],
            [],
        )->joinLeft(
            ['image_attr_store' => $imageAttribute['table']],
            'main_table.entity_id=image_attr_store.entity_id AND image_attr_store.store_id=' . $storeId . ' AND image_attr_store.attribute_id=' . $imageAttribute['attribute_id'],
            ['image' => 'COALESCE(image_attr_store.value, image_attr_global.value)'],
        );

        $this->_addFilter($storeId, 'is_active', 1);

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
        return !empty($row['request_path']) ? $row['request_path'] : 'catalog/category/view/id/' . $entity->getId();
    }

    /**
     * Loads category attribute by given attribute code.
     *
     * @param string $attributeCode
     * @return $this
     */
    #[\Override]
    protected function _loadAttribute($attributeCode)
    {
        $attribute = Mage::getSingleton('catalog/category')->getResource()->getAttribute($attributeCode);

        $this->_attributesCache[$attributeCode] = [
            'entity_type_id' => $attribute->getEntityTypeId(),
            'attribute_id'   => $attribute->getId(),
            'table'          => $attribute->getBackend()->getTable(),
            'is_global'      => $attribute->getIsGlobal(),
            'backend_type'   => $attribute->getBackendType(),
        ];
        return $this;
    }
}
