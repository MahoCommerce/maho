<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_CatalogIndex
 */

/**
 * Catalog indexer eav processor
 *
 * @package    Mage_CatalogIndex
 *
 * @method Mage_CatalogIndex_Model_Resource_Indexer_Eav _getResource()
 * @method Mage_CatalogIndex_Model_Resource_Indexer_Eav getResource()
 */
class Mage_CatalogIndex_Model_Indexer_Eav extends Mage_CatalogIndex_Model_Indexer_Abstract
{
    #[\Override]
    protected function _construct()
    {
        $this->_init('catalogindex/indexer_eav');
        parent::_construct();
    }

    /**
     * @return array
     */
    #[\Override]
    public function createIndexData(Mage_Catalog_Model_Product $object, ?Mage_Eav_Model_Entity_Attribute_Abstract $attribute = null)
    {
        $data = [];

        $data['store_id'] = $attribute->getStoreId();
        $data['entity_id'] = $object->getId();
        $data['attribute_id'] = $attribute->getId();
        $data['value'] = $object->getData($attribute->getAttributeCode());

        if ($attribute->getFrontendInput() == 'multiselect') {
            $origData = $data;
            $data = [];

            $value = explode(',', $origData['value']);
            foreach ($value as $item) {
                $row = $origData;
                $row['value'] = $item;
                $data[] = $row;
            }
        }

        //return $this->_spreadDataForStores($object, $attribute, $data);
        return $data;
    }

    /**
     * @return bool
     */
    #[\Override]
    protected function _isAttributeIndexable(Mage_Eav_Model_Entity_Attribute_Abstract $attribute)
    {
        if ($attribute->getIsFilterable() == 0 && $attribute->getIsVisibleInAdvancedSearch() == 0) {
            return false;
        }
        if ($attribute->getFrontendInput() != 'select' && $attribute->getFrontendInput() != 'multiselect') {
            return false;
        }

        return true;
    }

    /**
     * @return string
     */
    #[\Override]
    protected function _getIndexableAttributeConditions()
    {
        return "main_table.frontend_input IN ('select', 'multiselect') AND (additional_table.is_filterable IN (1, 2) OR additional_table.is_visible_in_advanced_search = 1)";
    }

    public function getAttributeId(): ?int
    {
        $value = $this->getData('attribute_id');
        return $value === null ? null : (int) $value;
    }

    public function setAttributeId(?int $value): static
    {
        return $this->setData('attribute_id', $value);
    }

    public function setEntityId(?int $value): static
    {
        return $this->setData('entity_id', $value);
    }

    public function getStoreId(): ?int
    {
        $value = $this->getData('store_id');
        return $value === null ? null : (int) $value;
    }

    public function setStoreId(?int $value): static
    {
        return $this->setData('store_id', $value);
    }

    public function getValue(): ?int
    {
        $value = $this->getData('value');
        return $value === null ? null : (int) $value;
    }

    public function setValue(?int $value): static
    {
        return $this->setData('value', $value);
    }

}
