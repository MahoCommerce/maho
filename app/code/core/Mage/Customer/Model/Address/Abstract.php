<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2025 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Customer
 */

declare(strict_types=1);

/**
 * Address abstract model
 *
 * @package    Mage_Customer
 *
 * @method $this unsRegion()
 */
class Mage_Customer_Model_Address_Abstract extends Mage_Core_Model_Abstract
{
    /**
     * Possible customer address types
     */
    public const TYPE_BILLING  = 'billing';
    public const TYPE_SHIPPING = 'shipping';

    /**
     * Prefix of model events
     *
     * @var string
     */
    #[\Override]
    protected $_eventPrefix = 'customer_address';

    /**
     * Name of event object
     *
     * @var string
     */
    #[\Override]
    protected $_eventObject = 'customer_address';

    /**
     * List of errors
     *
     * @var array
     */
    protected $_errors = [];

    /**
     * Directory country models
     *
     * @var array
     */
    protected static $_countryModels = [];

    /**
     * Directory region models
     *
     * @var array
     */
    protected static $_regionModels = [];

    /**
     * Get full customer name
     *
     * @return string
     */
    public function getName()
    {
        $name = '';
        $config = Mage::getSingleton('eav/config');
        if ($config->getAttribute('customer_address', 'prefix')->getIsVisible() && $this->getPrefix()) {
            $name .= $this->getPrefix() . ' ';
        }
        $name .= $this->getFirstname();
        if ($config->getAttribute('customer_address', 'middlename')->getIsVisible() && $this->getMiddlename()) {
            $name .= ' ' . $this->getMiddlename();
        }
        $name .=  ' ' . $this->getLastname();
        if ($config->getAttribute('customer_address', 'suffix')->getIsVisible() && $this->getSuffix()) {
            $name .= ' ' . $this->getSuffix();
        }
        return $name;
    }

    /**
     * get address street
     *
     * @param   int $line address line index
     * @return  string|array
     */
    public function getStreet($line = 0)
    {
        $street = parent::getData('street');
        if ($line === -1) {
            // Callers expect the raw newline-joined string; street can still be
            // an array when it was written via addData() and not yet saved.
            return is_array($street) ? trim(implode("\n", $street)) : $street;
        }
        $arr = is_array($street) ? $street : explode("\n", (string) $street);
        if ($line === 0 || $line === null) {
            return $arr;
        }
        return $arr[$line - 1] ?? '';
    }

    /**
     * @return string
     */
    public function getStreet1()
    {
        return $this->getStreet(1);
    }

    /**
     * @return string
     */
    public function getStreet2()
    {
        return $this->getStreet(2);
    }

    /**
     * @return string
     */
    public function getStreet3()
    {
        return $this->getStreet(3);
    }

    /**
     * @return string
     */
    public function getStreet4()
    {
        return $this->getStreet(4);
    }

    /**
     * @return string
     */
    public function getStreetFull()
    {
        return $this->getData('street');
    }

    /**
     * @param string $street
     * @return Mage_Customer_Model_Address_Abstract
     */
    public function setStreetFull($street)
    {
        return $this->setStreet($street);
    }

    /**
     * set address street
     *
     * @param array|string $street
     * @return $this
     */
    public function setStreet($street)
    {
        if (is_array($street)) {
            $street = trim(implode("\n", $street));
        }
        $this->setData('street', $street);
        return $this;
    }

    /**
     * Create fields street1, street2, etc.
     *
     * To be used in controllers for views data
     */
    public function explodeStreetAddress()
    {
        $streetLines = $this->getStreet();
        foreach ($streetLines as $i => $line) {
            $this->setData('street' . ($i + 1), $line);
        }
        return $this;
    }

    /**
     * To be used when processing _POST
     */
    public function implodeStreetAddress()
    {
        $this->setStreet($this->getData('street'));
        return $this;
    }

    /**
     * Retrieve region name
     *
     * @return string
     */
    public function getRegion()
    {
        $regionId = $this->getData('region_id');
        $region   = $this->getData('region');

        if ($regionId) {
            if ($this->getRegionModel($regionId)->getCountryId() == $this->getCountryId()) {
                $region = $this->getRegionModel($regionId)->getName();
                $this->setData('region', $region);
            }
        }

        if (!empty($region) && is_string($region)) {
            $this->setData('region', $region);
        } elseif (!$regionId && is_numeric($region)) {
            if ($this->getRegionModel($region)->getCountryId() == $this->getCountryId()) {
                $this->setData('region', $this->getRegionModel($region)->getName());
                $this->setData('region_id', $region);
            }
        } elseif ($regionId && !$region) {
            if ($this->getRegionModel($regionId)->getCountryId() == $this->getCountryId()) {
                $this->setData('region', $this->getRegionModel($regionId)->getName());
            }
        }

        return $this->getData('region');
    }

    /**
     * Return 2 letter state code if available, otherwise full region name
     */
    public function getRegionCode()
    {
        $regionId = $this->getData('region_id');
        $region   = $this->getData('region');

        if (!$regionId && is_numeric($region)) {
            if ($this->getRegionModel($region)->getCountryId() == $this->getCountryId()) {
                $this->setData('region_code', $this->getRegionModel($region)->getCode());
            }
        } elseif ($regionId) {
            if ($this->getRegionModel($regionId)->getCountryId() == $this->getCountryId()) {
                $this->setData('region_code', $this->getRegionModel($regionId)->getCode());
            }
        } elseif (is_string($region)) {
            $this->setData('region_code', $region);
        }
        return $this->getData('region_code');
    }

    public function getRegionId(): ?int
    {
        $regionId = $this->getData('region_id');
        $region   = $this->getData('region');
        if (!$regionId) {
            if (is_numeric($region)) {
                $this->setData('region_id', $region);
                $this->unsRegion();
            } else {
                $regionModel = Mage::getModel('directory/region')
                    ->loadByCode($this->getRegionCode(), $this->getCountryId());
                $this->setData('region_id', $regionModel->getId());
            }
        }
        $value = $this->getData('region_id');
        return $value === null ? null : (int) $value;
    }

    /**
     * @return string
     */
    public function getCountry()
    {
        /*if ($this->getData('country_id') && !$this->getData('country')) {
            $this->setData('country', Mage::getModel('directory/country')
                ->load($this->getData('country_id'))->getIso2Code());
        }
        return $this->getData('country');*/
        $country = $this->getCountryId();
        return $country ?: $this->getData('country');
    }

    /**
     * Retrieve country model
     *
     * @return Mage_Directory_Model_Country
     */
    public function getCountryModel()
    {
        $countryId = (string) $this->getCountryId();
        self::$_countryModels[$countryId] ??= Mage::getModel('directory/country')
            ->load($countryId);

        return self::$_countryModels[$countryId];
    }

    /**
     * Retrieve country model
     *
     * @param int|null $region
     * @return Mage_Directory_Model_Country
     */
    public function getRegionModel($region = null)
    {
        $region ??= $this->getRegionId();

        self::$_regionModels[$region] ??= Mage::getModel('directory/region')->load($region);

        return self::$_regionModels[$region];
    }

    /**
     * Retrieve HTML address format
     *
     * @return \Maho\DataObject
     */
    public function getHtmlFormat()
    {
        return $this->getConfig()->getFormatByCode('html');
    }

    /**
     * @param bool $html
     * @return string
     */
    public function getFormated($html = false)
    {
        return $this->format($html ? 'html' : 'text');
    }

    /**
     * @param string $type
     * @return string|null
     */
    public function format($type)
    {
        if (!($formatType = $this->getConfig()->getFormatByCode($type))
            || !$formatType->getRenderer()
        ) {
            return null;
        }
        Mage::dispatchEvent('customer_address_format', ['type' => $formatType, 'address' => $this]);
        return $formatType->getRenderer()->render($this);
    }

    /**
     * Retrieve address config object
     *
     * @return Mage_Customer_Model_Address_Config
     */
    public function getConfig()
    {
        return Mage::getSingleton('customer/address_config');
    }

    #[\Override]
    protected function _beforeSave()
    {
        parent::_beforeSave();
        $this->getRegion();
        if (is_array($this->getData('street'))) {
            $this->implodeStreetAddress();
        }
        return $this;
    }

    /**
     * Validate address attribute values
     *
     * @return array | bool
     */
    public function validate()
    {
        $this->_resetErrors();

        $this->implodeStreetAddress();

        $this->_basicCheck();

        Mage::dispatchEvent('customer_address_validation_after', ['address' => $this]);

        $errors = $this->_getErrors();

        $this->_resetErrors();

        if (empty($errors) || $this->getShouldIgnoreValidation()) {
            return true;
        }
        return $errors;
    }

    /**
     * Perform basic validation
     */
    protected function _basicCheck()
    {
        // Validate first name
        if (!Mage::helper('core')->isValidNotBlank($this->getFirstname())) {
            $this->addError(Mage::helper('customer')->__('Please enter the first name.'));
        }

        // Validate last name
        if (!Mage::helper('core')->isValidNotBlank($this->getLastname())) {
            $this->addError(Mage::helper('customer')->__('Please enter the last name.'));
        }

        // Validate street
        if (!Mage::helper('core')->isValidNotBlank($this->getStreet(1))) {
            $this->addError(Mage::helper('customer')->__('Please enter the street.'));
        }

        // Validate city
        if (!Mage::helper('core')->isValidNotBlank($this->getCity())) {
            $this->addError(Mage::helper('customer')->__('Please enter the city.'));
        }

        // Validate telephone
        if (!Mage::helper('core')->isValidNotBlank($this->getTelephone())) {
            $this->addError(Mage::helper('customer')->__('Please enter the telephone number.'));
        }

        // Validate postcode
        $havingOptionalZip = Mage::helper('directory')->getCountriesWithOptionalZip();
        if (!in_array($this->getCountryId(), $havingOptionalZip)) {
            if (!Mage::helper('core')->isValidNotBlank($this->getPostcode())) {
                $this->addError(Mage::helper('customer')->__('Please enter the zip/postal code.'));
            }
        }

        // Validate country
        if (!Mage::helper('core')->isValidNotBlank($this->getCountryId())) {
            $this->addError(Mage::helper('customer')->__('Please enter the country.'));
        }

        // Validate region
        if ($this->getCountryModel()->getRegionCollection()->getSize()
            && Mage::helper('directory')->isRegionRequired($this->getCountryId())
        ) {
            if (!Mage::helper('core')->isValidNotBlank($this->getRegionId())) {
                $this->addError(Mage::helper('customer')->__('Please enter the state/province.'));
            }
        }
    }

    /**
     * Add error
     *
     * @param string $error
     * @return $this
     */
    public function addError($error)
    {
        $this->_errors[] = $error;
        return $this;
    }

    /**
     * Retrieve errors
     *
     * @return array
     */
    protected function _getErrors()
    {
        return $this->_errors;
    }

    /**
     * Reset errors array
     *
     * @return $this
     */
    protected function _resetErrors()
    {
        $this->_errors = [];
        return $this;
    }

    public function getCustomerId(): ?int
    {
        $value = $this->getData('customer_id');
        return $value === null ? null : (int) $value;
    }

    public function getFirstname(): ?string
    {
        $value = $this->getData('firstname');
        return $value === null ? null : (string) $value;
    }

    public function setFirstname(?string $value): static
    {
        return $this->setData('firstname', $value);
    }

    public function getMiddlename(): ?string
    {
        $value = $this->getData('middlename');
        return $value === null ? null : (string) $value;
    }

    public function setMiddlename(?string $value): static
    {
        return $this->setData('middlename', $value);
    }

    public function getLastname(): ?string
    {
        $value = $this->getData('lastname');
        return $value === null ? null : (string) $value;
    }

    public function setLastname(?string $value): static
    {
        return $this->setData('lastname', $value);
    }

    public function getCity(): ?string
    {
        $value = $this->getData('city');
        return $value === null ? null : (string) $value;
    }

    public function setCity(?string $value): static
    {
        return $this->setData('city', $value);
    }

    public function getTelephone(): ?string
    {
        $value = $this->getData('telephone');
        return $value === null ? null : (string) $value;
    }

    public function setTelephone(?string $value): static
    {
        return $this->setData('telephone', $value);
    }

    public function getCountryId(): ?string
    {
        $value = $this->getData('country_id');
        return $value === null ? null : (string) $value;
    }

    public function setCountryId(?string $value): static
    {
        return $this->setData('country_id', $value);
    }

    public function getPostcode(): ?string
    {
        $value = $this->getData('postcode');
        return $value === null ? null : (string) $value;
    }

    public function setPostcode(?string $value): static
    {
        return $this->setData('postcode', $value);
    }

    public function getParentId(): ?int
    {
        $value = $this->getData('parent_id');
        return $value === null ? null : (int) $value;
    }

    public function setRegion(?string $value): static
    {
        return $this->setData('region', $value);
    }

    public function getIsDefaultBilling(): ?bool
    {
        $value = $this->getData('is_default_billing');
        return $value === null ? null : (bool) $value;
    }

    public function setIsDefaultBilling(?bool $value = true): static
    {
        return $this->setData('is_default_billing', $value);
    }

    public function getIsDefaultShipping(): ?bool
    {
        $value = $this->getData('is_default_shipping');
        return $value === null ? null : (bool) $value;
    }

    public function getVatId(): ?string
    {
        $value = $this->getData('vat_id');
        return $value === null ? null : (string) $value;
    }

    public function getVatIsValid(): ?bool
    {
        $value = $this->getData('vat_is_valid');
        return $value === null ? null : (bool) $value;
    }

    public function getVatRequestId(): ?string
    {
        $value = $this->getData('vat_request_id');
        return $value === null ? null : (string) $value;
    }

    public function getVatRequestDate(): ?string
    {
        $value = $this->getData('vat_request_date');
        return $value === null ? null : (string) $value;
    }

    public function getVatRequestSuccess(): ?bool
    {
        $value = $this->getData('vat_request_success');
        return $value === null ? null : (bool) $value;
    }

    public function setIsDefaultShipping(?bool $value = true): static
    {
        return $this->setData('is_default_shipping', $value);
    }

    public function getIsPrimaryBilling(): ?bool
    {
        $value = $this->getData('is_primary_billing');
        return $value === null ? null : (bool) $value;
    }

    public function setIsPrimaryBilling(?bool $value = true): static
    {
        return $this->setData('is_primary_billing', $value);
    }

    public function getIsPrimaryShipping(): ?bool
    {
        $value = $this->getData('is_primary_shipping');
        return $value === null ? null : (bool) $value;
    }

    public function setIsPrimaryShipping(?bool $value = true): static
    {
        return $this->setData('is_primary_shipping', $value);
    }

    public function getForceProcess(): ?bool
    {
        $value = $this->getData('force_process');
        return $value === null ? null : (bool) $value;
    }

    public function setForceProcess(?bool $value = true): static
    {
        return $this->setData('force_process', $value);
    }

    public function getIsCustomerSaveTransaction(): ?bool
    {
        $value = $this->getData('is_customer_save_transaction');
        return $value === null ? null : (bool) $value;
    }

    public function setParentId(?int $value): static
    {
        return $this->setData('parent_id', $value);
    }

    public function setStoreId(?int $value): static
    {
        return $this->setData('store_id', $value);
    }

    public function setIsCustomerSaveTransaction(?bool $value = true): static
    {
        return $this->setData('is_customer_save_transaction', $value);
    }

    public function getPrefix(): ?string
    {
        $value = $this->getData('prefix');
        return $value === null ? null : (string) $value;
    }

    public function setPrefix(?string $value): static
    {
        return $this->setData('prefix', $value);
    }

    public function getSuffix(): ?string
    {
        $value = $this->getData('suffix');
        return $value === null ? null : (string) $value;
    }

    public function setSuffix(?string $value): static
    {
        return $this->setData('suffix', $value);
    }

    public function getShouldIgnoreValidation(): ?bool
    {
        $value = $this->getData('should_ignore_validation');
        return $value === null ? null : (bool) $value;
    }
}
