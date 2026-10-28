<?php

/**
 * A tab with the user-defined attributes of one group of the set of a blog post or blog category.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Blog
 */

declare(strict_types=1);

class Maho_Blog_Block_Adminhtml_Edit_Tab_Attributes extends Mage_Adminhtml_Block_Widget_Form implements Mage_Adminhtml_Block_Widget_Tab_Interface
{
    public function getEntity(): Mage_Core_Model_Abstract
    {
        return $this->getData('entity');
    }

    public function getGroup(): Mage_Eav_Model_Entity_Attribute_Group
    {
        return $this->getData('group');
    }

    /**
     * @return list<Maho_Blog_Model_Resource_Eav_Attribute>
     */
    public function getAttributes(): array
    {
        return $this->getData('attributes') ?? [];
    }

    #[\Override]
    protected function _prepareForm()
    {
        $form = new \Maho\Data\Form();
        $form->setHtmlIdPrefix('group_' . $this->getGroup()->getId() . '_');
        if ($this->getData('field_name_suffix')) {
            $form->setFieldNameSuffix((string) $this->getData('field_name_suffix'));
        }
        $fieldset = $form->addFieldset('group_fields_' . $this->getGroup()->getId(), [
            'legend' => $this->getTabLabel(),
            'class' => 'fieldset-wide',
        ]);

        $editors = [];
        $others = [];
        foreach ($this->getAttributes() as $attribute) {
            if ($attribute->getFrontendInput() === 'textarea' && $attribute->getData('is_wysiwyg_enabled')) {
                $editors[] = $attribute;
            } else {
                $others[] = $attribute;
            }
        }
        $this->_setFieldset($others, $fieldset);
        foreach ($editors as $attribute) {
            $fieldset->addField((string) $attribute->getAttributeCode(), 'editor', [
                'name' => $attribute->getAttributeCode(),
                'label' => $attribute->getFrontend()->getLabel(),
                'required' => (bool) $attribute->getIsRequired(),
                'style' => 'height:20em;',
                'config' => Mage::getSingleton('cms/wysiwyg_config')->getConfig(),
            ]);
        }

        $form->setValues($this->getEntity()->getData());
        $this->setForm($form);
        return parent::_prepareForm();
    }

    #[\Override]
    public function getTabLabel()
    {
        return Mage::helper('blog')->__((string) $this->getGroup()->getAttributeGroupName());
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
