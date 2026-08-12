<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

/**
 * Filter item model
 *
 * @package    Mage_Catalog
 *
 * @method int getCount()
 * @method $this setCount(int $value)
 * @method string getLabel()
 * @method $this setLabel(string $value)
 * @method string getValue()
 * @method $this setValue(string $value)
 * @method $this setFilter(Mage_Catalog_Model_Layer_Filter_Abstract $value)
 */
class Mage_Catalog_Model_Layer_Filter_Item extends \Maho\DataObject
{
    /**
     * Get filter instance
     *
     * @return Mage_Catalog_Model_Layer_Filter_Abstract
     */
    public function getFilter()
    {
        $filter = $this->getData('filter');
        if (!is_object($filter)) {
            Mage::throwException(
                Mage::helper('catalog')->__('Filter must be an object. Please set correct filter.'),
            );
        }
        return $filter;
    }

    /**
     * Get filter item url
     *
     * For a multi-select filter this value is appended to the values already
     * selected in the facet (OR logic) rather than replacing them.
     *
     * @return string
     */
    public function getUrl()
    {
        $filter = $this->getFilter();
        $query = [
            $filter->getRequestVar() => $this->_getAppliedValue(),
            Mage::getBlockSingleton('page/html_pager')->getPageVarName() => null, // exclude current page from urls
        ];
        return Mage::getUrl('*/*/*', ['_current' => true, '_use_rewrite' => true, '_query' => $query]);
    }

    /**
     * Get url for remove item from filter
     *
     * For a multi-select filter only this value is removed; the other selected
     * values in the facet are kept.
     *
     * @return string
     */
    public function getRemoveUrl()
    {
        $filter = $this->getFilter();
        $query = [$filter->getRequestVar() => $this->_getRemovedValue()];
        $params['_current']     = true;
        $params['_use_rewrite'] = true;
        $params['_query']       = $query;
        $params['_escape']      = true;
        return Mage::getUrl('*/*/*', $params);
    }

    /**
     * Request value that adds this item to the current selection.
     *
     * Single value for a normal filter; this value appended to the already
     * selected ones (comma-separated) for a multi-select filter.
     */
    protected function _getAppliedValue(): string
    {
        $filter = $this->getFilter();
        if (!$filter->isMultipleSelect()) {
            return (string) $this->getValue();
        }

        $values = $filter->getAppliedValues();
        $values[] = $this->getValue();

        return implode(',', array_unique(array_map(strval(...), $values)));
    }

    /**
     * Request value that removes this item from the current selection.
     *
     * Reset value for a normal filter; the remaining selected values for a
     * multi-select filter, or the reset value when this was the last one.
     */
    protected function _getRemovedValue(): mixed
    {
        $filter = $this->getFilter();
        if (!$filter->isMultipleSelect()) {
            return $filter->getResetValue();
        }

        $remaining = array_diff(
            array_map(strval(...), $filter->getAppliedValues()),
            [(string) $this->getValue()],
        );

        return $remaining === [] ? $filter->getResetValue() : implode(',', $remaining);
    }

    /**
     * Get url for "clear" link
     *
     * @return false|string
     */
    public function getClearLinkUrl()
    {
        $clearLinkText = $this->getFilter()->getClearLinkText();
        if (!$clearLinkText) {
            return false;
        }

        $urlParams = [
            '_current' => true,
            '_use_rewrite' => true,
            '_query' => [$this->getFilter()->getRequestVar() => null],
            '_escape' => true,
        ];
        return Mage::getUrl('*/*/*', $urlParams);
    }

    /**
     * Get item filter name
     *
     * @return string
     */
    public function getName()
    {
        return $this->getFilter()->getName();
    }

    /**
     * Get item value as string
     *
     * @return string
     */
    public function getValueString()
    {
        $value = $this->getValue();
        if (is_array($value)) {
            return implode(',', $value);
        }
        return $value;
    }
}
