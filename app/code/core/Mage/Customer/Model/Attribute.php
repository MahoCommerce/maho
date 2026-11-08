<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Customer
 */

declare(strict_types=1);

/**
 * Customer attribute model
 *
 * @package    Mage_Customer
 *
 * @method Mage_Customer_Model_Resource_Attribute _getResource()
 * @method Mage_Customer_Model_Resource_Attribute getResource()
 * @method Mage_Customer_Model_Resource_Attribute_Collection getCollection()
 */

class Mage_Customer_Model_Attribute extends Mage_Eav_Model_Attribute
{
    /**
     * Name of the module
     */
    public const MODULE_NAME = 'Mage_Customer';

    /**
     * Prefix of model events names
     *
     * @var string
     */
    #[\Override]
    protected $_eventPrefix = 'customer_entity_attribute';

    /**
     * Prefix of model events object
     *
     * @var string
     */
    #[\Override]
    protected $_eventObject = 'attribute';

    /**
     * Init resource model
     */
    #[\Override]
    protected function _construct()
    {
        $this->_init('customer/attribute');
    }

    #[\Override]
    public function getAdminPageSettings(): ?Mage_Eav_Model_Entity_Attribute_AdminPageSettings
    {
        $helper = Mage::helper('customer');
        return match ($this->getEntityType()->getEntityTypeCode()) {
            'customer' => new Mage_Eav_Model_Entity_Attribute_AdminPageSettings(
                acl: 'system/attributes/customer',
                title: $helper->__('Customer Attributes'),
                setsTitle: $helper->__('Customer Attribute Sets'),
                mainTab: 'adminhtml/customer_attribute_edit_tab_main',
                setsUserDefinedOnly: true,
                addToDefaultSet: true,
            ),
            'customer_address' => new Mage_Eav_Model_Entity_Attribute_AdminPageSettings(
                acl: 'system/attributes/customer_address',
                title: $helper->__('Customer Address Attributes'),
                setsTitle: $helper->__('Customer Address Attribute Sets'),
                mainTab: 'adminhtml/customer_attribute_edit_tab_main',
                setsUserDefinedOnly: true,
                addToDefaultSet: true,
            ),
            default => null,
        };
    }

    /**
     * Explain that the customer group picks the set, name the groups that use $set, and say how the
     * groups of $set divide the forms.
     */
    #[\Override]
    public function getSetNote(Mage_Eav_Model_Entity_Attribute_Set $set): ?string
    {
        $column = $this->getGroupSetColumn();
        if ($column === null) {
            return null;
        }
        $groups = Mage::getResourceModel('customer/group_collection');
        if ((int) $set->getId() === (int) $this->getEntityType()->getDefaultAttributeSetId()) {
            // A group without a set uses the default set
            $groups->addFieldToFilter($column, [['eq' => (int) $set->getId()], ['null' => true]]);
        } else {
            $groups->addFieldToFilter($column, (int) $set->getId());
        }
        $names = array_map(strval(...), $groups->getColumnValues('customer_group_code'));

        $helper = Mage::helper('customer');
        $lines = [
            $helper->__('The customer group of a customer picks the set. Select the set of each group in Customers > Customer Groups. A form shows an attribute only to the customer groups whose set holds it.')
            . ' ' . ($names
                ? $helper->__('Customer groups that use this set: %s.', implode(', ', $names))
                : $helper->__('No customer group uses this set.')),
        ];
        $firstGroup = Mage::getResourceModel('eav/entity_attribute_group_collection')
            ->setAttributeSetFilter((int) $set->getId())
            ->setSortOrder()
            ->getFirstItem();
        if ($firstGroup->getId()) {
            $lines[] = $helper->__('The fields of the first group, %s, join the other fields of the form with no title. Each other group is a part of the form, with the name of the group as its title.', (string) $firstGroup->getAttributeGroupName());
        }
        return implode("\n", $lines);
    }

    /**
     * The groups of $set move to the default set. The values of the customers stay, because the
     * rows of customers and addresses do not hold a set.
     */
    #[\Override]
    public function getSetDeleteQuestion(Mage_Eav_Model_Entity_Attribute_Set $set): string
    {
        if ($this->getGroupSetColumn() === null) {
            return parent::getSetDeleteQuestion($set);
        }
        return Mage::helper('customer')->__('The customer groups of this set will use the default set. The values of the customers stay. Delete the set?');
    }

    /**
     * The forms of the customer groups of $set stop showing $attributes. The values of the customers
     * stay, because the rows of customers and addresses do not hold a set.
     */
    #[\Override]
    public function getSetRemoveQuestion(Mage_Eav_Model_Entity_Attribute_Set $set, array $attributes): ?string
    {
        if ($this->getGroupSetColumn() === null || !$attributes) {
            return parent::getSetRemoveQuestion($set, $attributes);
        }
        return Mage::helper('customer')->__('The forms stop showing these fields to the customer groups of this set: %s. The values of the customers stay. Save the set?', self::getQuestionLabels($attributes));
    }

    /**
     * Name the forms, the customer groups and the websites that show this attribute, because
     * the three settings together decide if a customer sees the field.
     */
    #[\Override]
    public function getAttributeSummary(): ?array
    {
        $column = $this->getGroupSetColumn();
        if ($column === null || !$this->getId()) {
            return null;
        }
        $helper = Mage::helper('customer');

        $usedInForms = (array) $this->getUsedInForms();
        $forms = [];
        foreach ($this->getFormCodes() as $code => $label) {
            $forms[] = ['label' => $label, 'shown' => in_array($code, $usedInForms, true)];
        }
        $rows = [[
            'label' => $helper->__('Forms'),
            'items' => self::sortShownFirst($forms),
            'note' => in_array(true, array_column($forms, 'shown'), true)
                ? null
                : $helper->__('No form shows this attribute. Select the forms in Used in Forms.'),
        ]];

        $adapter = $this->getResource()->getReadConnection();
        $setIds = array_map(intval(...), $adapter->fetchCol(
            $adapter->select()
                ->from($this->getResource()->getTable('eav/entity_attribute'), ['attribute_set_id'])
                ->where('attribute_id = ?', (int) $this->getId()),
        ));
        $defaultSetId = (int) $this->getEntityType()->getDefaultAttributeSetId();
        $groups = [];
        foreach (Mage::getResourceModel('customer/group_collection') as $group) {
            $setId = (int) $group->getData($column) ?: $defaultSetId;
            $groups[] = ['label' => (string) $group->getCustomerGroupCode(), 'shown' => in_array($setId, $setIds, true)];
        }
        $rows[] = [
            'label' => $helper->__('Customer Groups'),
            'items' => self::sortShownFirst($groups),
            'note' => $setIds
                ? $helper->__('A customer group shows the attribute when its set holds the attribute.')
                : $helper->__('The attribute is in no set, so no customer group sees it. Add it to a set in Attribute Sets.'),
        ];

        $modes = [
            'hidden' => $helper->__('Hidden'),
            'optional' => $helper->__('Optional'),
            'required' => $helper->__('Required'),
        ];
        $websiteValues = $this->getResource()->getWebsiteValues((int) $this->getId());
        $websiteModes = [];
        foreach (Mage::app()->getWebsites() as $website) {
            $mode = self::getWebsiteModeOverride($websiteValues[(int) $website->getId()] ?? null) ?: $this->getDefaultWebsiteMode();
            $websiteModes[] = [(string) $website->getName(), $mode];
        }
        $usedModes = array_values(array_unique(array_column($websiteModes, 1)));
        if (count($websiteModes) > 1 && count($usedModes) === 1) {
            $websites = [['label' => $helper->__('All websites (%s)', $modes[$usedModes[0]]), 'shown' => $usedModes[0] !== 'hidden']];
        } else {
            $websites = [];
            foreach ($websiteModes as [$name, $mode]) {
                $websites[] = ['label' => $name . ' (' . $modes[$mode] . ')', 'shown' => $mode !== 'hidden'];
            }
        }
        if ($websites) {
            $rows[] = ['label' => $helper->__('Websites'), 'items' => self::sortShownFirst($websites), 'note' => null];
        }

        return [
            'comment' => $helper->__('A customer sees the field only on a form, in a customer group and on a website that show it.'),
            'rows' => $rows,
        ];
    }

    /**
     * Put the items that show the attribute before the items that do not, and keep the order in each part.
     *
     * @param list<array{label: string, shown: bool}> $items
     * @return list<array{label: string, shown: bool}>
     */
    private static function sortShownFirst(array $items): array
    {
        usort($items, fn(array $a, array $b): int => $b['shown'] <=> $a['shown']);
        return $items;
    }

    /**
     * Return the mode of the Default field, from is_visible and is_required: hidden, optional or required.
     */
    public function getDefaultWebsiteMode(): string
    {
        return match (true) {
            !$this->getId() => 'optional',
            !$this->getIsVisible() => 'hidden',
            (bool) $this->getIsRequired() => 'required',
            default => 'optional',
        };
    }

    /**
     * Return the mode that the website values of one website set, or '' when the website uses the default.
     *
     * @param array{is_visible: ?int, is_required: ?int}|null $values a row of getWebsiteValues()
     */
    public static function getWebsiteModeOverride(?array $values): string
    {
        return match (true) {
            $values === null || ($values['is_visible'] === null && $values['is_required'] === null) => '',
            $values['is_visible'] === 0 => 'hidden',
            $values['is_required'] === 1 => 'required',
            default => 'optional',
        };
    }

    /**
     * Return the column of customer_group that holds the set of this entity type.
     */
    protected function getGroupSetColumn(): ?string
    {
        return match ($this->getEntityType()->getEntityTypeCode()) {
            'customer' => 'customer_attribute_set_id',
            'customer_address' => 'customer_address_attribute_set_id',
            default => null,
        };
    }

    /**
     * Turn the Default field into is_visible and is_required, and keep only the known forms and
     * input rules. A system attribute keeps its forms.
     */
    #[\Override]
    public function prepareAdminData(array $data): array
    {
        $mode = $data['default_mode'] ?? null;
        unset($data['default_mode']);
        if (in_array($mode, ['hidden', 'optional', 'required'], true)) {
            $data['is_visible'] = $mode === 'hidden' ? 0 : 1;
            $data['is_required'] = $mode === 'required' ? 1 : 0;
        }

        if ($this->getId() && $this->getIsSystem()) {
            unset($data['used_in_forms']);
        } else {
            $allowed = array_keys($this->getFormCodes());
            $data['used_in_forms'] = array_values(array_intersect((array) ($data['used_in_forms'] ?? []), $allowed));
        }
        if (!$this->getId()) {
            $data['is_system'] = 0;
        } elseif (!$this->getIsUserDefined()) {
            // The Customer Configuration shows or hides the attributes of the platform, such as dob
            unset($data['is_visible']);
        }

        // A filter or a rule that the form does not offer, such as the date rule of dob, stays as it is
        $filters = ['striptags', 'escapehtml'];
        $storedFilter = (string) $this->getInputFilter();
        $data['input_filter'] = in_array($data['input_filter'] ?? '', $filters, true) ? $data['input_filter']
            : ($storedFilter === '' || in_array($storedFilter, $filters, true) ? null : $storedFilter);

        $formats = ['alphanumeric', 'alpha', 'numeric', 'email', 'url'];
        $rules = $this->getValidateRules();
        unset($rules['min_text_length'], $rules['max_text_length']);
        if (in_array($rules['input_validation'] ?? '', $formats, true)) {
            unset($rules['input_validation']);
        }
        foreach (['min_text_length', 'max_text_length'] as $key) {
            if (!empty($data[$key])) {
                $rules[$key] = (int) $data[$key];
            }
        }
        if (in_array($data['input_validation'] ?? '', $formats, true)) {
            $rules['input_validation'] = $data['input_validation'];
        }
        // A date attribute checks and normalizes its value, as dob does
        if (($data['frontend_input'] ?? $this->getFrontendInput()) === 'date') {
            $data['input_filter'] = 'date';
            $rules['input_validation'] = 'date';
        }
        $data['validate_rules'] = $rules;
        unset($data['min_text_length'], $data['max_text_length'], $data['input_validation'], $data['website_values']);

        if (isset($data['multiline_count'])) {
            $data['multiline_count'] = max(1, (int) $data['multiline_count']);
        }
        return $data;
    }

    /**
     * Save the hidden, optional or required mode of each website.
     */
    #[\Override]
    public function saveAdminData(array $data): void
    {
        if (is_array($data['website_values'] ?? null)) {
            $websiteIds = array_map(intval(...), array_keys(Mage::app()->getWebsites()));
            $this->getResource()->saveWebsiteValues((int) $this->getId(), array_intersect_key($data['website_values'], array_flip($websiteIds)));
        }
    }

    #[\Override]
    protected function _getInputTypes(): array
    {
        $types = parent::_getInputTypes();
        if ($this->getEntityType()->getEntityTypeCode() === 'customer_address') {
            $types['multiline'] = ['label' => Mage::helper('customer')->__('Multiple Lines')];
        }
        return $types;
    }

    /**
     * Return the forms that can show the attribute, keyed by form code.
     *
     * The event customer_attribute_form_codes_prepare lets a module add a form.
     *
     * @return array<string, string>
     */
    public function getFormCodes(): array
    {
        $helper = Mage::helper('customer');
        $codes = $this->getEntityType()->getEntityTypeCode() === 'customer_address'
            ? [
                'adminhtml_customer_address' => $helper->__('Admin Customer Address'),
                'customer_address_edit' => $helper->__('Address Book and Checkout'),
                'customer_register_address' => $helper->__('Customer Registration'),
            ]
            : [
                'adminhtml_customer' => $helper->__('Admin Customer'),
                'adminhtml_checkout' => $helper->__('Admin Order Creation'),
                'customer_account_create' => $helper->__('Customer Registration'),
                'customer_account_edit' => $helper->__('Customer Account Edit'),
                'checkout_register' => $helper->__('Checkout Registration'),
            ];
        $result = new \Maho\DataObject(['codes' => $codes]);
        Mage::dispatchEvent('customer_attribute_form_codes_prepare', ['attribute' => $this, 'result' => $result]);
        return $result->getData('codes');
    }

    public function setScopeIsVisible(?bool $value = true): static
    {
        return $this->setData('scope_is_visible', $value);
    }

    public function setScopeIsRequired(?bool $value = true): static
    {
        return $this->setData('scope_is_required', $value);
    }

    public function getScopeMultilineCount(): ?int
    {
        $value = $this->getData('scope_multiline_count');
        return $value === null ? null : (int) $value;
    }

    public function setScopeMultilineCount(?int $value): static
    {
        return $this->setData('scope_multiline_count', $value);
    }
}
