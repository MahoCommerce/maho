<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Config form fieldset renderer
 *
 * @package    Mage_Adminhtml
 */
class Mage_Adminhtml_Block_System_Config_Form_Fieldset extends Mage_Adminhtml_Block_Abstract implements Varien_Data_Form_Element_Renderer_Interface
{
    /**
     * Render fieldset html
     *
     * @return string
     */
    #[\Override]
    public function render(Varien_Data_Form_Element_Abstract $element)
    {
        $this->setElement($element);
        $html = $this->_getHeaderHtml($element);

        foreach ($element->getSortedElements() as $field) {
            $html .= $field->toHtml();
        }

        $html .= $this->_getFooterHtml($element);

        return $html;
    }

    /**
     * Return header html for fieldset
     *
     * @param Varien_Data_Form_Element_Abstract $element
     * @return string
     */
    protected function _getHeaderHtml($element)
    {
        $id = $element->getId();
        $isOpen = (int) $this->_getCollapseState($element) ? ' open' : '';

        $columns = [
            '<colgroup class="label" />',
            '<colgroup class="value" />',
            '<colgroup class="scope-label" />',
            '<colgroup class="" />',
        ];
        if ($this->getRequest()->getParam('website') || $this->getRequest()->getParam('store')) {
            array_splice($columns, 2, 0, '<colgroup class="use-default" />');
        }
        $columns = implode('', $columns);

        $html = <<<HTML
            <details class="{$this->_getFrontendClass($element)} accordion"{$isOpen}>
                {$this->_getHeaderTitleHtml($element)}
                <input id="{$id}-state" name="config_state[{$id}]" type="hidden">
                <fieldset id="{$id}" class="{$this->_getFieldsetCss($element)}">
                    <legend>{$element->getLegend()}</legend>
                    {$this->_getHeaderCommentHtml($element)}
                    <table cellspacing="0" class="form-list">
                        {$columns}
                        <tbody>
        HTML;

        if ($element->getIsNested()) {
            $html = '<tr class="nested"><td colspan="4">' . $html;
        }

        return $html;
    }

    /**
     * Get frontend class
     *
     * @param Varien_Data_Form_Element_Abstract $element
     * @return string
     */
    protected function _getFrontendClass($element)
    {
        $frontendClass = (string) $this->getGroup($element)->frontend_class;
        return 'section-config' . (empty($frontendClass) ? '' : (' ' . $frontendClass));
    }

    /**
     * Get group xml data of the element
     *
     * @param null|Varien_Data_Form_Element_Abstract $element
     * @return Mage_Core_Model_Config_Element
     */
    public function getGroup($element = null)
    {
        if (is_null($element)) {
            $element = $this->getElement();
        }
        if ($element && $element->getGroup() instanceof Mage_Core_Model_Config_Element) {
            return $element->getGroup();
        }

        return new Mage_Core_Model_Config_Element('<config/>');
    }

    /**
     * Return header title part of html for fieldset
     *
     * @param Varien_Data_Form_Element_Abstract $element
     * @return string
     */
    protected function _getHeaderTitleHtml($element)
    {
        return '<summary><h4>' . $element->getLegend() . '</h4></summary>';
    }

    /**
     * Return header comment part of html for fieldset
     *
     * @param Varien_Data_Form_Element_Abstract $element
     * @return string
     */
    protected function _getHeaderCommentHtml($element)
    {
        if ($element->getComment()) {
            return '<div class="comment">' . $element->getComment() . '</div>';
        }
        return '';
    }

    /**
     * Return full css class name for form fieldset
     *
     * @param null|Varien_Data_Form_Element_Abstract $element
     * @return string
     */
    protected function _getFieldsetCss($element = null)
    {
        $configCss = (string) $this->getGroup($element)->fieldset_css;
        return 'config' . ($configCss ? " $configCss" : '');
    }

    /**
     * Return footer html for fieldset
     *
     * @param Varien_Data_Form_Element_Abstract $element
     * @return string
     */
    protected function _getFooterHtml($element)
    {
        $html = '</tbody></table></fieldset></details>';
        if ($element->getIsNested()) {
            $html .= '</td></tr>';
        }

        return $html;
    }

    /**
     * Return js code for fieldset
     *
     * @param Varien_Data_Form_Element_Abstract $element
     * @param bool $tooltipsExist Init tooltips observer or not
     * @return string
     * @deprecated
     */
    protected function _getExtraJs($element, $tooltipsExist = false)
    {
        return '';
    }

    /**
     * Collapsed or expanded fieldset when page loaded?
     *
     * @param Varien_Data_Form_Element_Abstract $element
     * @return int|false
     */
    protected function _getCollapseState($element)
    {
        if ($element->getExpanded() !== null) {
            return 1;
        }
        $extra = Mage::getSingleton('admin/session')->getUser()->getExtra();
        return $extra['configState'][$element->getId()] ?? false;
    }
}
