<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2023 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_CatalogRule
 */

/**
 * Catalog Rule Product Aggregated Price per date Model
 *
 * @package    Mage_CatalogRule
 *
 * @method Mage_CatalogRule_Model_Resource_Rule_Product_Price _getResource()
 * @method Mage_CatalogRule_Model_Resource_Rule_Product_Price getResource()
 */
class Mage_CatalogRule_Model_Rule_Product_Price extends Mage_Core_Model_Abstract
{
    #[\Override]
    protected function _construct()
    {
        $this->_init('catalogrule/rule_product_price');
    }

    /**
     * Apply price rule price to price index table
     *
     * @param array|string $indexTable
     * @param string $entityId
     * @param string $customerGroupId
     * @param string $websiteId
     * @param array $updateFields       the array fields for compare with rule price and update
     * @param string $websiteDate
     * @return $this
     */
    public function applyPriceRuleToIndexTable(
        Maho\Db\Select $select,
        $indexTable,
        $entityId,
        $customerGroupId,
        $websiteId,
        $updateFields,
        $websiteDate,
    ) {
        $this->_getResource()->applyPriceRuleToIndexTable(
            $select,
            $indexTable,
            $entityId,
            $customerGroupId,
            $websiteId,
            $updateFields,
            $websiteDate,
        );

        return $this;
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

    public function getEarliestEndDate(): ?string
    {
        $value = $this->getData('earliest_end_date');
        return $value === null ? null : (string) $value;
    }

    public function setEarliestEndDate(?string $value): static
    {
        return $this->setData('earliest_end_date', $value);
    }

    public function getLatestStartDate(): ?string
    {
        $value = $this->getData('latest_start_date');
        return $value === null ? null : (string) $value;
    }

    public function setLatestStartDate(?string $value): static
    {
        return $this->setData('latest_start_date', $value);
    }

    public function getProductId(): ?int
    {
        $value = $this->getData('product_id');
        return $value === null ? null : (int) $value;
    }

    public function setProductId(?int $value): static
    {
        return $this->setData('product_id', $value);
    }

    public function getRuleDate(): ?string
    {
        $value = $this->getData('rule_date');
        return $value === null ? null : (string) $value;
    }

    public function setRuleDate(?string $value): static
    {
        return $this->setData('rule_date', $value);
    }

    public function getRulePrice(): ?float
    {
        $value = $this->getData('rule_price');
        return $value === null ? null : (float) $value;
    }

    public function setRulePrice(?float $value): static
    {
        return $this->setData('rule_price', $value);
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
