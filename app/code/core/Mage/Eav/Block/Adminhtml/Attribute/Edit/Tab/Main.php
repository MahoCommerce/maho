<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Eav
 */

declare(strict_types=1);

/**
 * The properties tab of an attribute of any entity type.
 *
 * A subclass adds the fields of one entity type in _prepareEntityFields().
 */
class Mage_Eav_Block_Adminhtml_Attribute_Edit_Tab_Main extends Mage_Eav_Block_Adminhtml_Attribute_Edit_Main_Abstract
{
    #[\Override]
    protected function _prepareForm()
    {
        parent::_prepareForm();
        $attribute = $this->getAttributeObject();
        $form = $this->getForm();
        $helper = Mage::helper('eav');

        $inputTypes = [];
        foreach ($attribute->getInputTypes() as $value => $type) {
            $inputTypes[] = ['value' => $value, 'label' => $type['label']];
        }
        $input = $form->getElement('frontend_input');
        $input->setLabel($helper->__('Input Type'))
            ->setTitle($helper->__('Input Type'))
            ->setValues($inputTypes);
        if ($attribute->getId() && !isset($attribute->getInputTypes()[$attribute->getFrontendInput()])) {
            $input->setValues(array_merge($inputTypes, [[
                'value' => $attribute->getFrontendInput(),
                'label' => $attribute->getFrontendInput(),
            ]]));
        }

        $form->getElement('is_unique')
            ->setTitle($helper->__('Unique Value'))
            ->setNote($helper->__('No two records can have the same value.'));
        $form->getElement('frontend_class')
            ->setLabel($helper->__('Input Validation'))
            ->setTitle($helper->__('Input Validation'));

        /** @var \Maho\Data\Form\Element\Fieldset $fieldset */
        $fieldset = $form->getElement('base_fieldset');
        $this->_prepareEntityFields($form, $fieldset);

        Mage::dispatchEvent('adminhtml_eav_attribute_edit_prepare_form', [
            'form' => $form,
            'attribute' => $attribute,
            'entity_type_code' => $attribute->getEntityType()->getEntityTypeCode(),
        ]);

        return $this;
    }

    /**
     * Add the fields of the entity type to the form.
     */
    protected function _prepareEntityFields(\Maho\Data\Form $form, \Maho\Data\Form\Element\Fieldset $fieldset): void {}
}
