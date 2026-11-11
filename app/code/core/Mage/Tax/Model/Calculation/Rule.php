<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Tax
 */

declare(strict_types=1);

/**
 * @method Mage_Tax_Model_Resource_Calculation_Rule _getResource()
 * @method Mage_Tax_Model_Resource_Calculation_Rule getResource()
 * @method Mage_Tax_Model_Resource_Calculation_Rule_Collection getCollection()
 */
class Mage_Tax_Model_Calculation_Rule extends Mage_Core_Model_Abstract
{
    /**
     * No references found in the project. Variable kept for backward compatibility
     */
    protected $_ctcs                = null;

    /**
     * No references found in the project. Variable kept for backward compatibility
     */
    protected $_ptcs                = null;

    /**
     * No references found in the project. Variable kept for backward compatibility
     */
    protected $_rates               = null;

    /**
     * No references found in the project. Variable kept for backward compatibility
     */
    protected $_ctcModel            = null;

    /**
     * No references found in the project. Variable kept for backward compatibility
     */
    protected $_ptcModel            = null;

    /**
     * No references found in the project. Variable kept for backward compatibility
     *
     * @var Mage_Tax_Model_Calculation_Rate
     */
    protected $_rateModel           = null;

    /**
     * Holds the tax Calculation model
     *
     * @var Mage_Tax_Model_Calculation|null
     */
    protected $_calculationModel    = null;

    /**
     * Varien model constructor
     */
    #[\Override]
    protected function _construct()
    {
        $this->_init('tax/calculation_rule');
    }

    /**
     * Check the code, the priority, the position, and the classes and the rates of the rule.
     * A save without classes and rates keeps the stored ones. The resource model refuses a code that another rule uses.
     * Mage_Tax_Service_Rule refuses a combination of the rates and the classes that another rule has.
     *
     * @throws Mage_Core_Exception_Input with one error for each invalid field
     */
    #[\Override]
    protected function _beforeSave()
    {
        $helper = Mage::helper('tax');
        $errors = new Mage_Core_Exception_Input();
        if (trim((string) $this->getCode()) === '') {
            $errors->addError('code', $helper->__('Tax rule code is required.'));
        }
        foreach (['priority', 'position'] as $field) {
            if ((int) $this->getData($field) < 0) {
                $errors->addError($field, $helper->__('Please enter a number 0 or greater in this field.'));
            }
        }

        if ($this->isObjectNew() || $this->hasCalculationData()) {
            $classTypes = [
                'tax_customer_class' => [Mage_Tax_Model_Class::TAX_CLASS_TYPE_CUSTOMER, $helper->__('At least one customer tax class is required.')],
                'tax_product_class' => [Mage_Tax_Model_Class::TAX_CLASS_TYPE_PRODUCT, $helper->__('At least one product tax class is required.')],
            ];
            foreach ($classTypes as $field => [$type, $required]) {
                $ids = array_map(intval(...), (array) $this->getData($field));
                $known = Mage::getResourceModel('tax/class_collection')->setClassTypeFilter($type)->getAllIds();
                if ($ids === []) {
                    $errors->addError($field, $required);
                } elseif ($unknown = array_diff($ids, array_map(intval(...), $known))) {
                    $errors->addError($field, $helper->__('Tax class not found with ID: %s', implode(', ', $unknown)));
                }
            }
            $rateIds = array_map(intval(...), (array) $this->getData('tax_rate'));
            if ($rateIds === []) {
                $errors->addError('tax_rate', $helper->__('At least one tax rate is required.'));
            } elseif ($unknown = array_diff($rateIds, array_map(intval(...), Mage::getResourceModel('tax/calculation_rate_collection')->getAllIds()))) {
                $errors->addError('tax_rate', $helper->__('Tax rate not found with ID: %s', implode(', ', $unknown)));
            }
        }
        $errors->throwIfErrors();

        return parent::_beforeSave();
    }

    /**
     * Whether the rule holds its customer tax classes, its product tax classes and its rates, which a save writes.
     */
    public function hasCalculationData(): bool
    {
        return $this->getData('tax_customer_class') !== null
            || $this->getData('tax_product_class') !== null
            || $this->getData('tax_rate') !== null;
    }

    /**
     * After save rule
     * Re - declared for populate rate calculations
     *
     * @return $this
     */
    #[\Override]
    protected function _afterSave()
    {
        parent::_afterSave();
        if ($this->hasCalculationData()) {
            $this->saveCalculationData();
        }
        Mage::dispatchEvent('tax_settings_change_after');
        return $this;
    }

    /**
     * After rule delete
     * Redeclared for dispatch tax_settings_change_after event
     */
    #[\Override]
    protected function _afterDelete()
    {
        Mage::dispatchEvent('tax_settings_change_after');
        return parent::_afterDelete();
    }

    /**
     * Saves the Calculation Data
     */
    public function saveCalculationData()
    {
        $ctc = $this->getData('tax_customer_class');
        $ptc = $this->getData('tax_product_class');
        $rates = $this->getData('tax_rate');

        Mage::getSingleton('tax/calculation')->deleteByRuleId($this->getId());
        foreach ($ctc as $c) {
            foreach ($ptc as $p) {
                foreach ($rates as $r) {
                    $dataArray = [
                        'tax_calculation_rule_id'   => $this->getId(),
                        'tax_calculation_rate_id'   => $r,
                        'customer_tax_class_id'     => $c,
                        'product_tax_class_id'      => $p,
                    ];
                    Mage::getSingleton('tax/calculation')->setData($dataArray)->save();
                }
            }
        }
    }

    /**
     * @return Mage_Core_Model_Abstract|Mage_Tax_Model_Calculation|null
     */
    public function getCalculationModel()
    {
        $this->_calculationModel ??= Mage::getSingleton('tax/calculation');
        return $this->_calculationModel;
    }

    /**
     * @return mixed
     */
    public function getRates()
    {
        return $this->getCalculationModel()->getRates($this->getId());
    }

    /**
     * @return mixed
     */
    public function getCustomerTaxClasses()
    {
        return $this->getCalculationModel()->getCustomerTaxClasses($this->getId());
    }

    /**
     * @return mixed
     */
    public function getProductTaxClasses()
    {
        return $this->getCalculationModel()->getProductTaxClasses($this->getId());
    }

    /**
     * Fetches rules by rate, customer tax class and product tax class
     * and product tax class combination
     *
     * @param array $rateId
     * @param array $customerTaxClassId
     * @param array $productTaxClassId
     * @return array
     */
    public function fetchRuleCodes($rateId, $customerTaxClassId, $productTaxClassId)
    {
        return $this->getResource()->fetchRuleCodes($rateId, $customerTaxClassId, $productTaxClassId);
    }

    public function setCalculateSubtotal(?int $value): static
    {
        return $this->setData('calculate_subtotal', $value);
    }

    public function getCode(): ?string
    {
        $value = $this->getData('code');
        return $value === null ? null : (string) $value;
    }

    public function setCode(?string $value): static
    {
        return $this->setData('code', $value);
    }

    public function getPosition(): ?int
    {
        $value = $this->getData('position');
        return $value === null ? null : (int) $value;
    }

    public function setPosition(?int $value): static
    {
        return $this->setData('position', $value);
    }

    public function getPriority(): ?int
    {
        $value = $this->getData('priority');
        return $value === null ? null : (int) $value;
    }

    public function setPriority(?int $value): static
    {
        return $this->setData('priority', $value);
    }

    public function getTaxCustomerClass(): ?array
    {
        return $this->getData('tax_customer_class');
    }

    /**
     * @param list<int>|null $value the IDs of the customer tax classes
     */
    public function setTaxCustomerClass(?array $value): static
    {
        return $this->setData('tax_customer_class', $value);
    }

    public function getTaxProductClass(): ?array
    {
        return $this->getData('tax_product_class');
    }

    /**
     * @param list<int>|null $value the IDs of the product tax classes
     */
    public function setTaxProductClass(?array $value): static
    {
        return $this->setData('tax_product_class', $value);
    }

    public function getTaxRate(): ?array
    {
        return $this->getData('tax_rate');
    }

    /**
     * @param list<int>|null $value the IDs of the tax rates
     */
    public function setTaxRate(?array $value): static
    {
        return $this->setData('tax_rate', $value);
    }

}
