<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Adminhtml_Block_System_Config_Form_Fieldset extends Mage_Adminhtml_Block_Abstract implements \Maho\Data\Form\Element\Renderer\RendererInterface
{
    /**
     * Render fieldset html
     *
     * @return string
     */
    #[\Override]
    public function render(\Maho\Data\Form\Element\AbstractElement $element)
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
     * @param \Maho\Data\Form\Element\AbstractElement $element
     * @return string
     */
    protected function _getHeaderHtml($element)
    {
        if ($element->getIsNested()) {
            $html = '<tr class="nested"><td colspan="4"><div class="' . $this->_getFrontendClass($element) . '">';
        } else {
            $html = '<div class="' . $this->_getFrontendClass($element) . '">';
        }

        $html .= $this->_getHeaderTitleHtml($element);

        $html .= '<input id="' . $element->getHtmlId() . '-state" name="config_state[' . $element->getId()
            . ']" type="hidden" value="' . (int) $this->_getCollapseState($element) . '" />';
        $html .= '<fieldset class="' . $this->_getFieldsetCss($element) . '" id="' . $element->getHtmlId() . '">';
        $html .= '<legend>' . $element->getLegend() . '</legend>';

        $html .= $this->_getHeaderCommentHtml($element);

        // field label column
        $html .= '<table cellspacing="0" class="form-list"><colgroup class="label" /><colgroup class="value" />';
        if ($this->getRequest()->getParam('website') || $this->getRequest()->getParam('store')) {
            $html .= '<colgroup class="use-default" />';
        }
        $html .= '<colgroup class="scope-label" /><colgroup class="" /><tbody>';

        return $html;
    }

    /**
     * Get frontend class
     *
     * @param \Maho\Data\Form\Element\AbstractElement $element
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
     * @param null|\Maho\Data\Form\Element\AbstractElement $element
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
     * @param \Maho\Data\Form\Element\AbstractElement $element
     * @return string
     */
    protected function _getHeaderTitleHtml($element)
    {
        return '<div class="entry-edit-head collapseable" ><a id="' . $element->getHtmlId()
            . '-head" href="#" onclick="Fieldset.toggleCollapse(\'' . $element->getHtmlId() . '\', \''
            . $this->getUrl('*/*/state') . '\'); return false;">' . $element->getLegend() . '</a></div>';
    }

    /**
     * Return header comment part of html for fieldset
     *
     * @param \Maho\Data\Form\Element\AbstractElement $element
     * @return string
     */
    protected function _getHeaderCommentHtml($element)
    {
        $package = (string) ($this->getGroup($element)->mandatory_package ?? '');
        if ($package && !\Composer\InstalledVersions::isInstalled($package)) {
            $warning = "⚠️ Install <code>$package</code>";
            $comment = $element->getComment();
            $element->setComment($comment ? "$warning<br>$comment" : $warning);
        }

        return $element->getComment()
            ? '<div class="comment">' . $element->getComment() . '</div>'
            : '';
    }

    /**
     * Return full css class name for form fieldset
     *
     * @param null|\Maho\Data\Form\Element\AbstractElement $element
     * @return string
     */
    protected function _getFieldsetCss($element = null)
    {
        $configCss = (string) $this->getGroup($element)->fieldset_css;
        return 'config collapseable' . ($configCss ? ' ' . $configCss : '');
    }

    /**
     * Return footer html for fieldset
     * Add extra tooltip comments to elements
     *
     * @param \Maho\Data\Form\Element\AbstractElement $element
     * @return string
     */
    protected function _getFooterHtml($element)
    {
        $tooltipsExist = false;
        $html = '</tbody></table>';
        $html .= '</fieldset>' . $this->_getExtraJs($element, $tooltipsExist);

        if ($element->getIsNested()) {
            $html .= '</div></td></tr>';
        } else {
            $html .= '</div>';
        }
        return $html;
    }

    /**
     * Return js code for fieldset:
     * - observe fieldset rows;
     * - apply collapse;
     *
     * @param \Maho\Data\Form\Element\AbstractElement $element
     * @param bool $tooltipsExist Init tooltips observer or not
     * @return string
     */
    protected function _getExtraJs($element, $tooltipsExist = false)
    {
        $id = $element->getHtmlId();
        $js = "Fieldset.applyCollapse('{$id}');";
        return Mage::helper('adminhtml/js')->getScript($js);
    }

    /**
     * Collapsed or expanded fieldset when page loaded?
     *
     * @param \Maho\Data\Form\Element\AbstractElement $element
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
