<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2023 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Eav
 */

abstract class Mage_Eav_Model_Convert_Parser_Abstract extends Mage_Dataflow_Model_Convert_Parser_Abstract
{
    /** @var array<int, Mage_Core_Model_Store>|null */
    protected ?array $_storesById = null;
    /** @var array<int, string>|null */
    protected ?array $_attributeSetsById = null;
    /** @var array<string, int>|null */
    protected ?array $_attributeSetsByName = null;

    /**
     * Return the IDs of a comma separated list of store IDs and codes, or an empty list for an unknown code
     *
     * @return list<int>
     */
    public function getStoreIds(?string $stores): array
    {
        if (empty($stores)) {
            $storeIds = [0];
        } else {
            $storeIds = [];
            foreach (explode(',', $stores) as $store) {
                if (is_numeric($store)) {
                    $storeIds[] = (int) $store;
                } else {
                    $storeNode = Mage::getConfig()->getNode('stores/' . $store);
                    if (!$storeNode) {
                        return [];
                    }
                    $storeIds[] = (int) $storeNode->system->store->id;
                }
            }
        }
        return $storeIds;
    }

    /**
     * @param int $storeId
     * @return string
     * @throws Mage_Core_Model_Store_Exception
     */
    public function getStoreCode($storeId)
    {
        return Mage::app()->getStore($storeId ?: 0)->getCode();
    }

    /**
     * @param int $entityTypeId
     * @return $this
     */
    public function loadAttributeSets($entityTypeId)
    {
        $attributeSetCollection = Mage::getResourceModel('eav/entity_attribute_set_collection')
            ->setEntityTypeFilter($entityTypeId)
            ->load();
        $this->_attributeSetsById = [];
        $this->_attributeSetsByName = [];
        /**
         * @var int $id
         * @var Mage_Eav_Model_Entity_Attribute_Set $attributeSet
         */
        foreach ($attributeSetCollection as $id => $attributeSet) {
            $name = $attributeSet->getAttributeSetName();
            $this->_attributeSetsById[$id] = $name;
            $this->_attributeSetsByName[$name] = $id;
        }
        return $this;
    }

    /**
     * @param int $entityTypeId
     * @param int $id
     * @return bool
     */
    public function getAttributeSetName($entityTypeId, $id)
    {
        if (!$this->_attributeSetsById) {
            $this->loadAttributeSets($entityTypeId);
        }
        return $this->_attributeSetsById[$id] ?? false;
    }

    /**
     * @param int $entityTypeId
     * @param string $name
     * @return bool
     */
    public function getAttributeSetId($entityTypeId, $name)
    {
        if (!$this->_attributeSetsByName) {
            $this->loadAttributeSets($entityTypeId);
        }
        return $this->_attributeSetsByName[$name] ?? false;
    }

    /**
     * @param string $value
     * @return string|null
     */
    public function getSourceOptionId(Mage_Eav_Model_Entity_Attribute_Source_Interface $source, $value)
    {
        foreach ($source->getAllOptions() as $option) {
            if (strcasecmp($option['label'], $value) == 0) {
                return $option['value'];
            }
        }
        return null;
    }
}
