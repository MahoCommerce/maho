<?php

/**
 * The tab of the custom attributes of a CMS page.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

declare(strict_types=1);

class Mage_Adminhtml_Block_Cms_Page_Edit_Tab_Attributes extends Mage_Adminhtml_Block_Widget_Form implements Mage_Adminhtml_Block_Widget_Tab_Interface
{
    /**
     * @return array<string, Mage_Eav_Model_Entity_Attribute_Abstract>
     */
    public function getAttributes(): array
    {
        return Mage::getResourceSingleton('cms/page')->getCustomAttributes();
    }

    #[\Override]
    protected function _prepareForm()
    {
        $form = new \Maho\Data\Form();
        $form->setHtmlIdPrefix('page_');
        $fieldset = $form->addFieldset('attributes_fieldset', [
            'legend' => $this->getTabLabel(),
            'class' => 'fieldset-wide',
        ]);
        $this->_setFieldset($this->getAttributes(), $fieldset);

        if (!Mage::getSingleton('admin/session')->isAllowed('cms/page/save')) {
            foreach ($fieldset->getElements() as $element) {
                $element->setDisabled(true);
            }
        }

        /** @var Mage_Cms_Model_Page $page */
        $page = Mage::registry('cms_page');
        $values = $page->getData();
        if (!$page->getId()) {
            foreach ($this->getAttributes() as $code => $attribute) {
                $values[$code] ??= $attribute->getDefaultValue();
            }
        }
        $form->setValues($values);
        $this->setForm($form);
        return parent::_prepareForm();
    }

    #[\Override]
    public function getTabLabel()
    {
        return Mage::helper('cms')->__('Attributes');
    }

    #[\Override]
    public function getTabTitle()
    {
        return $this->getTabLabel();
    }

    #[\Override]
    public function canShowTab()
    {
        return $this->getAttributes() !== [];
    }

    #[\Override]
    public function isHidden()
    {
        return false;
    }
}
