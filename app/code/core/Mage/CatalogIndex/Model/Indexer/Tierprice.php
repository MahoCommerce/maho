<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_CatalogIndex
 */

declare(strict_types=1);

/**
 * Tier Price indexer
 *
 * @package    Mage_CatalogIndex
 *
 * @method Mage_CatalogIndex_Model_Resource_Indexer_Price _getResource()
 * @method Mage_CatalogIndex_Model_Resource_Indexer_Price getResource()
 */
class Mage_CatalogIndex_Model_Indexer_Tierprice extends Mage_CatalogIndex_Model_Indexer_Abstract
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
    protected $_processChildren = false;

    #[\Override]
    protected function _construct()
    {
        $this->_init('catalogindex/indexer_price');
        $this->_currencyModel = Mage::getModel('directory/currency');
        $this->_customerGroups = Mage::getModel('customer/group')->getCollection();

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

        $result = [];
        $values = $object->getData($attribute->getAttributeCode());

        if (!is_array($values)) {
            return $result;
        }

        foreach ($values as $row) {
            if (isset($row['delete']) && $row['delete']) {
                continue;
            }

            $data['qty'] = $row['price_qty'];
            $data['value'] = $row['price'];
            if ($row['cust_group'] == Mage_Customer_Model_Group::CUST_GROUP_ALL) {
                foreach ($this->_customerGroups as $group) {
                    $data['customer_group_id'] = $group->getId();
                    $result[] = $data;
                }
            } else {
                $data['customer_group_id'] = $row['cust_group'];
                $result[] = $data;
            }
        }

        return $result;
    }

    /**
     * @return bool
     */
    #[\Override]
    protected function _isAttributeIndexable(Mage_Eav_Model_Entity_Attribute_Abstract $attribute)
    {
        if ($attribute->getAttributeCode() != 'tier_price') {
            return false;
        }

        return true;
    }

    /**
     * @return array
     */
    #[\Override]
    protected function _getIndexableAttributeConditions()
    {
        $conditions = [];
        $conditions['attribute_code'] = 'tier_price';

        return $conditions;
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

    public function getFinalPrice(): ?float
    {
        $value = $this->getData('final_price');
        return $value === null ? null : (float) $value;
    }

    public function setFinalPrice(?float $value): static
    {
        return $this->setData('final_price', $value);
    }

    public function getMaxPrice(): ?float
    {
        $value = $this->getData('max_price');
        return $value === null ? null : (float) $value;
    }

    public function setMaxPrice(?float $value): static
    {
        return $this->setData('max_price', $value);
    }

    public function getMinPrice(): ?float
    {
        $value = $this->getData('min_price');
        return $value === null ? null : (float) $value;
    }

    public function setMinPrice(?float $value): static
    {
        return $this->setData('min_price', $value);
    }

    public function getPrice(): ?float
    {
        $value = $this->getData('price');
        return $value === null ? null : (float) $value;
    }

    public function setPrice(?float $value): static
    {
        return $this->setData('price', $value);
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

    public function getTierPrice(): ?float
    {
        $value = $this->getData('tier_price');
        return $value === null ? null : (float) $value;
    }

    public function setTierPrice(?float $value): static
    {
        return $this->setData('tier_price', $value);
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
