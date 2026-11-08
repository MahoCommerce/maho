<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

declare(strict_types=1);

/**
 * The properties tab of a customer or customer address attribute.
 *
 * @method Mage_Customer_Model_Attribute getAttributeObject()
 */
class Mage_Adminhtml_Block_Customer_Attribute_Edit_Tab_Main extends Mage_Eav_Block_Adminhtml_Attribute_Edit_Tab_Main
{
    #[\Override]
    protected function _prepareEntityFields(\Maho\Data\Form $form, \Maho\Data\Form\Element\Fieldset $fieldset): void
    {
        $helper = Mage::helper('customer');
        $attribute = $this->getAttributeObject();
        $isAddress = $attribute->getEntityType()->getEntityTypeCode() === 'customer_address';
        $rules = $attribute->getValidateRules();

        $forms = [];
        foreach ($attribute->getFormCodes() as $code => $label) {
            $forms[] = ['value' => $code, 'label' => $label];
        }
        $usedInForms = $attribute->getId()
            ? $attribute->getUsedInForms()
            : [$isAddress ? 'adminhtml_customer_address' : 'adminhtml_customer'];

        $front = $form->addFieldset('front_fieldset', ['legend' => $helper->__('Forms')]);
        $front->addField('used_in_forms', 'multiselect', [
            'name' => 'used_in_forms[]',
            'label' => $helper->__('Used in Forms'),
            'title' => $helper->__('Used in Forms'),
            'note' => $helper->__('The customer group of the customer decides the attribute set. The form shows the attribute only when it is in that set.'),
            'values' => $forms,
            'value' => $usedInForms,
            'size' => count($forms),
        ]);
        $fieldset->removeField('is_required');
        $front->addField('default_mode', 'select', $this->getDefaultModeConfig($attribute));
        $front->addField('sort_order', 'text', [
            'name' => 'sort_order',
            'label' => $helper->__('Sort Order'),
            'title' => $helper->__('Sort Order'),
            'note' => $helper->__('The order of the field in the forms.'),
            'class' => 'validate-digits',
        ]);
        if ($isAddress) {
            $front->addField('multiline_count', 'text', [
                'name' => 'multiline_count',
                'label' => $helper->__('Lines Count'),
                'title' => $helper->__('Lines Count'),
                'class' => 'validate-digits',
                'value' => $attribute->getId() ? null : 2,
            ]);
        }

        $validation = $form->addFieldset('validation_fieldset', ['legend' => $helper->__('Input Rules')]);
        $validation->addField('input_filter', 'select', [
            'name' => 'input_filter',
            'label' => $helper->__('Input Filter'),
            'title' => $helper->__('Input Filter'),
            'values' => [
                ['value' => '', 'label' => $helper->__('None')],
                ['value' => 'striptags', 'label' => $helper->__('Strip HTML Tags')],
                ['value' => 'escapehtml', 'label' => $helper->__('Escape HTML Entities')],
            ],
        ]);
        $validation->addField('min_text_length', 'text', [
            'name' => 'min_text_length',
            'label' => $helper->__('Minimum Text Length'),
            'title' => $helper->__('Minimum Text Length'),
            'class' => 'validate-digits',
            'value' => $rules['min_text_length'] ?? '',
        ]);
        $validation->addField('max_text_length', 'text', [
            'name' => 'max_text_length',
            'label' => $helper->__('Maximum Text Length'),
            'title' => $helper->__('Maximum Text Length'),
            'class' => 'validate-digits',
            'value' => $rules['max_text_length'] ?? '',
        ]);
        $validation->addField('input_validation', 'select', [
            'name' => 'input_validation',
            'label' => $helper->__('Value Format'),
            'title' => $helper->__('Value Format'),
            'values' => [
                ['value' => '', 'label' => $helper->__('Any')],
                ['value' => 'alphanumeric', 'label' => $helper->__('Letters and Numbers')],
                ['value' => 'alpha', 'label' => $helper->__('Letters')],
                ['value' => 'numeric', 'label' => $helper->__('Numbers')],
                ['value' => 'email', 'label' => $helper->__('Email')],
                ['value' => 'url', 'label' => $helper->__('URL')],
            ],
            'value' => $rules['input_validation'] ?? '',
        ]);

        $this->_prepareWebsiteFields($form, $attribute);
    }

    /**
     * Return the field that replaces Show in Forms and Values Required: hidden, optional or required
     * in every form.
     *
     * @return array<string, mixed>
     */
    protected function getDefaultModeConfig(Mage_Customer_Model_Attribute $attribute): array
    {
        $helper = Mage::helper('customer');
        $modes = [
            'hidden' => $helper->__('Hidden'),
            'optional' => $helper->__('Optional'),
            'required' => $helper->__('Required'),
        ];
        $options = [];
        foreach ($modes as $value => $label) {
            $options[] = ['value' => $value, 'label' => $label];
        }
        $notes = [$helper->__('Hidden: no form shows the field. Optional: the customer can leave it empty. Required: the customer must fill it in.')];
        if ($this->hasWebsiteFields($attribute)) {
            $notes[] = $helper->__('The Websites section can change it for one website.');
        }
        return [
            'name' => 'default_mode',
            'label' => $helper->__('Default'),
            'title' => $helper->__('Default'),
            'note' => implode(' ', $notes),
            'values' => $options,
            'value' => $attribute->getDefaultWebsiteMode(),
        ];
    }

    /**
     * A store with one website and no saved value gets no Websites section, because the default
     * already decides.
     */
    protected function hasWebsiteFields(Mage_Customer_Model_Attribute $attribute): bool
    {
        $websites = Mage::app()->getWebsites();
        if (count($websites) > 1) {
            return true;
        }
        if (!$websites || !$attribute->getId()) {
            return false;
        }
        $values = $attribute->getResource()->getWebsiteValues((int) $attribute->getId());
        return array_filter($values, fn(array $value): bool => Mage_Customer_Model_Attribute::getWebsiteModeOverride($value) !== '') !== [];
    }

    /**
     * Add the website values: each website can show, hide or require the attribute.
     */
    protected function _prepareWebsiteFields(\Maho\Data\Form $form, Mage_Customer_Model_Attribute $attribute): void
    {
        if (!$this->hasWebsiteFields($attribute)) {
            return;
        }
        $values = $attribute->getId() ? $attribute->getResource()->getWebsiteValues((int) $attribute->getId()) : [];
        $helper = Mage::helper('customer');
        $modes = [
            'hidden' => $helper->__('Hidden'),
            'optional' => $helper->__('Optional'),
            'required' => $helper->__('Required'),
        ];
        $options = [['value' => '', 'label' => $helper->__('Use Default (%s)', $modes[$attribute->getDefaultWebsiteMode()])]];
        foreach ($modes as $value => $label) {
            $options[] = ['value' => $value, 'label' => $label];
        }

        $fieldset = $form->addFieldset('website_fieldset', [
            'legend' => $helper->__('Websites'),
            'comment' => $helper->__('Change the default for one website. Use Default follows the Default field.'),
        ]);
        foreach (Mage::app()->getWebsites() as $website) {
            $id = (int) $website->getId();
            $fieldset->addField("website_{$id}", 'select', [
                'name' => "website_values[{$id}]",
                'label' => $website->getName(),
                'values' => $options,
                'value' => Mage_Customer_Model_Attribute::getWebsiteModeOverride($values[$id] ?? null),
                'class' => 'website-mode',
            ]);
        }
    }
}
