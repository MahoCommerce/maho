<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2025 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

declare(strict_types=1);

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

    public const XML_PATH_ALLOWED_HTML_TAGS = 'system/catalog/frontend/allowed_html_tags_list';

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
     * @throws Mage_Core_Exception_Conflict when the scope changes on an attribute of configurable products
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
                throw new Mage_Core_Exception_Conflict(Mage::helper('catalog')->__('Scope must not be changed, because the attribute is used in configurable products.'));
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
            throw new Mage_Core_Exception_Conflict(Mage::helper('catalog')->__('This attribute is used in configurable products.'));
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

    #[\Override]
    public function getAdminPageSettings(): ?Mage_Eav_Model_Entity_Attribute_AdminPageSettings
    {
        if ($this->getEntityType()->getEntityTypeCode() !== Mage_Catalog_Model_Category::ENTITY) {
            return null;
        }
        $helper = Mage::helper('catalog');
        return new Mage_Eav_Model_Entity_Attribute_AdminPageSettings(
            acl: 'system/attributes/catalog_category',
            title: $helper->__('Category Attributes'),
            setsTitle: $helper->__('Category Attribute Sets'),
            mainTab: 'adminhtml/catalog_category_attribute_edit_tab_main',
            allowedTags: $this->getAllowedOptionTags(),
            addToDefaultSet: true,
        );
    }

    /**
     * A category adds the image type. A product takes the types of general/validator_data/input_types, with the
     * models of Mage_Catalog_Helper_Product::getAttributeInputTypes().
     */
    #[\Override]
    protected function _getInputTypes(): array
    {
        $types = parent::_getInputTypes();
        $helper = Mage::helper('catalog');
        $entityType = $this->getEntityType()->getEntityTypeCode();
        if ($entityType === Mage_Catalog_Model_Category::ENTITY) {
            $types['image'] = [
                'label' => $helper->__('Image'),
                'backend_model' => 'catalog/category_attribute_backend_image',
            ];
        }
        if ($entityType !== Mage_Catalog_Model_Product::ENTITY) {
            return $types;
        }

        $labels = ['price' => $helper->__('Price'), 'media_image' => $helper->__('Media Image'), 'file' => $helper->__('File')];
        /** @var Mage_Catalog_Helper_Product $productHelper */
        $productHelper = Mage::helper('catalog/product');
        $productTypes = [];
        foreach (array_keys((array) Mage::helper('eav')->getInputTypesValidatorData()) as $code) {
            $code = (string) $code;
            $type = $types[$code] ?? ['label' => $labels[$code] ?? ucwords(str_replace('_', ' ', $code))];
            foreach ((array) $productHelper->getAttributeInputTypes($code) as $key => $model) {
                if (in_array($key, ['backend_model', 'source_model', 'frontend_model'], true) && is_string($model)) {
                    $type[$key] ??= $model;
                }
            }
            $productTypes[$code] = $type;
        }
        return $productTypes;
    }

    /**
     * Add the rules of a catalog attribute: a code that the product model does not reserve, a known scope and layered
     * navigation value, a position of 0 or more, and the known product types in apply_to.
     */
    #[\Override]
    public function getValidationErrors(): Mage_Core_Exception_Input
    {
        $errors = parent::getValidationErrors();
        $helper = Mage::helper('catalog');

        /** @var Mage_Catalog_Model_Product $product */
        $product = Mage::getModel('catalog/product');
        if ($product->isReservedAttribute($this)) {
            $errors->addError('attribute_code', $helper->__('The attribute code \'%s\' is reserved by system. Please try another attribute code', $this->getAttributeCode()));
        }

        $choices = [
            'is_global' => [[self::SCOPE_STORE, self::SCOPE_GLOBAL, self::SCOPE_WEBSITE], $helper->__('The scope must be 0 (store view), 1 (global) or 2 (website).')],
            'is_filterable' => [[0, 1, 2], $helper->__('The layered navigation value must be 0 (no), 1 (filterable with results) or 2 (filterable with no results).')],
        ];
        foreach ($choices as $field => [$allowed, $message]) {
            $value = $this->getData($field);
            if ($value !== null && $this->isValueChanged($field) && !in_array((string) (is_bool($value) ? (int) $value : $value), array_map(strval(...), $allowed), true)) {
                $errors->addError($field, $message);
            }
        }

        $position = $this->getData('position');
        if ($position !== null && $position !== '' && $this->isValueChanged('position')
            && (!is_scalar($position) || filter_var($position, FILTER_VALIDATE_INT) === false || (int) $position < 0)
        ) {
            $errors->addError('position', $helper->__('The position must be an integer of 0 or more.'));
        }

        if ($this->getEntityTypeId() && $this->getEntityType()->getEntityTypeCode() === Mage_Catalog_Model_Product::ENTITY
            && $this->isValueChanged('apply_to')
        ) {
            $productTypes = array_keys(Mage_Catalog_Model_Product_Type::getTypes());
            $applyTo = array_filter(array_map(strval(...), $this->getApplyTo()), static fn(string $type): bool => $type !== '');
            if (array_diff($applyTo, $productTypes) !== []) {
                $errors->addError('apply_to', $helper->__('Apply To must contain only these product types: %s', implode(', ', $productTypes)));
            }
        }

        return $errors;
    }

    /**
     * Return the tags of the Allowed HTML Tags setting of the catalog.
     */
    #[\Override]
    public function getAllowedOptionTags(): array
    {
        return array_values(array_filter(explode(',', (string) Mage::getStoreConfig(self::XML_PATH_ALLOWED_HTML_TAGS))));
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
