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
 *
 * A template can render the block two times: with the placement top at the start of the form, and with the
 * placement bottom after the standard fields. A field goes to the top when its sort order is lower than the
 * sort order of each standard field of the form. A block without a placement renders all the fields.
 */
class Mage_Customer_Block_Form_Attributes extends Mage_Core_Block_Template
{
    public const PLACEMENT_TOP = 'top';
    public const PLACEMENT_BOTTOM = 'bottom';

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
     * Set the part of the fields that the block renders: PLACEMENT_TOP, PLACEMENT_BOTTOM, or null for all the fields.
     */
    public function setPlacement(?string $placement): static
    {
        return $this->setData('placement', $placement);
    }

    public function getPlacement(): ?string
    {
        $value = $this->getData('placement');
        return $value === null ? null : (string) $value;
    }

    /**
     * Return the visible user-defined attributes of the form, in the placement of the block.
     *
     * @return list<Mage_Customer_Model_Attribute>
     */
    public function getAttributes(): array
    {
        if (!$this->form) {
            return [];
        }
        $placement = $this->getPlacement();
        $firstStandard = $placement === null ? null : $this->getFirstStandardSortOrder();
        $attributes = [];
        foreach ($this->form->getUserAttributes() as $attribute) {
            /** @var Mage_Customer_Model_Attribute $attribute */
            if (!$attribute->getIsVisible()) {
                continue;
            }
            if ($placement !== null) {
                $isTop = $firstStandard !== null && (int) $attribute->getSortOrder() < $firstStandard;
                if ($isTop !== ($placement === self::PLACEMENT_TOP)) {
                    continue;
                }
            }
            $attributes[] = $attribute;
        }
        return $attributes;
    }

    /**
     * Return the lowest sort order of the visible standard fields of the form, or null when the form has none.
     */
    protected function getFirstStandardSortOrder(): ?int
    {
        $sortOrders = [];
        foreach ($this->form?->getSystemAttributes() ?? [] as $attribute) {
            /** @var Mage_Customer_Model_Attribute $attribute */
            if ($attribute->getIsVisible()) {
                $sortOrders[] = (int) $attribute->getSortOrder();
            }
        }
        return $sortOrders ? min($sortOrders) : null;
    }

    /**
     * Return the visible user-defined attributes of the form by group of the attribute set of the
     * form. The first part has no label: its fields join the fields that the template shows itself.
     * Each other group is a fieldset with its label in the current store view.
     *
     * @return list<array{id: ?int, label: ?string, attributes: list<Mage_Customer_Model_Attribute>}>
     */
    public function getAttributeGroups(): array
    {
        $attributes = $this->getAttributes();
        if (!$attributes) {
            return [];
        }
        return Mage::helper('eav')->groupAttributesBySet($attributes, $this->form?->getAttributeSetId());
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

    /**
     * Return the configuration of FieldDependenceController for the visible fields of the form, or null when no field
     * has a condition: the conditions that show the fields, and the conditions that make optional fields required. The
     * fields are the fields of this block and the standard fields that the template of the form shows itself, such as
     * company and vat_id. A field that a condition reads and that the form does not have takes the value of the entity.
     * The block with the placement top returns null, because the controller of the other block covers the whole form.
     */
    public function getDependenceJson(): ?string
    {
        if ($this->getPlacement() === self::PLACEMENT_TOP) {
            return null;
        }
        $depends = [];
        $required = [];
        $fields = [];
        foreach ($this->form?->getAttributes() ?? [] as $attribute) {
            /** @var Mage_Customer_Model_Attribute $attribute */
            if (!$attribute->getIsVisible()) {
                continue;
            }
            $code = (string) $attribute->getAttributeCode();
            if ($condition = $attribute->getDependsOn()) {
                $depends[$code] = $condition;
                $fields = [...$fields, ...\Maho\Data\Form\Condition::fields($condition)];
            }
            // A field that the Required setting already requires needs no condition to require it
            if (!$attribute->getIsRequired() && ($condition = $attribute->getRequiredWhen())) {
                $required[$code] = $condition;
                $fields = [...$fields, ...\Maho\Data\Form\Condition::fields($condition)];
            }
        }
        if (!$depends && !$required) {
            return null;
        }
        $values = [];
        $entity = $this->form?->getEntity();
        $data = $entity ? Mage::helper('eav')->getConditionData($entity, $fields) : [];
        foreach (array_unique($fields) as $field) {
            $value = $data[$field] ?? null;
            // A new customer gets the website of the store when it is saved, see Mage_Customer_Model_Attribute
            if ($field === 'website_id' && $value === null && $entity instanceof Mage_Customer_Model_Customer) {
                $value = Mage::app()->getStore()->getWebsiteId();
            }
            if ($value !== null && $value !== '' && $value !== false) {
                $values[$field] = \Maho\Data\Form\Condition::fieldValues($value);
            }
        }
        return Mage::helper('core')->jsonEncode([
            'depends' => (object) $depends,
            'required' => (object) $required,
            'nameFormat' => (string) ($this->getData('name_format') ?: '%s'),
            'values' => (object) $values,
        ]);
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
