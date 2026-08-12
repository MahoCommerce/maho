<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

class Mage_Catalog_Model_Layer_Filter_Attribute extends Mage_Catalog_Model_Layer_Filter_Abstract
{
    public const OPTIONS_ONLY_WITH_RESULTS = 1;

    /**
     * Resource instance
     *
     * @var Mage_Catalog_Model_Resource_Layer_Filter_Attribute|null
     */
    protected $_resource;

    /**
     * Option values currently applied for a multi-select attribute filter
     *
     * @var array
     */
    protected $_appliedValues = [];

    /**
     * Construct attribute filter
     */
    public function __construct()
    {
        parent::__construct();
        $this->_requestVar = 'attribute';
    }

    /**
     * Retrieve resource instance
     *
     * @return Mage_Catalog_Model_Resource_Layer_Filter_Attribute
     */
    protected function _getResource()
    {
        if (is_null($this->_resource)) {
            $this->_resource = Mage::getResourceModel('catalog/layer_filter_attribute');
        }
        return $this->_resource;
    }

    /**
     * Get option text from frontend model by option id
     *
     * @param   int|string $optionId
     * @return  string|bool
     */
    protected function _getOptionText($optionId)
    {
        return $this->getAttributeModel()->getFrontend()->getOption($optionId);
    }

    /**
     * Apply attribute option filter to product collection
     *
     * @param \Maho\DataObject $filterBlock
     * @return  Mage_Catalog_Model_Layer_Filter_Attribute
     */
    #[\Override]
    public function apply(Mage_Core_Controller_Request_Http $request, $filterBlock)
    {
        $filter = $request->getParam($this->_requestVar);

        if ($this->isMultipleSelect()) {
            return $this->_applyMultiple($filter);
        }

        if (is_array($filter)) {
            return $this;
        }
        $text = $this->_getOptionText($filter);
        if (!is_string($text)) {
            return $this;
        }

        if ($filter && strlen($text)) {
            $this->_getResource()->applyFilterToCollection($this, $filter);
            $this->getLayer()->getState()->addFilter($this->_createItem($text, $filter));
            $this->_items = [];
        }
        return $this;
    }

    /**
     * Apply a multi-select attribute filter (OR within the facet).
     *
     * Every valid value is applied to the product collection with a single
     * IN() condition and gets its own removable state item. Unlike the
     * single-select path the facet is left renderable, so the remaining
     * option values stay available to be OR-ed into the current selection.
     */
    protected function _applyMultiple(array|string|null $filter): static
    {
        $applied = [];
        foreach ($this->_parseRequestValues($filter) as $value) {
            $value = $this->_getCanonicalOptionValue($value);
            if ($value === null) {
                continue;
            }
            $text = $this->_getOptionText($value);
            if (is_string($text) && strlen($text)) {
                $applied[$value] = $text;
            }
        }

        if (empty($applied)) {
            return $this;
        }

        $this->_appliedValues = array_keys($applied);
        $this->_getResource()->applyFilterToCollection($this, $this->_appliedValues);
        foreach ($applied as $value => $text) {
            $this->getLayer()->getState()->addFilter($this->_createItem($text, $value));
        }

        return $this;
    }

    /**
     * Normalize the request value of a multi-select filter to a list of values.
     *
     * Accepts both the repeated-parameter form (?code[]=a&code[]=b, parsed by
     * PHP into an array) and the comma-separated form (?code=a,b). Values are
     * trimmed, empties dropped and duplicates removed.
     */
    protected function _parseRequestValues(array|string|null $filter): array
    {
        if (is_array($filter)) {
            $values = $filter;
        } elseif (is_string($filter) && strlen($filter)) {
            $values = explode(',', $filter);
        } else {
            return [];
        }

        // Request params can nest (?code[][]=1), so drop non-scalars before trimming.
        $values = array_map(trim(...), array_map(strval(...), array_filter($values, is_scalar(...))));

        return array_values(array_unique(array_filter($values, fn(string $value): bool => $value !== '')));
    }

    /**
     * Resolve a requested value to the option value it denotes.
     *
     * Matching is strict, so only the exact values this filter puts in its own
     * URLs are accepted. Other spellings of the same id ('04', '4.0') would each
     * survive as a distinct applied value, duplicating state chips and leaving
     * the option unmatchable against the applied ones.
     *
     * @return string|null canonical option value, or null when unknown
     */
    protected function _getCanonicalOptionValue(string $value): ?string
    {
        foreach ($this->getAttributeModel()->getFrontend()->getSelectOptions() as $option) {
            if (!isset($option['value']) || is_array($option['value'])) {
                continue;
            }
            if ((string) $option['value'] === $value) {
                return (string) $option['value'];
            }
        }

        return null;
    }

    /**
     * Whether this attribute allows selecting several values at once.
     */
    #[\Override]
    public function isMultipleSelect(): bool
    {
        return (bool) $this->getAttributeModel()->getIsFilterableMultiple();
    }

    /**
     * Option values currently applied for this multi-select filter.
     */
    #[\Override]
    public function getAppliedValues(): array
    {
        return $this->_appliedValues;
    }

    /**
     * Check whether specified attribute can be used in LN
     *
     * @param Mage_Catalog_Model_Resource_Eav_Attribute $attribute
     * @return int
     */
    protected function _getIsFilterableAttribute($attribute)
    {
        return $attribute->getIsFilterable();
    }

    /**
     * Get data array for building attribute filter items
     *
     * @return array
     */
    #[\Override]
    protected function _getItemsData()
    {
        $attribute = $this->getAttributeModel();
        $this->_requestVar = $attribute->getAttributeCode();

        $key = $this->getLayer()->getStateKey() . '_' . $this->_requestVar;
        $data = $this->getLayer()->getAggregator()->getCacheData($key);

        if ($data === null) {
            $options = $attribute->getFrontend()->getSelectOptions();
            $optionsCount = $this->_getResource()->getCount($this);
            // Values already selected are shown as removable state chips, so drop
            // them from the option list; the rest stay available to be OR-ed in.
            $appliedValues = array_map(strval(...), $this->getAppliedValues());
            $data = [];
            foreach ($options as $option) {
                if (is_array($option['value'])) {
                    continue;
                }
                if (in_array((string) $option['value'], $appliedValues, true)) {
                    continue;
                }
                if (Mage::helper('core/string')->strlen($option['value'])) {
                    // Check filter type
                    if ($this->_getIsFilterableAttribute($attribute) == self::OPTIONS_ONLY_WITH_RESULTS) {
                        if (!empty($optionsCount[$option['value']])) {
                            $data[] = [
                                'label' => $option['label'],
                                'value' => $option['value'],
                                'count' => $optionsCount[$option['value']],
                            ];
                        }
                    } else {
                        $data[] = [
                            'label' => $option['label'],
                            'value' => $option['value'],
                            'count' => $optionsCount[$option['value']] ?? 0,
                        ];
                    }
                }
            }

            $tags = [
                Mage_Eav_Model_Entity_Attribute::CACHE_TAG . ':' . $attribute->getId(),
            ];

            $tags = $this->getLayer()->getStateTags($tags);
            $this->getLayer()->getAggregator()->saveCacheData($data, $key, $tags);
        }
        return $data;
    }
}
