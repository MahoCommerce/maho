<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Eav
 */

declare(strict_types=1);

class Mage_Eav_Block_Adminhtml_Attribute_Set_Edit_Formset extends Mage_Adminhtml_Block_Widget_Form
{
    use Mage_Eav_Block_Adminhtml_EntityTypeUrlTrait;

    #[\Override]
    protected function _prepareForm()
    {
        $data = Mage::getModel('eav/entity_attribute_set')
            ->load($this->getRequest()->getParam('id'));

        $form = new \Maho\Data\Form();
        $fieldset = $form->addFieldset('set_name', ['legend' => Mage::helper('eav')->__('Edit Set Name')]);
        $fieldset->addField('attribute_set_name', 'text', [
            'label' => Mage::helper('eav')->__('Name'),
            'note' => Mage::helper('eav')->__('For internal use.'),
            'name' => 'attribute_set_name',
            'required' => true,
            'class' => 'required-entry validate-no-html-tags',
            'value' => $data->getAttributeSetName(),
        ]);

        if (!$this->getRequest()->getParam('id', false)) {
            $fieldset->addField('gotoEdit', 'hidden', [
                'name' => 'gotoEdit',
                'value' => '1',
            ]);

            $sets = Mage::getModel('eav/entity_attribute_set')
                ->getResourceCollection()
                ->setEntityTypeFilter(Mage::registry('entityType'))
                ->setOrder('attribute_set_name', 'asc')
                ->load()
                ->toOptionArray();

            $fieldset->addField('skeleton_set', 'select', [
                'label' => Mage::helper('eav')->__('Based On'),
                'name' => 'skeleton_set',
                'required' => true,
                'class' => 'required-entry',
                'values' => $sets,
            ]);
        }

        $form->setMethod('post');
        $form->setUseContainer(true);
        $form->setId('set_prop_form');
        $form->setAction($this->getUrl('*/*/save'));
        $form->setOnsubmit('return false;');
        $this->setForm($form);
        return $this;
    }

    #[\Override]
    protected function _initFormValues()
    {
        if ($this->getIsReadOnly()) {
            $fieldset = $this->getForm()->getElement('set_name');
            foreach ($fieldset->getElements() as $element) {
                $element->setDisabled(true);
            }
        }
        return $this;
    }
}
