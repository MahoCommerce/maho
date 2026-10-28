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
    public function getAdminPageSettings(): ?array
    {
        $helper = Mage::helper('customer');
        return match ($this->getEntityType()->getEntityTypeCode()) {
            'customer' => [
                'acl' => 'system/attributes/customer',
                'title' => $helper->__('Customer Attributes'),
                'sets_title' => $helper->__('Customer Attribute Sets'),
                'main_tab' => 'adminhtml/customer_attribute_edit_tab_main',
            ],
            'customer_address' => [
                'acl' => 'system/attributes/customer_address',
                'title' => $helper->__('Customer Address Attributes'),
                'sets_title' => $helper->__('Customer Address Attribute Sets'),
                'main_tab' => 'adminhtml/customer_attribute_edit_tab_main',
            ],
            default => null,
        };
    }

    /**
     * Keep only the known forms and input rules. A system attribute keeps its forms.
     */
    #[\Override]
    public function prepareAdminData(array $data): array
    {
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
