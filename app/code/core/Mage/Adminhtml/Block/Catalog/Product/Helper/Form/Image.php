<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2021-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_Catalog_Product_Helper_Form_Image extends \Maho\Data\Form\Element\Image
{
    #[\Override]
    protected function _getUrl()
    {
        $url = false;
        if ($this->getValue()) {
            $url = Mage::getBaseUrl('media') . 'catalog/product/' . $this->getValue();
        }
        return $url;
    }

    #[\Override]
    protected function _getDeleteCheckbox()
    {
        $html = '';
        if ($attribute = $this->getEntityAttribute()) {
            if (!$attribute->getIsRequired()) {
                $html .= parent::_getDeleteCheckbox();
            } else {
                $html .= '<input value="' . $this->getValue() . '" id="' . $this->getHtmlId() . '_hidden" type="hidden" class="required-entry">';
                $html .= '<script>
                    syncOnchangeValue(\'' . $this->getHtmlId() . '\', \'' . $this->getHtmlId() . '_hidden\');
                </script>';
            }
        } else {
            $html .= parent::_getDeleteCheckbox();
        }
        return $html;
    }
}
