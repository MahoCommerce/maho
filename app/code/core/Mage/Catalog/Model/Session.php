<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

declare(strict_types=1);

/**
 * @method $this unsDisplayMode()
 * @method $this unsLimitPage()
 * @method $this unsSortDirection()
 * @method $this unsSortOrder()
 */

class Mage_Catalog_Model_Session extends Mage_Core_Model_Session_Abstract
{
    public function __construct()
    {
        $this->init('catalog');
    }

    /**
     * @return string
     */
    public function getDisplayMode()
    {
        return $this->_getData('display_mode');
    }

    public function setBeforeCompareUrl(?string $value): static
    {
        return $this->setData('before_compare_url', $value);
    }

    public function getFormData(): ?array
    {
        return $this->getData('form_data');
    }

    public function setFormData(?array $value): static
    {
        return $this->setData('form_data', $value);
    }

    public function getLastViewedCategoryId(): ?int
    {
        $value = $this->getData('last_viewed_category_id');
        return $value === null ? null : (int) $value;
    }

    public function getLastViewedProductId(): ?int
    {
        $value = $this->getData('last_viewed_product_id');
        return $value === null ? null : (int) $value;
    }

    public function setLastViewedProductId(?int $value): static
    {
        return $this->setData('last_viewed_product_id', $value);
    }

    public function getLastVisitedCategoryId(): ?int
    {
        $value = $this->getData('last_visited_category_id');
        return $value === null ? null : (int) $value;
    }

    public function getLimitPage(): ?string
    {
        $value = $this->getData('limit_page');
        return $value === null ? null : (string) $value;
    }

    public function getParamsMemorizeDisabled(): ?bool
    {
        $value = $this->getData('params_memorize_disabled');
        return $value === null ? null : (bool) $value;
    }

    public function getSortDirection(): ?string
    {
        $value = $this->getData('sort_direction');
        return $value === null ? null : (string) $value;
    }

    public function getSortOrder(): ?string
    {
        $value = $this->getData('sort_order');
        return $value === null ? null : (string) $value;
    }
}
