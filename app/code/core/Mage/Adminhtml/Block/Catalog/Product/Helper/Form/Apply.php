<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_Catalog_Product_Helper_Form_Apply extends \Maho\Data\Form\Element\Multiselect
{
    #[\Override]
    public function getElementHtml()
    {
        $elementAttributeHtml = '';

        if ($this->getReadonly()) {
            $elementAttributeHtml = $elementAttributeHtml . ' readonly="readonly"';
        }

        if ($this->getDisabled()) {
            $elementAttributeHtml = $elementAttributeHtml . ' disabled="disabled"';
        }

        $html = '<select onchange="toggleApplyVisibility(this)"' . $elementAttributeHtml . '>'
              . '<option value="0">' . $this->getModeLabels('all') . '</option>'
              . '<option value="1" ' . ($this->getValue() == null ? '' : 'selected') . '>' . $this->getModeLabels('custom') . '</option>'
              . '</select><br><br>';

        $html .= parent::getElementHtml();
        return $html;
    }

    /**
     * Duplicate interface of \Maho\Data\Form\Element\AbstractElement::setReadonly
     *
     * @param bool $readonly
     * @param bool $useDisabled
     * @return $this
     */
    #[\Override]
    public function setReadonly($readonly, $useDisabled = false)
    {
        $this->setData('readonly', $readonly);
        $this->setData('disabled', $useDisabled);
        return $this;
    }
}
