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
