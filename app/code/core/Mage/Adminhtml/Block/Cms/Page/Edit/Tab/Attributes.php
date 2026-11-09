<?php

/**
 * A tab with the custom attributes of one group of the attribute set of CMS pages.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

declare(strict_types=1);

class Mage_Adminhtml_Block_Cms_Page_Edit_Tab_Attributes extends Mage_Adminhtml_Block_Widget_Form implements Mage_Adminhtml_Block_Widget_Tab_Interface
{
    use Mage_Adminhtml_Block_Cms_Page_Edit_AttributeFieldsTrait;

    public function getGroup(): Mage_Eav_Model_Entity_Attribute_Group
    {
        return $this->getData('group');
    }

    /**
     * @return list<Mage_Eav_Model_Entity_Attribute_Abstract>
     */
    public function getAttributes(): array
    {
        return $this->getData('attributes') ?? [];
    }

    #[\Override]
    protected function _prepareForm()
    {
        /** @var Mage_Cms_Model_Page $page */
        $page = Mage::registry('cms_page');
        $form = new \Maho\Data\Form();
        $form->setHtmlIdPrefix('page_');
        $fieldset = $form->addFieldset('group_fields_' . $this->getGroup()->getId(), [
            'legend' => $this->getTabLabel(),
            'class' => 'fieldset-wide',
        ]);
        $this->addAttributeFields($fieldset, $this->getAttributes(), $page);
        $form->setValues($page->getData());
        $this->setForm($form);
        return parent::_prepareForm();
    }

    #[\Override]
    public function getTabLabel()
    {
        return (string) $this->getGroup()->getAttributeGroupName();
    }

    #[\Override]
    public function getTabTitle()
    {
        return $this->getTabLabel();
    }

    #[\Override]
    public function canShowTab()
    {
        return true;
    }

    #[\Override]
    public function isHidden()
    {
        return false;
    }
}
