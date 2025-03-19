<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Button widget
 *
 * @package    Mage_Adminhtml
 */
class Mage_Adminhtml_Block_Widget_Button extends Mage_Adminhtml_Block_Widget
{
    /**
     * Return type of button, i.e. 'submit', 'button'.
     *
     * @return string
     */
    public function getType()
    {
        return $this->getData('type') ?? 'button';
    }

    /**
     * Return onclick attribute
     *
     * @return ?string
     */
    public function getOnClick()
    {
        return $this->getData('on_click') ?? $this->getData('onclick');
    }

    #[\Override]
    protected function _toHtml()
    {
        $attrObj = new Varien_Object();
        $attrObj->setType($this->getType());
        if ($this->getId()) {
            $attrObj->setId($this->getId());
        }
        if ($this->getElementName()) {
            $attrObj->setName($this->getElementName());
        }
        if ($this->getTitle()) {
            $attrObj->setTitle($this->getTitle());
        } elseif ($this->getLabel()) {
            $attrObj->setTitle($this->getLabel());
        }
        if ($this->getOnClick()) {
            $attrObj->setOnclick($this->getOnClick());
        }
        if ($this->getStyle()) {
            $attrObj->setStyle($this->getStyle());
        }
        if ($this->getValue()) {
            $attrObj->setValue($this->getValue());
        }
        if ($this->getDisabled()) {
            $attrObj->setDisabled('disabled');
        }

        $classes = ['scalable'];
        if ($this->getClass()) {
            $classes[] = $this->getClass();
        }
        if ($this->getDisabled()) {
            $classes[] = 'disabled';
        }
        $attrObj->setClass(implode(' ', $classes));

        if ($this->getIcon()) {
            $icon = $this->getIconSvg($this->getIcon(), $this->getIconVariant());
        } else {
            $icon = '';
        }
        $html = $this->getBeforeHtml();
        $html .= "<button {$attrObj->serialize()}>{$icon}{$this->getLabel()}</button>";
        $html .= $this->getAfterHtml();
        return $html;
    }
}
