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
 * @method Mage_Tax_Model_Resource_Calculation_Rate _getResource()
 * @method Mage_Tax_Model_Resource_Calculation_Rate getResource()
 * @method Mage_Tax_Model_Resource_Calculation_Rate_Collection getCollection()
 *
 * @method bool hasTaxPostcode()
 */
class Mage_Tax_Model_Calculation_Rate extends Mage_Core_Model_Abstract
{
    /**
     * List of tax titles
     *
     * @var array|null
     */
    protected $_titles = null;

    /**
     * The Mage_Tax_Model_Calculation_Rate_Title
     *
     * @var Mage_Tax_Model_Calculation_Rate_Title|null
     */
    protected $_titleModel = null;

    /**
     * Varien model constructor
     */
    #[\Override]
    protected function _construct()
    {
        $this->_init('tax/calculation_rate');
    }

    /**
     * Check the fields, and prepare the location settings and the tax postcode. The resource model refuses a code that another rate uses.
     *
     * @return $this
     * @throws Mage_Core_Exception_Input with one error for each invalid field
     */
    #[\Override]
    protected function _beforeSave()
    {
        $helper = Mage::helper('tax');
        $this->validateRecord()->throwIfErrors();

        if ($this->getZipIsRange()) {
            $zipFrom = $this->getData('zip_from');
            $zipTo = $this->getData('zip_to');

            if (strlen((string) $zipFrom) > 9 || strlen((string) $zipTo) > 9) {
                throw new Mage_Core_Exception_Input()->addError('zip_from', $helper->__('Maximum zip code length is 9.'));
            }

            if (!ctype_digit((string) $zipFrom) || !ctype_digit((string) $zipTo)) {
                throw new Mage_Core_Exception_Input()->addError('zip_from', $helper->__('Zip code should not contain characters other than digits.'));
            }

            if ((int) $zipFrom > (int) $zipTo) {
                throw new Mage_Core_Exception_Input()->addError('zip_to', $helper->__('Range To should be equal or greater than Range From.'));
            }

            $this->setTaxPostcode($zipFrom . '-' . $zipTo);
        } else {
            $taxPostCode = $this->getTaxPostcode();

            if (strlen($taxPostCode ?? '') > 10) {
                $taxPostCode = substr($taxPostCode, 0, 10);
            }

            $this->setTaxPostcode($taxPostCode)
                ->setZipIsRange(null)
                ->setZipFrom(null)
                ->setZipTo(null);
        }

        parent::_beforeSave();
        $country = $this->getTaxCountryId();
        $region = $this->getTaxRegionId();
        $regionModel = Mage::getModel('directory/region');
        $regionModel->load($region);
        if ($regionModel->getCountryId() != $country) {
            $this->setTaxRegionId('*');
        }
        return $this;
    }

    /**
     * Save rate titles
     */
    #[\Override]
    protected function _afterSave()
    {
        // A save without titles keeps the stored ones
        if ($this->getTitle() !== null) {
            $this->saveTitles();
        }
        Mage::dispatchEvent('tax_settings_change_after');
        return parent::_afterSave();
    }

    /**
     * Add an error to $errors for each problem of the code, the country, the rate and the zip range, and return $errors.
     * Mage_Tax_Service_Rate also checks that the store has the country and the store views of the titles.
     */
    public function validateRecord(Mage_Core_Exception_Input $errors = new Mage_Core_Exception_Input()): Mage_Core_Exception_Input
    {
        $helper = Mage::helper('tax');
        if (trim((string) $this->getCode()) === '') {
            $errors->addError('code', $helper->__('Tax rate code is required.'));
        }
        if ((string) $this->getTaxCountryId() === '') {
            $errors->addError('tax_country_id', $helper->__('Tax country is required.'));
        }
        $rate = $this->getData('rate');
        if ($rate === null || $rate === '') {
            $errors->addError('rate', $helper->__('Rate is required.'));
        } elseif (!is_numeric($rate) || $rate < 0) {
            $errors->addError('rate', $helper->__('Rate Percent should be a positive number.'));
        }
        if ($this->getZipIsRange()) {
            foreach (['zip_from', 'zip_to'] as $field) {
                if ((string) $this->getData($field) === '') {
                    $errors->addError($field, $helper->__('Please fill all required fields with valid information.'));
                }
            }
        }
        return $errors;
    }

    /**
     * Processing object before delete data
     *
     * @return Mage_Core_Model_Abstract
     * @throws Mage_Core_Exception_Conflict when a tax rule uses the rate
     */
    #[\Override]
    protected function _beforeDelete()
    {
        if ($this->_isInRule()) {
            throw new Mage_Core_Exception_Conflict(Mage::helper('tax')->__('Tax rate cannot be removed. It exists in tax rule'));
        }
        return parent::_beforeDelete();
    }

    /**
     * After rate delete
     * Redeclared for dispatch tax_settings_change_after event
     */
    #[\Override]
    protected function _afterDelete()
    {
        Mage::dispatchEvent('tax_settings_change_after');
        return parent::_afterDelete();
    }

    /**
     * Saves the tax titles
     *
     * @param array | null $titles
     */
    public function saveTitles($titles = null)
    {
        $titles ??= $this->getTitle();

        $this->getTitleModel()->deleteByRateId($this->getId());
        if (is_array($titles) && $titles) {
            foreach ($titles as $store => $title) {
                if ($title !== '') {
                    $this->getTitleModel()
                        ->setId(null)
                        ->setTaxCalculationRateId((int) $this->getId())
                        ->setStoreId((int) $store)
                        ->setValue($title)
                        ->save();
                }
            }
        }
    }

    /**
     * Returns the Mage_Tax_Model_Calculation_Rate_Title
     *
     * @return Mage_Tax_Model_Calculation_Rate_Title
     */
    public function getTitleModel()
    {
        $this->_titleModel ??= Mage::getModel('tax/calculation_rate_title');
        return $this->_titleModel;
    }

    /**
     * Returns the list of tax titles
     *
     * @return array
     */
    public function getTitles()
    {
        $this->_titles ??= $this->getTitleModel()->getCollection()->loadByRateId($this->getId());
        return $this->_titles;
    }

    /**
     * Deletes all tax rates
     *
     * @return $this
     */
    public function deleteAllRates()
    {
        $this->_getResource()->deleteAllRates();
        Mage::dispatchEvent('tax_settings_change_after');
        return $this;
    }

    /**
     * Load rate model by code
     *
     * @param  string $code
     * @return $this
     */
    public function loadByCode($code)
    {
        $this->load($code, 'code');
        return $this;
    }

    /**
     * Check if rate exists in tax rule
     *
     * @return array
     */
    protected function _isInRule()
    {
        return $this->getResource()->isInRule($this->getId());
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

    public function getRate(): ?float
    {
        $value = $this->getData('rate');
        return $value === null ? null : (float) $value;
    }

    public function setRate(?float $value): static
    {
        return $this->setData('rate', $value);
    }

    public function setRegionName(?string $value): static
    {
        return $this->setData('region_name', $value);
    }

    public function getTaxCalculationRateId(): ?int
    {
        $value = $this->getData('tax_calculation_rate_id');
        return $value === null ? null : (int) $value;
    }

    public function getTaxCountryId(): ?string
    {
        $value = $this->getData('tax_country_id');
        return $value === null ? null : (string) $value;
    }

    public function setTaxCountryId(?string $value): static
    {
        return $this->setData('tax_country_id', $value);
    }

    public function getTaxPostcode(): ?string
    {
        $value = $this->getData('tax_postcode');
        return $value === null ? null : (string) $value;
    }

    public function setTaxPostcode(?string $value): static
    {
        return $this->setData('tax_postcode', $value);
    }

    public function getTaxRegionId(): int|string|null
    {
        return $this->getData('tax_region_id');
    }

    public function setTaxRegionId(int|string|null $value): static
    {
        return $this->setData('tax_region_id', $value);
    }

    public function getTitle(): ?array
    {
        return $this->getData('title');
    }

    public function setTitle(?array $value): static
    {
        return $this->setData('title', $value);
    }

    public function getZipFrom(): ?int
    {
        $value = $this->getData('zip_from');
        return $value === null ? null : (int) $value;
    }

    public function setZipFrom(?int $value): static
    {
        return $this->setData('zip_from', $value);
    }

    public function getZipIsRange(): ?bool
    {
        $value = $this->getData('zip_is_range');
        return $value === null ? null : (bool) $value;
    }

    public function setZipIsRange(?bool $value = true): static
    {
        return $this->setData('zip_is_range', $value);
    }

    public function getZipTo(): ?int
    {
        $value = $this->getData('zip_to');
        return $value === null ? null : (int) $value;
    }

    public function setZipTo(?int $value): static
    {
        return $this->setData('zip_to', $value);
    }

}
