<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2021-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_Widget_Grid_Column_Filter_Price extends Mage_Adminhtml_Block_Widget_Grid_Column_Filter_Abstract
{
    protected $_currencyList = null;
    protected $_currencyModel = null;

    #[\Override]
    public function getHtml()
    {
        $fromLabel = Mage::helper('adminhtml')->__('From');
        $toLabel = Mage::helper('adminhtml')->__('To');

        $html  = '<div class="range filter-price">';
        $html .= '<div class="range-line">'
            . '<span class="label" aria-hidden="true" title="' . $this->quoteEscape($fromLabel) . '">&ge;</span>'
            . '<input type="number" name="' . $this->_getHtmlName() . '[from]" id="' . $this->_getHtmlId() . '_from"'
                . ' aria-label="' . $this->quoteEscape($fromLabel) . '" title="' . $this->quoteEscape($fromLabel) . '"'
                . ' value="' . $this->getEscapedValue('from') . '" class="input-text no-changes"></div>';
        $html .= '<div class="range-line">'
            . '<span class="label" aria-hidden="true" title="' . $this->quoteEscape($toLabel) . '">&le;</span>'
            . '<input type="number" name="' . $this->_getHtmlName() . '[to]" id="' . $this->_getHtmlId() . '_to"'
                . ' aria-label="' . $this->quoteEscape($toLabel) . '" title="' . $this->quoteEscape($toLabel) . '"'
                . ' value="' . $this->getEscapedValue('to') . '" class="input-text no-changes"></div>';
        if ($this->getDisplayCurrencySelect()) {
            $currencyLabel = Mage::helper('adminhtml')->__('Currency');
            $html .= '<div class="range-line">'
                . '<span class="label" aria-hidden="true"></span>'
                . $this->_getCurrencySelectHtml($currencyLabel) . '</div>';
        }
        $html .= '</div>';

        return $html;
    }

    public function getDisplayCurrencySelect()
    {
        if (!is_null($this->getColumn()->getData('display_currency_select'))) {
            return $this->getColumn()->getData('display_currency_select');
        }
        return true;
    }

    public function getCurrencyAffect()
    {
        if (!is_null($this->getColumn()->getData('currency_affect'))) {
            return $this->getColumn()->getData('currency_affect');
        }
        return true;
    }

    protected function _getCurrencyModel()
    {
        if (is_null($this->_currencyModel)) {
            $this->_currencyModel = Mage::getModel('directory/currency');
        }

        return $this->_currencyModel;
    }

    protected function _getCurrencySelectHtml(?string $label = null)
    {
        $value = $this->getEscapedValue('currency');
        if (!$value) {
            $value = $this->getColumn()->getCurrencyCode();
        }

        $label = $label ?: Mage::helper('adminhtml')->__('Currency');
        $html  = '';
        $html .= '<select name="' . $this->_getHtmlName() . '[currency]" id="' . $this->_getHtmlId() . '_currency"'
            . ' aria-label="' . $this->quoteEscape($label) . '" title="' . $this->quoteEscape($label) . '">';
        foreach ($this->_getCurrencyList() as $currency) {
            $html .= '<option value="' . $currency . '" ' . ($currency == $value ? 'selected="selected"' : '') . '>'
                . $currency . '</option>';
        }
        $html .= '</select>';
        return $html;
    }

    protected function _getCurrencyList()
    {
        if (is_null($this->_currencyList)) {
            $this->_currencyList = $this->_getCurrencyModel()->getConfigAllowCurrencies();
        }
        return $this->_currencyList;
    }

    public function getValue($index = null)
    {
        if ($index) {
            return $this->getData('value', $index);
        }
        $value = $this->getData('value');
        if ((isset($value['from']) && (string) $value['from'] !== '')
            || (isset($value['to']) && (string) $value['to'] !== '')
        ) {
            return $value;
        }
        return null;
    }

    #[\Override]
    public function getCondition()
    {
        $value = $this->getValue();

        if (isset($value['currency']) && $this->getCurrencyAffect()) {
            $displayCurrency = $value['currency'];
        } else {
            $displayCurrency = $this->getColumn()->getCurrencyCode();
        }
        $rate = $this->_getRate($displayCurrency, $this->getColumn()->getCurrencyCode());

        foreach (['from', 'to'] as $key) {
            if (isset($value[$key]) && is_numeric($value[$key])) {
                $value[$key] = sprintf('%F', $value[$key] * $rate);
            }
        }

        $this->prepareRates($displayCurrency);
        return $value;
    }

    protected function _getRate($from, $to)
    {
        return Mage::getModel('directory/currency')->load($from)->getAnyRate($to);
    }

    public function prepareRates($displayCurrency)
    {
        $storeCurrency = $this->getColumn()->getCurrencyCode();

        $rate = $this->_getRate($storeCurrency, $displayCurrency);
        if ($rate) {
            $this->getColumn()->setRate($rate);
            $this->getColumn()->setCurrencyCode($displayCurrency);
        }
    }
}
