<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_CatalogIndex
 */

/**
 * Catalog indexer price processor
 *
 * @package    Mage_CatalogIndex
 *
 * @method Mage_CatalogIndex_Model_Resource_Indexer_Minimalprice _getResource()
 * @method Mage_CatalogIndex_Model_Resource_Indexer_Minimalprice getResource()
 */
class Mage_CatalogIndex_Model_Indexer_Minimalprice extends Mage_CatalogIndex_Model_Indexer_Abstract
{
    /**
     * @var Mage_Directory_Model_Currency
     */
    protected $_currencyModel;

    /**
     * @var Mage_Customer_Model_Resource_Group_Collection
     */
    protected $_customerGroups;

    #[\Override]
    protected $_runOnce = true;
    #[\Override]
    protected $_processChildren = false;

    #[\Override]
    protected function _construct()
    {
        $this->_init('catalogindex/indexer_minimalprice');
        $this->_currencyModel = Mage::getModel('directory/currency');
        $this->_customerGroups = Mage::getModel('customer/group')->getCollection();

        parent::_construct();
    }

    /**
     * @return Mage_Eav_Model_Entity_Attribute_Abstract|mixed
     * @throws Mage_Core_Exception
     */
    public function getTierPriceAttribute()
    {
        $data = $this->getData('tier_price_attribute');
        if (is_null($data)) {
            $data = Mage::getSingleton('eav/config')->getAttribute(Mage_Catalog_Model_Product::ENTITY, 'tier_price');
            $this->setData('tier_price_attribute', $data);
        }
        return $data;
    }

    /**
     * @return Mage_Eav_Model_Entity_Attribute_Abstract|mixed
     * @throws Mage_Core_Exception
     */
    public function getPriceAttribute()
    {
        $data = $this->getData('price_attribute');
        if (is_null($data)) {
            $data = Mage::getSingleton('eav/config')->getAttribute(Mage_Catalog_Model_Product::ENTITY, 'price');
            $this->setData('price_attribute', $data);
        }
        return $data;
    }

    /**
     * @return array|bool
     */
    #[\Override]
    public function createIndexData(Mage_Catalog_Model_Product $object, ?Mage_Eav_Model_Entity_Attribute_Abstract $attribute = null)
    {
        $searchEntityId = $object->getId();
        $priceAttributeId = $this->getTierPriceAttribute()->getId();
        if ($object->isGrouped()) {
            $priceAttributeId = $this->getPriceAttribute()->getId();
            /** @var Mage_Catalog_Model_Product_Type_Grouped $productType */
            $productType = $object->getTypeInstance(true);
            $associated = $productType->getAssociatedProducts($object);
            $searchEntityId = [];

            foreach ($associated as $product) {
                $searchEntityId[] = $product->getId();
            }
        }

        if (!count($searchEntityId)) {
            return false;
        }

        $result = [];
        $data = [];

        $data['store_id'] = $object->getStoreId();
        $data['entity_id'] = $object->getId();

        $search['store_id'] = $object->getStoreId();
        $search['entity_id'] = $searchEntityId;
        $search['attribute_id'] = $priceAttributeId;

        foreach ($this->_customerGroups as $group) {
            $search['customer_group_id'] = $group->getId();
            $data['customer_group_id'] = $group->getId();

            $value = $this->_getResource()->getMinimalValue($search);
            if (is_null($value)) {
                continue;
            }
            $data['value'] = $value;
            $result[] = $data;
        }

        return $result;
    }

    /**
     * @return bool
     */
    #[\Override]
    public function isAttributeIdUsed()
    {
        return false;
    }

    /**
     * @return bool
     */
    #[\Override]
    protected function _isAttributeIndexable(Mage_Eav_Model_Entity_Attribute_Abstract $attribute)
    {
        if ($attribute->getAttributeCode() != 'minimal_price') {
            return false;
        }

        return true;
    }

    public function getCustomerGroupId(): ?int
    {
        $value = $this->getData('customer_group_id');
        return $value === null ? null : (int) $value;
    }

    public function setCustomerGroupId(?int $value): static
    {
        return $this->setData('customer_group_id', $value);
    }

    public function setEntityId(?int $value): static
    {
        return $this->setData('entity_id', $value);
    }

    public function getQty(): ?float
    {
        $value = $this->getData('qty');
        return $value === null ? null : (float) $value;
    }

    public function setQty(?float $value): static
    {
        return $this->setData('qty', $value);
    }

    public function getTaxClassId(): ?int
    {
        $value = $this->getData('tax_class_id');
        return $value === null ? null : (int) $value;
    }

    public function setTaxClassId(?int $value): static
    {
        return $this->setData('tax_class_id', $value);
    }

    public function getValue(): ?float
    {
        $value = $this->getData('value');
        return $value === null ? null : (float) $value;
    }

    public function setValue(?float $value): static
    {
        return $this->setData('value', $value);
    }

    public function getWebsiteId(): ?int
    {
        $value = $this->getData('website_id');
        return $value === null ? null : (int) $value;
    }

    public function setWebsiteId(?int $value): static
    {
        return $this->setData('website_id', $value);
    }

}
