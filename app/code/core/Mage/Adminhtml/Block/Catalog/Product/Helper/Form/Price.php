<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_Catalog_Product_Helper_Form_Price extends \Maho\Data\Form\Element\Text
{
    /**
     * @param array $attributes
     */
    public function __construct($attributes = [])
    {
        parent::__construct($attributes);
        $this->addClass('validate-zero-or-greater');
    }

    /**
     * @return string
     * @throws Mage_Core_Model_Store_Exception
     */
    #[\Override]
    public function getAfterElementHtml()
    {
        $html = parent::getAfterElementHtml();
        /**
         * getEntityAttribute - use __call
         */
        $addJsObserver = false;
        if ($attribute = $this->getEntityAttribute()) {
            if (!($storeId = $attribute->getStoreId())) {
                $storeId = $this->getForm()->getDataObject()->getStoreId();
            }
            $store = Mage::app()->getStore($storeId);
            $html .= '<strong>[' . (string) $store->getBaseCurrencyCode() . ']</strong>';
            if (Mage::helper('tax')->priceIncludesTax($store)) {
                if ($attribute->getAttributeCode() !== 'cost') {
                    $addJsObserver = true;
                    $html .= ' <strong>[' . Mage::helper('tax')->__('Inc. Tax') . '<span id="dynamic-tax-' . $attribute->getAttributeCode() . '"></span>]</strong>';
                }
            }
        }
        if ($addJsObserver) {
            $html .= $this->_getTaxObservingCode($attribute);
        }

        return $html;
    }

    /**
     * @param $attribute
     * @return string
     */
    protected function _getTaxObservingCode($attribute)
    {
        $spanId = "dynamic-tax-{$attribute->getAttributeCode()}";

        return "<script>if (dynamicTaxes == undefined) var dynamicTaxes = new Array(); dynamicTaxes[dynamicTaxes.length]='{$attribute->getAttributeCode()}'</script>";
    }

    /**
     * @param null $index deprecated
     * @return string|null
     */
    #[\Override]
    public function getEscapedValue($index = null)
    {
        $value = $this->getValue();

        if (!is_numeric($value)) {
            return null;
        }

        return number_format((float) $value, 2, null, '');
    }
}
