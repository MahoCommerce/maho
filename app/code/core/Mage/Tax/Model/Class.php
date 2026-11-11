<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Tax
 */

declare(strict_types=1);

/**
 * @method Mage_Tax_Model_Resource_Class _getResource()
 * @method Mage_Tax_Model_Resource_Class getResource()
 * @method Mage_Tax_Model_Resource_Class_Collection getCollection()
 */

class Mage_Tax_Model_Class extends Mage_Core_Model_Abstract
{
    public const TAX_CLASS_TYPE_CUSTOMER   = 'CUSTOMER';
    public const TAX_CLASS_TYPE_PRODUCT    = 'PRODUCT';

    #[\Override]
    protected function _construct()
    {
        $this->_init('tax/class');
    }

    /**
     * @throws Mage_Core_Exception_Input with one error for each invalid field
     */
    #[\Override]
    protected function _beforeSave()
    {
        $helper = Mage::helper('tax');
        $errors = new Mage_Core_Exception_Input();
        if (trim((string) $this->getClassName()) === '') {
            $errors->addError('class_name', $helper->__('Tax class name is required.'));
        }
        // An unset type takes the default of the column
        $type = $this->getClassType();
        if ($type !== null && !in_array($type, [self::TAX_CLASS_TYPE_PRODUCT, self::TAX_CLASS_TYPE_CUSTOMER], true)) {
            $errors->addError('class_type', $helper->__('Tax class type must be PRODUCT or CUSTOMER.'));
        }
        $errors->throwIfErrors();
        return parent::_beforeSave();
    }

    /**
     * Refuse to delete a class that a tax rule, a customer group or a product uses.
     *
     * @throws Mage_Core_Exception_Conflict
     */
    #[\Override]
    protected function _beforeDelete()
    {
        $helper = Mage::helper('tax');
        $type = (string) $this->getClassType();
        $rules = Mage::getResourceModel('tax/calculation_rule_collection')->setClassTypeFilter($type, (int) $this->getId());
        if ($rules->getSize() > 0) {
            throw new Mage_Core_Exception_Conflict($helper->__('You cannot delete this tax class as it is used in Tax Rules. You have to delete the rules it is used in first.'));
        }

        if ($type === self::TAX_CLASS_TYPE_CUSTOMER) {
            $groups = Mage::getResourceModel('customer/group_collection')->addFieldToFilter('tax_class_id', (int) $this->getId())->getSize();
            if ($groups > 0) {
                throw new Mage_Core_Exception_Conflict($helper->__('You cannot delete this tax class as it is used for %d customer groups.', $groups));
            }
        } else {
            $products = Mage::getResourceModel('catalog/product_collection')->addAttributeToFilter('tax_class_id', (int) $this->getId())->getSize();
            if ($products > 0) {
                throw new Mage_Core_Exception_Conflict($helper->__('You cannot delete this tax class as it is used for %d products.', $products));
            }
        }
        return parent::_beforeDelete();
    }

    public function getClassName(): ?string
    {
        $value = $this->getData('class_name');
        return $value === null ? null : (string) $value;
    }

    public function setClassName(?string $value): static
    {
        return $this->setData('class_name', $value);
    }

    public function getClassType(): ?string
    {
        $value = $this->getData('class_type');
        return $value === null ? null : (string) $value;
    }

    public function setClassType(?string $value): static
    {
        return $this->setData('class_type', $value);
    }
}
