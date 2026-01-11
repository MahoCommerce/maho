<?php

/**
 * Maho
 *
 * @package    Mage_Directory
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * @package    Mage_Directory
 *
 * @method Mage_Directory_Model_Resource_Region _getResource()
 * @method Mage_Directory_Model_Resource_Region getResource()
 * @method Mage_Directory_Model_Resource_Region_Collection getCollection()
 * @method Mage_Directory_Model_Resource_Region_Collection getResourceCollection()
 *
 * @method string getCode()
 * @method $this setCode(string $value)
 * @method string getCountryId()
 * @method $this setCountryId(string $value)
 * @method string getDefaultName()
 * @method $this setDefaultName(string $value)
 * @method int getRegionId()
 */
class Mage_Directory_Model_Region extends Mage_Core_Model_Abstract
{
    #[\Override]
    protected function _construct()
    {
        $this->_init('directory/region');
    }

    /**
     * Retrieve region name
     *
     * If name is no declared, then default_name is used
     *
     * @return string
     */
    public function getName()
    {
        $name = $this->getData('name');
        if (is_null($name)) {
            $name = $this->getData('default_name');
        }
        return $name;
    }

    /**
     * @param string $code
     * @param string $countryId
     * @return $this
     */
    public function loadByCode($code, $countryId)
    {
        if ($code) {
            $this->_getResource()->loadByCode($this, $code, $countryId);
        }
        return $this;
    }

    /**
     * @param string $name
     * @param string $countryId
     * @return $this
     */
    public function loadByName($name, $countryId)
    {
        $this->_getResource()->loadByName($this, $name, $countryId);
        return $this;
    }

    public function validate(): array|true
    {
        $errors = [];

        // Validate country ID
        if (empty($this->getCountryId())) {
            $errors[] = Mage::helper('directory')->__('Country is required.');
        } else {
            // Check if country exists
            $country = Mage::getModel('directory/country')->load($this->getCountryId());
            if (!$country->getId()) {
                $errors[] = Mage::helper('directory')->__('Selected country does not exist.');
            }
        }

        // Validate default name
        if (empty($this->getDefaultName())) {
            $errors[] = Mage::helper('directory')->__('Default name is required.');
        } elseif (strlen($this->getDefaultName()) > 255) {
            $errors[] = Mage::helper('directory')->__('Default name cannot be longer than 255 characters.');
        }

        // Validate code if provided
        if (!empty($this->getCode()) && strlen($this->getCode()) > 32) {
            $errors[] = Mage::helper('directory')->__('Region code cannot be longer than 32 characters.');
        }

        // Check for duplicate region code within the same country
        if (!$this->getId() && !empty($this->getCode()) && !empty($this->getCountryId())) {
            $region = Mage::getModel('directory/region')->loadByCode($this->getCode(), $this->getCountryId());
            if ($region->getId()) {
                $errors[] = Mage::helper('directory')->__('A region with code "%s" already exists in this country.', $this->getCode());
            }
        }

        if (empty($errors)) {
            return true;
        }
        return $errors;
    }

    /**
     * Return collection of translated region names
     */
    public function getTranslationCollection(): \Maho\Data\Collection\Db
    {
        return $this->_getResource()->getTranslationCollection($this);
    }

    /**
     * Check if region has at least one translation
     */
    public function hasTranslation(): bool
    {
        return $this->_getResource()->hasTranslation($this);
    }

    public function validateTranslation(array $data): array|true
    {
        $errors = [];

        // Validate locale
        if (empty($data['locale'])) {
            $errors[] = Mage::helper('directory')->__('Locale is required.');
        }

        // Validate name
        if (empty($data['name'])) {
            $errors[] = Mage::helper('directory')->__('Region name is required.');
        } elseif (strlen($data['name']) > 255) {
            $errors[] = Mage::helper('directory')->__('Region name cannot be longer than 255 characters.');
        }

        // Check for duplicate locale/region combination
        if (!empty($data['locale'])) {
            $existingRegion = $this->_getResource()->getTranslation($this, $data['locale']);
            if ($existingRegion->getLocale()) {
                $errors[] = Mage::helper('directory')->__('A region name for this locale and region combination already exists.');
            }
        }

        if (empty($errors)) {
            return true;
        }
        return $errors;
    }

    public function saveTranslation(array $data): bool
    {
        if (empty($data['locale']) || empty($data['name'])) {
            Mage::throwException(Mage::helper('directory')->__('Locale and region name are required.'));
        }
        return $this->_getResource()->insertOrUpdateTranslation($this, $data);
    }

    public function deleteTranslation(string $locale): bool
    {
        return $this->_getResource()->deleteTranslation($this, $locale);
    }
}
