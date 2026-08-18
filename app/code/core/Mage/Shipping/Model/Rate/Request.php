<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Shipping
 */

declare(strict_types=1);

/**
 * Fields:
 * - orig:
 *   - country_id: UK
 *   - region_id: 1
 *   - postcode: 90034
 * - dest:
 *   - country_id: UK
 *   - region_id: 2
 *   - postcode: 01005
 * - package:
 *   - value: $100
 *   - weight: 1.5 lb
 *   - height: 10"
 *   - width: 10"
 *   - depth: 10"
 * - order:
 *   - total_qty: 10
 *   - subtotal: $100
 * - option
 *   - insurance: true
 *   - handling: $1
 * - table (shiptable)
 *   - condition_name: package_weight
 * - limit
 *   - carrier: ups
 *   - method: 3dp
 * - ups
 *   - pickup: CC
 *   - container: CP
 *   - address: RES
 *
 * @package    Mage_Shipping
 *
 * @method string|array getConditionName()
 * @method $this setConditionName(string|array $value)
 */

class Mage_Shipping_Model_Rate_Request extends \Maho\DataObject
{
    public function getAllItems(): ?array
    {
        return $this->getData('all_items');
    }

    public function setAllItems(?array $value): static
    {
        return $this->setData('all_items', $value);
    }

    public function getBaseCurrency(): ?Mage_Directory_Model_Currency
    {
        return $this->getData('base_currency');
    }

    public function setBaseCurrency(?Mage_Directory_Model_Currency $value): static
    {
        return $this->setData('base_currency', $value);
    }

    public function getBaseSubtotalInclTax(): ?float
    {
        $value = $this->getData('base_subtotal_incl_tax');
        return $value === null ? null : (float) $value;
    }

    public function setBaseSubtotalInclTax(?float $value): static
    {
        return $this->setData('base_subtotal_incl_tax', $value);
    }

    public function setCity(?string $value): static
    {
        return $this->setData('city', $value);
    }

    public function setCountryId(?string $value): static
    {
        return $this->setData('country_id', $value);
    }

    public function getDestCountryId(): ?string
    {
        $value = $this->getData('dest_country_id');
        return $value === null ? null : (string) $value;
    }

    public function setDestCountryId(?string $value): static
    {
        return $this->setData('dest_country_id', $value);
    }

    public function getDestRegionId(): ?int
    {
        $value = $this->getData('dest_region_id');
        return $value === null ? null : (int) $value;
    }

    public function setDestRegionId(?int $value): static
    {
        return $this->setData('dest_region_id', $value);
    }

    public function getDestRegionCode(): ?string
    {
        $value = $this->getData('dest_region_code');
        return $value === null ? null : (string) $value;
    }

    public function setDestRegionCode(?string $value): static
    {
        return $this->setData('dest_region_code', $value);
    }

    public function getDestPostcode(): ?string
    {
        $value = $this->getData('dest_postcode');
        return $value === null ? null : (string) $value;
    }

    public function setDestPostcode(?string $value): static
    {
        return $this->setData('dest_postcode', $value);
    }

    public function getDestCity(): ?string
    {
        $value = $this->getData('dest_city');
        return $value === null ? null : (string) $value;
    }

    public function setDestCity(?string $value): static
    {
        return $this->setData('dest_city', $value);
    }

    public function getDestStreet(): ?string
    {
        $value = $this->getData('dest_street');
        return $value === null ? null : (string) $value;
    }

    public function setDestStreet(?string $value): static
    {
        return $this->setData('dest_street', $value);
    }

    public function getFreeShipping(): ?bool
    {
        $value = $this->getData('free_shipping');
        return $value === null ? null : (bool) $value;
    }

    public function setFreeShipping(?bool $value): static
    {
        return $this->setData('free_shipping', $value);
    }

    public function getFreeMethodWeight(): ?float
    {
        $value = $this->getData('free_method_weight');
        return $value === null ? null : (float) $value;
    }

    public function setFreeMethodWeight(?float $value): static
    {
        return $this->setData('free_method_weight', $value);
    }

    public function getLimitCarrier(): array|bool|string|null
    {
        return $this->getData('limit_carrier');
    }

    public function setLimitCarrier(array|bool|string|null $value): static
    {
        return $this->setData('limit_carrier', $value);
    }

    public function getLimitMethod(): ?string
    {
        $value = $this->getData('limit_method');
        return $value === null ? null : (string) $value;
    }

    public function setLimitMethod(?string $value): static
    {
        return $this->setData('limit_method', $value);
    }

    public function getOptionInsurance(): ?bool
    {
        $value = $this->getData('option_insurance');
        return $value === null ? null : (bool) $value;
    }

    public function setOptionInsurance(?bool $value): static
    {
        return $this->setData('option_insurance', $value);
    }

    public function getOptionHandling(): ?float
    {
        $value = $this->getData('option_handling');
        return $value === null ? null : (float) $value;
    }

    public function setOptionHandling(?float $value): static
    {
        return $this->setData('option_handling', $value);
    }

    public function getOrderTotalQty(): ?float
    {
        $value = $this->getData('order_total_qty');
        return $value === null ? null : (float) $value;
    }

    public function setOrderTotalQty(?float $value): static
    {
        return $this->setData('order_total_qty', $value);
    }

    public function getOrderSubtotal(): ?float
    {
        $value = $this->getData('order_subtotal');
        return $value === null ? null : (float) $value;
    }

    public function setOrderSubtotal(?float $value): static
    {
        return $this->setData('order_subtotal', $value);
    }

    public function getOrigCountryId(): ?string
    {
        $value = $this->getData('orig_country_id');
        return $value === null ? null : (string) $value;
    }

    public function setOrigCountryId(?string $value): static
    {
        return $this->setData('orig_country_id', $value);
    }

    public function getOrigRegionId(): ?int
    {
        $value = $this->getData('orig_region_id');
        return $value === null ? null : (int) $value;
    }

    public function setOrigRegionId(?int $value): static
    {
        return $this->setData('orig_region_id', $value);
    }

    public function getOrigPostcode(): ?string
    {
        $value = $this->getData('orig_postcode');
        return $value === null ? null : (string) $value;
    }

    public function setOrigPostcode(?string $value): static
    {
        return $this->setData('orig_postcode', $value);
    }

    public function getOrigCity(): ?string
    {
        $value = $this->getData('orig_city');
        return $value === null ? null : (string) $value;
    }

    public function setOrigCity(?string $value): static
    {
        return $this->setData('orig_city', $value);
    }

    public function getPackageValue(): ?float
    {
        $value = $this->getData('package_value');
        return $value === null ? null : (float) $value;
    }

    public function setPackageValue(?float $value): static
    {
        return $this->setData('package_value', $value);
    }

    public function getPackageValueWithDiscount(): ?float
    {
        $value = $this->getData('package_value_with_discount');
        return $value === null ? null : (float) $value;
    }

    public function setPackageValueWithDiscount(?float $value): static
    {
        return $this->setData('package_value_with_discount', $value);
    }

    public function getPackagePhysicalValue(): ?float
    {
        $value = $this->getData('package_physical_value');
        return $value === null ? null : (float) $value;
    }

    public function setPackagePhysicalValue(?float $value): static
    {
        return $this->setData('package_physical_value', $value);
    }

    public function getPackageQty(): ?float
    {
        $value = $this->getData('package_qty');
        return $value === null ? null : (float) $value;
    }

    public function setPackageQty(?float $value): static
    {
        return $this->setData('package_qty', $value);
    }

    public function getPackageWeight(): ?float
    {
        $value = $this->getData('package_weight');
        return $value === null ? null : (float) $value;
    }

    public function setPackageWeight(?float $value): static
    {
        return $this->setData('package_weight', $value);
    }

    public function getPackageHeight(): ?int
    {
        $value = $this->getData('package_height');
        return $value === null ? null : (int) $value;
    }

    public function setPackageHeight(?int $value): static
    {
        return $this->setData('package_height', $value);
    }

    public function getPackageWidth(): ?int
    {
        $value = $this->getData('package_width');
        return $value === null ? null : (int) $value;
    }

    public function setPackageWidth(?int $value): static
    {
        return $this->setData('package_width', $value);
    }

    public function getPackageDepth(): ?int
    {
        $value = $this->getData('package_depth');
        return $value === null ? null : (int) $value;
    }

    public function setPackageDepth(?int $value): static
    {
        return $this->setData('package_depth', $value);
    }

    public function getPackageCurrency(): ?Mage_Directory_Model_Currency
    {
        return $this->getData('package_currency');
    }

    public function setPackageCurrency(?Mage_Directory_Model_Currency $value): static
    {
        return $this->setData('package_currency', $value);
    }

    public function setPostcode(?string $value): static
    {
        return $this->setData('postcode', $value);
    }

    public function setRegionId(?string $value): static
    {
        return $this->setData('region_id', $value);
    }

    public function getStore(): ?Mage_Core_Model_Store
    {
        return $this->getData('store');
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
