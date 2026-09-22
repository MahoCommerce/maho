<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_CatalogIndex
 */

/**
 * Index data retriever factory
 *
 * @package    Mage_CatalogIndex
 *
 * @method Mage_CatalogIndex_Model_Resource_Retreiver _getResource()
 * @method Mage_CatalogIndex_Model_Resource_Retreiver getResource()
 */
class Mage_CatalogIndex_Model_Retreiver extends Mage_Core_Model_Abstract
{
    public const CHILDREN_FOR_TIERS = 1;
    public const CHILDREN_FOR_PRICES = 2;
    public const CHILDREN_FOR_ATTRIBUTES = 3;

    protected $_attributeIdCache = [];

    /**
     * Customer group cache
     *
     * @var Mage_Customer_Model_Resource_Group_Collection|null
     */
    protected $_customerGroups;

    /**
     * Retriever model names cache
     *
     * @var array
     */
    protected $_retreivers = [];

    /**
     * Retriever factory init, load retriever settings
     */
    #[\Override]
    protected function _construct()
    {
        $config = Mage::getConfig()->getNode('global/catalog/product/type')->asArray();
        foreach ($config as $type => $data) {
            if (isset($data['index_data_retreiver'])) {
                $this->_retreivers[$type] = $data['index_data_retreiver'];
            }
        }

        $this->_init('catalogindex/retreiver');
    }

    /**
     * Returns data retriever model by specified product type
     *
     * @param string $type
     * @return Mage_CatalogIndex_Model_Data_Abstract|false
     * @throws Mage_Core_Exception
     */
    public function getRetreiver($type)
    {
        if (isset($this->_retreivers[$type])) {
            return Mage::getSingleton($this->_retreivers[$type]);
        }
        Mage::throwException("Data retreiver for '{$type}' is not defined");
    }

    /**
     * Return customer group collection
     *
     * @return Mage_Customer_Model_Resource_Group_Collection
     */
    public function getCustomerGroups()
    {
        $this->_customerGroups ??= Mage::getModel('customer/group')->getCollection();
        return $this->_customerGroups;
    }

    /**
     * Return product ids sorted by type
     *
     * @param array $products
     * @return array
     */
    public function assignProductTypes($products)
    {
        $flat = $this->_getResource()->getProductTypes($products);
        $result = [];
        foreach ($flat as $one) {
            $result[$one['type']][] = $one['id'];
        }
        return $result;
    }

    public function getAttributeSetId(): ?int
    {
        $value = $this->getData('attribute_set_id');
        return $value === null ? null : (int) $value;
    }

    public function setAttributeSetId(?int $value): static
    {
        return $this->setData('attribute_set_id', $value);
    }

    public function getEntityTypeId(): ?int
    {
        $value = $this->getData('entity_type_id');
        return $value === null ? null : (int) $value;
    }

    public function setEntityTypeId(?int $value): static
    {
        return $this->setData('entity_type_id', $value);
    }

    public function getHasOptions(): ?bool
    {
        $value = $this->getData('has_options');
        return $value === null ? null : (bool) $value;
    }

    public function setHasOptions(?bool $value): static
    {
        return $this->setData('has_options', $value);
    }

    public function getRequiredOptions(): ?bool
    {
        $value = $this->getData('required_options');
        return $value === null ? null : (bool) $value;
    }

    public function setRequiredOptions(?bool $value): static
    {
        return $this->setData('required_options', $value);
    }

    public function getSku(): ?string
    {
        $value = $this->getData('sku');
        return $value === null ? null : (string) $value;
    }

    public function setSku(?string $value): static
    {
        return $this->setData('sku', $value);
    }

    public function getTypeId(): ?string
    {
        $value = $this->getData('type_id');
        return $value === null ? null : (string) $value;
    }

    public function setTypeId(?string $value): static
    {
        return $this->setData('type_id', $value);
    }

    public function getUpdatedAt(): ?string
    {
        $value = $this->getData('updated_at');
        return $value === null ? null : (string) $value;
    }

}
