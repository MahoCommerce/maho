<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
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
              . '</select><br /><br />';

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
