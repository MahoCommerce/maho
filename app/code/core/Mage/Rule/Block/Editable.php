<?php

/**
 * Maho
 *
 * @package    Mage_Rule
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Rule_Block_Editable extends Mage_Core_Block_Abstract implements \Maho\Data\Form\Element\Renderer\RendererInterface
{
    /**
     * @see \Maho\Data\Form\Element\Renderer\RendererInterface::render()
     * @return string
     */
    #[\Override]
    public function render(\Maho\Data\Form\Element\AbstractElement $element)
    {
        $element->addClass('element-value-changer');
        $valueName = $element->getValueName();

        if ($valueName === '' || $valueName === null) {
            $valueName = '...';
        }

        if ($element->getShowAsText()) {
            $html = ' <input type="hidden" class="hidden" id="'
                . $element->getHtmlId()
                . '" name="' . $element->getName()
                . '" value="' . $element->getValue() . '"/> '
                . htmlspecialchars($valueName) . '&nbsp;';
        } else {
            $html = ' <span class="rule-param"'
                . ($element->getParamId() ? ' id="' . $element->getParamId() . '"' : '') . '>'
                . '<a href="javascript:void(0)" class="label">';

            $translate = Mage::getSingleton('core/translate_inline');

            $html .= $translate->isAllowed()
                ? Mage::helper('core')->escapeHtml($valueName)
                : Mage::helper('core')->escapeHtml(Mage::helper('core/string')->truncate($valueName, 100, '...'));

            $html .= '</a><span class="element"> ' . $element->getElementHtml();

            if ($element->getExplicitApply()) {
                $html .= ' <a href="javascript:void(0)" class="rule-param-apply" title="'
                    . Mage::helper('core')->quoteEscape($this->__('Apply'))
                    . '">' . $this->getIconSvg('circle-check') . '</a> ';
            }

            $html .= '</span></span>&nbsp;';
        }

        return $html;
    }
}
