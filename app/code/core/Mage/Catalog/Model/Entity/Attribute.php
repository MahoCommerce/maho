<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

declare(strict_types=1);

/**
 * Product attribute extension with event dispatching
 *
 * @package    Mage_Catalog
 *
 * @method Mage_Catalog_Model_Resource_Attribute _getResource()
 * @method Mage_Catalog_Model_Resource_Attribute getResource()
 */
class Mage_Catalog_Model_Entity_Attribute extends Mage_Eav_Model_Entity_Attribute
{
    #[\Override]
    protected $_eventPrefix = 'catalog_entity_attribute';
    #[\Override]
    protected $_eventObject = 'attribute';
    public const MODULE_NAME = 'Mage_Catalog';

    /**
     * Processing object before save data
     *
     * @return Mage_Core_Model_Abstract
     */
    #[\Override]
    protected function _beforeSave()
    {
        if ($this->_getResource()->isUsedBySuperProducts($this)) {
            throw Mage::exception('Mage_Eav', Mage::helper('eav')->__('This attribute is used in configurable products'));
        }
        $this->setData('modulePrefix', self::MODULE_NAME);
        return parent::_beforeSave();
    }

    /**
     * Processing object after save data
     *
     * @return Mage_Core_Model_Abstract
     */
    #[\Override]
    protected function _afterSave()
    {
        /**
         * Fix saving attribute in admin
         */
        Mage::getSingleton('eav/config')->clear();
        return parent::_afterSave();
    }

    public function setApplyTo(array|string|null $value): static
    {
        return $this->setData('apply_to', $value);
    }

    public function getFrontendInputRenderer(): ?string
    {
        $value = $this->getData('frontend_input_renderer');
        return $value === null ? null : (string) $value;
    }

    public function setFrontendInputRenderer(?string $value): static
    {
        return $this->setData('frontend_input_renderer', $value);
    }

    public function getIsComparable(): ?int
    {
        $value = $this->getData('is_comparable');
        return $value === null ? null : (int) $value;
    }

    public function setIsComparable(?int $value): static
    {
        return $this->setData('is_comparable', $value);
    }

    public function setIsConfigurable(?int $value): static
    {
        return $this->setData('is_configurable', $value);
    }

    public function setIsFilterableInSearch(?int $value): static
    {
        return $this->setData('is_filterable_in_search', $value);
    }

    public function getIsHtmlAllowedOnFront(): ?int
    {
        $value = $this->getData('is_html_allowed_on_front');
        return $value === null ? null : (int) $value;
    }

    public function setIsHtmlAllowedOnFront(?int $value): static
    {
        return $this->setData('is_html_allowed_on_front', $value);
    }

    public function setIsSearchable(?int $value): static
    {
        return $this->setData('is_searchable', $value);
    }

    public function getIsUsedForPriceRules(): ?int
    {
        $value = $this->getData('is_used_for_price_rules');
        return $value === null ? null : (int) $value;
    }

    public function setIsUsedForPriceRules(?int $value): static
    {
        return $this->setData('is_used_for_price_rules', $value);
    }

    public function getIsUsedForPromoRules(): ?int
    {
        $value = $this->getData('is_used_for_promo_rules');
        return $value === null ? null : (int) $value;
    }

    public function setIsUsedForPromoRules(?int $value): static
    {
        return $this->setData('is_used_for_promo_rules', $value);
    }

    public function setIsVisible(?int $value): static
    {
        return $this->setData('is_visible', $value);
    }

    public function setIsVisibleInAdvancedSearch(?int $value): static
    {
        return $this->setData('is_visible_in_advanced_search', $value);
    }

    public function setIsVisibleOnFront(?int $value): static
    {
        return $this->setData('is_visible_on_front', $value);
    }

    public function getIsWysiwygEnabled(): ?int
    {
        $value = $this->getData('is_wysiwyg_enabled');
        return $value === null ? null : (int) $value;
    }

    public function setIsWysiwygEnabled(?int $value): static
    {
        return $this->setData('is_wysiwyg_enabled', $value);
    }

    public function getPosition(): ?int
    {
        $value = $this->getData('position');
        return $value === null ? null : (int) $value;
    }

    public function setPosition(?int $value): static
    {
        return $this->setData('position', $value);
    }

    public function getSearchWeight(): ?int
    {
        $value = $this->getData('search_weight');
        return $value === null ? null : (int) $value;
    }

    public function setSearchWeight(?int $value): static
    {
        return $this->setData('search_weight', $value);
    }

    public function setUsedForSortBy(?int $value): static
    {
        return $this->setData('used_for_sort_by', $value);
    }

    public function getUsedInProductListing(): ?int
    {
        $value = $this->getData('used_in_product_listing');
        return $value === null ? null : (int) $value;
    }

    public function setUsedInProductListing(?int $value): static
    {
        return $this->setData('used_in_product_listing', $value);
    }

}
