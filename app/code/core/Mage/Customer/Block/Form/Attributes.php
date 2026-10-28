<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Customer
 */

declare(strict_types=1);

/**
 * The fields of the user-defined attributes of one customer or address form.
 *
 * The template of the form renders the system attributes itself. This block adds the attributes
 * that an admin created, for the attribute set of the customer group of the entity.
 */
class Mage_Customer_Block_Form_Attributes extends Mage_Core_Block_Template
{
    protected ?Mage_Customer_Model_Form $form = null;

    #[\Override]
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('customer/form/attributes.phtml');
    }

    /**
     * Set the form code, for example customer_account_create, and the entity that the form edits.
     */
    public function setCustomerForm(string $formCode, Mage_Core_Model_Abstract $entity): static
    {
        $this->form = Mage::getModel('customer/form')
            ->setFormCode($formCode)
            ->setEntityType($entity instanceof Mage_Customer_Model_Address_Abstract ? 'customer_address' : 'customer')
            ->setEntity($entity);
        return $this;
    }

    /**
     * Return the visible user-defined attributes of the form.
     *
     * @return list<Mage_Customer_Model_Attribute>
     */
    public function getAttributes(): array
    {
        if (!$this->form) {
            return [];
        }
        $attributes = [];
        foreach ($this->form->getUserAttributes() as $attribute) {
            /** @var Mage_Customer_Model_Attribute $attribute */
            if ($attribute->getIsVisible()) {
                $attributes[] = $attribute;
            }
        }
        return $attributes;
    }

    /**
     * Return the value of an attribute: the posted value that the session kept, or the value of the entity.
     */
    public function getValue(Mage_Customer_Model_Attribute $attribute): mixed
    {
        $code = $attribute->getAttributeCode();
        $values = $this->getData('values');
        if ($values instanceof \Maho\DataObject && $values->hasData($code)) {
            return $values->getData($code);
        }
        return $this->form?->getEntity()->getData($code) ?? $attribute->getDefaultValue();
    }

    /**
     * Return the values of a select or a multiselect as a list.
     *
     * @return list<string>
     */
    public function getSelectedValues(Mage_Customer_Model_Attribute $attribute): array
    {
        $value = $this->getValue($attribute);
        if (is_array($value)) {
            return array_map(strval(...), $value);
        }
        return $value === null || $value === '' ? [] : explode(',', (string) $value);
    }

    /**
     * Return the options of a select or a multiselect, without the empty option.
     *
     * @return list<array{value: string, label: string}>
     */
    public function getOptions(Mage_Customer_Model_Attribute $attribute): array
    {
        $options = [];
        foreach ($attribute->getSource()->getAllOptions() as $option) {
            if ((string) $option['value'] !== '') {
                $options[] = ['value' => (string) $option['value'], 'label' => (string) $option['label']];
            }
        }
        return $options;
    }

    /**
     * Return the name of the input of an attribute, with the name format of the block, for example billing[%s].
     */
    public function getFieldName(Mage_Customer_Model_Attribute $attribute, string $suffix = ''): string
    {
        $format = (string) ($this->getData('name_format') ?: '%s');
        return sprintf($format, $attribute->getAttributeCode()) . $suffix;
    }

    public function getFieldId(Mage_Customer_Model_Attribute $attribute): string
    {
        return $this->getData('id_prefix') . $attribute->getAttributeCode();
    }

    public function getValidationClass(Mage_Customer_Model_Attribute $attribute): string
    {
        $classes = [];
        if ($attribute->getIsRequired()) {
            $classes[] = 'required-entry';
        }
        $rules = $attribute->getValidateRules();
        $classes[] = match ($rules['input_validation'] ?? '') {
            'email' => 'validate-email',
            'url' => 'validate-url',
            'numeric' => 'validate-number',
            'alpha' => 'validate-alpha',
            'alphanumeric' => 'validate-alphanum',
            default => '',
        };
        if (!empty($rules['max_text_length'])) {
            $classes[] = 'validate-length maximum-length-' . (int) $rules['max_text_length'];
        }
        return trim(implode(' ', $classes));
    }
}
