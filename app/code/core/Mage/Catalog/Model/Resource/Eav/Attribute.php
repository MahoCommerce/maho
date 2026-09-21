<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2025 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

/**
 * @method Mage_Catalog_Model_Resource_Attribute _getResource()
 * @method Mage_Catalog_Model_Resource_Attribute getResource()
 */
class Mage_Catalog_Model_Resource_Eav_Attribute extends Mage_Eav_Model_Entity_Attribute
{
    public const SCOPE_STORE                           = 0;
    public const SCOPE_GLOBAL                          = 1;
    public const SCOPE_WEBSITE                         = 2;

    public const MODULE_NAME                           = 'Mage_Catalog';
    public const ENTITY                                = 'catalog_eav_attribute';

    /**
     * @var string
     */
    #[\Override]
    protected $_eventPrefix                     = 'catalog_entity_attribute';
    /**
     * @var string
     */
    #[\Override]
    protected $_eventObject                     = 'attribute';

    /**
     * Array with labels
     *
     * @var array|null
     */
    protected static $_labels                   = null;

    #[\Override]
    protected function _construct()
    {
        $this->_init('catalog/attribute');
    }

    /**
     * Processing object before save data
     *
     * @throws Mage_Core_Exception
     * @return Mage_Core_Model_Abstract
     */
    #[\Override]
    protected function _beforeSave()
    {
        $this->setData('modulePrefix', self::MODULE_NAME);
        if (isset($this->_origData['is_global'])) {
            $this->_data['is_global'] ??= self::SCOPE_GLOBAL;
            if (($this->_data['is_global'] != $this->_origData['is_global'])
                && $this->_getResource()->isUsedBySuperProducts($this)
            ) {
                Mage::throwException(Mage::helper('catalog')->__('Scope must not be changed, because the attribute is used in configurable products.'));
            }
        }
        if ($this->getFrontendInput() == 'price') {
            if (!$this->getBackendModel()) {
                $this->setBackendModel('catalog/product_attribute_backend_price');
            }
        }
        if ($this->getFrontendInput() == 'textarea') {
            if ($this->getIsWysiwygEnabled()) {
                $this->setIsHtmlAllowedOnFront(1);
            }
        }
        return parent::_beforeSave();
    }

    #[\Override]
    protected function _afterSave()
    {
        /**
         * Fix saving attribute in admin
         */
        Mage::getSingleton('eav/config')->clear();

        return parent::_afterSave();
    }

    /**
     * Register indexing event before delete catalog eav attribute
     */
    #[\Override]
    protected function _beforeDelete()
    {
        if ($this->_getResource()->isUsedBySuperProducts($this)) {
            Mage::throwException(Mage::helper('catalog')->__('This attribute is used in configurable products.'));
        }
        Mage::getSingleton('index/indexer')->logEvent(
            $this,
            self::ENTITY,
            Mage_Index_Model_Event::TYPE_DELETE,
        );
        return parent::_beforeDelete();
    }

    /**
     * Init indexing process after catalog eav attribute delete commit
     *
     * @return $this
     */
    #[\Override]
    protected function _afterDeleteCommit()
    {
        parent::_afterDeleteCommit();
        Mage::getSingleton('index/indexer')->indexEvents(
            self::ENTITY,
            Mage_Index_Model_Event::TYPE_DELETE,
        );
        return $this;
    }

    /**
     * Return is attribute global
     *
     * @return int
     */
    public function getIsGlobal()
    {
        return $this->_getData('is_global');
    }

    /**
     * Retrieve attribute is global scope flag
     *
     * @return bool
     */
    public function isScopeGlobal()
    {
        return $this->getIsGlobal() == self::SCOPE_GLOBAL;
    }

    /**
     * Retrieve attribute is website scope website
     *
     * @return bool
     */
    public function isScopeWebsite()
    {
        return $this->getIsGlobal() == self::SCOPE_WEBSITE;
    }

    /**
     * Retrieve attribute is store scope flag
     *
     * @return bool
     */
    public function isScopeStore()
    {
        return !$this->isScopeGlobal() && !$this->isScopeWebsite();
    }

    /**
     * Retrieve store id
     */
    #[\Override]
    public function getStoreId(): ?int
    {
        $dataObject = $this->getDataObject();
        if ($dataObject) {
            return $dataObject->getStoreId();
        }

        $storeId = $this->getDataByKey('store_id');
        return is_null($storeId) ? null : (int) $storeId;
    }

    /**
     * Retrieve apply to products array
     * Return empty array if applied to all products
     *
     * @return array
     */
    #[\Override]
    public function getApplyTo()
    {
        if ($this->getData('apply_to')) {
            if (is_array($this->getData('apply_to'))) {
                return $this->getData('apply_to');
            }
            return explode(',', $this->getData('apply_to'));
        }
        return [];
    }

    /**
     * Retrieve source model
     */
    #[\Override]
    public function getSourceModel(): ?string
    {
        $model = $this->getData('source_model');
        if (!empty($model)) {
            return $model;
        }

        if ($this->getBackendType() == 'int' && $this->getFrontendInput() == 'select') {
            return $this->getDefaultSourceModel();
        }

        return $model;
    }

    /**
     * Check is allow for rule condition
     *
     * @return bool
     */
    public function isAllowedForRuleCondition()
    {
        $allowedInputTypes = ['text', 'multiselect', 'textarea', 'date', 'datetime', 'select', 'boolean', 'price'];
        return $this->getIsVisible() && in_array($this->getFrontendInput(), $allowedInputTypes);
    }

    /**
     * Whether this attribute allows selecting several values at once in layered navigation.
     */
    public function getIsFilterableMultiple(): int
    {
        return (int) $this->_getData('is_filterable_multiple');
    }

    /**
     * Get default attribute source model
     */
    #[\Override]
    public function getDefaultSourceModel(): string
    {
        return 'eav/entity_attribute_source_table';
    }

    /**
     * @return string
     */
    #[\Override]
    #[\Deprecated(message: 'since 26.1 use getDefaultSourceModel() instead')]
    protected function _getDefaultSourceModel()
    {
        return $this->getDefaultSourceModel();
    }

    /**
     * Check is an attribute used in EAV index
     *
     * @return bool
     */
    public function isIndexable()
    {
        // exclude price attribute
        if ($this->getAttributeCode() == 'price') {
            return false;
        }

        if (!$this->getIsFilterableInSearch() && !$this->getIsVisibleInAdvancedSearch() && !$this->getIsFilterable()) {
            return false;
        }

        $backendType    = $this->getBackendType();
        $frontendInput  = $this->getFrontendInput();
        if ($backendType == 'int' && $frontendInput == 'select') {
            return true;
        }
        if (($backendType == 'varchar' || $backendType == 'text') && $frontendInput == 'multiselect') {
            return true;
        }

        if ($backendType == 'decimal') {
            return true;
        }

        return false;
    }

    /**
     * Retrieve index type for indexable attribute
     *
     * @return string|false
     */
    public function getIndexType()
    {
        if (!$this->isIndexable()) {
            return false;
        }
        if ($this->getBackendType() == 'decimal') {
            return 'decimal';
        }

        return 'source';
    }

    /**
     * Callback function which called after transaction commit in resource model
     *
     * @return $this
     */
    #[\Override]
    public function afterCommitCallback()
    {
        parent::afterCommitCallback();

        /** @var \Mage_Index_Model_Indexer $indexer */
        $indexer = Mage::getSingleton('index/indexer');
        $indexer->processEntityAction($this, self::ENTITY, Mage_Index_Model_Event::TYPE_SAVE);

        return $this;
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

    public function getIsUsedForCustomerSegment(): ?int
    {
        $value = $this->getData('is_used_for_customer_segment');
        return $value === null ? null : (int) $value;
    }

    public function setIsUsedForCustomerSegment(?int $value): static
    {
        return $this->setData('is_used_for_customer_segment', $value);
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

    public function getIsUsedForTargetRules(): ?int
    {
        $value = $this->getData('is_used_for_target_rules');
        return $value === null ? null : (int) $value;
    }

    public function setIsUsedForTargetRules(?int $value): static
    {
        return $this->setData('is_used_for_target_rules', $value);
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
