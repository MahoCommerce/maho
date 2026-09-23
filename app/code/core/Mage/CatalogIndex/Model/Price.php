<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2023 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_CatalogIndex
 */

declare(strict_types=1);

/**
 * Price index model
 *
 * @package    Mage_CatalogIndex
 *
 * @method Mage_CatalogIndex_Model_Resource_Price _getResource()
 * @method Mage_CatalogIndex_Model_Resource_Price getResource()
 */
class Mage_CatalogIndex_Model_Price extends Mage_Core_Model_Abstract
{
    #[\Override]
    protected function _construct()
    {
        $this->_init('catalogindex/price');
        $this->_getResource()->setStoreId(Mage::app()->getStore()->getId());
        $this->_getResource()->setRate(Mage::app()->getStore()->getCurrentCurrencyRate());
        $this->_getResource()->setCustomerGroupId(Mage::getSingleton('customer/session')->getCustomerGroupId());
    }

    /**
     * @param Mage_Eav_Model_Entity_Attribute $attribute
     * @param Maho\Db\Select $entityIdsFilter
     * @return float|int
     */
    public function getMaxValue($attribute, $entityIdsFilter)
    {
        return $this->_getResource()->getMaxValue($attribute, $entityIdsFilter);
    }

    /**
     * @param Mage_Eav_Model_Entity_Attribute $attribute
     * @param int $range
     * @param Maho\Db\Select $entitySelect
     * @return array
     */
    public function getCount($attribute, $range, $entitySelect)
    {
        return $this->_getResource()->getCount($range, $attribute, $entitySelect);
    }

    /**
     * @param Mage_Eav_Model_Entity_Attribute $attribute
     * @param int $range
     * @param int $index
     * @param array $entityIdsFilter
     * @return array
     */
    public function getFilteredEntities($attribute, $range, $index, $entityIdsFilter)
    {
        return $this->_getResource()->getFilteredEntities($range, $index, $attribute, $entityIdsFilter);
    }

    /**
     * @param Mage_Eav_Model_Resource_Entity_Attribute_Collection $collection
     * @param Mage_Eav_Model_Entity_Attribute $attribute
     * @param int $range
     * @param int $index
     * @return Mage_CatalogIndex_Model_Resource_Price
     */
    public function applyFilterToCollection($collection, $attribute, $range, $index)
    {
        return $this->_getResource()->applyFilterToCollection($collection, $attribute, $range, $index);
    }

    public function addMinimalPrices(Mage_Catalog_Model_Resource_Product_Collection $collection)
    {
        $minimalPrices = $this->_getResource()->getMinimalPrices($collection->getLoadedIds());

        foreach ($minimalPrices as $row) {
            $item = $collection->getItemById($row['entity_id']);
            if ($item) {
                $item->setData('minimal_price', $row['value']);
                $item->setData('minimal_tax_class_id', $row['tax_class_id']);
            }
        }
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
