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
 * Country model
 *
 * @package    Mage_Directory
 *
 * @method Mage_Directory_Model_Resource_Country _getResource()
 * @method Mage_Directory_Model_Resource_Country getResource()
 * @method Mage_Directory_Model_Resource_Country_Collection getResourceCollection()
 * @method string getCode()
 * @method string getCountryId()
 * @method $this setCountryId(string $value)
 * @method string getIso2Code()
 * @method $this setIso2Code(string $value)
 * @method string getIso3Code()
 * @method $this setIso3Code(string $value)
 */
class Mage_Directory_Model_Country extends Mage_Core_Model_Abstract
{
    public static $_format = [];

    #[\Override]
    protected function _construct()
    {
        $this->_init('directory/country');
    }

    /**
     * @param string $code
     * @return $this
     * @throws Mage_Core_Exception
     */
    public function loadByCode($code)
    {
        $this->_getResource()->loadByCode($this, $code);
        return $this;
    }

    /**
     * @return Mage_Directory_Model_Resource_Region_Collection
     */
    public function getRegions()
    {
        return $this->getLoadedRegionCollection();
    }

    /**
     * @return Mage_Directory_Model_Resource_Region_Collection
     */
    public function getLoadedRegionCollection()
    {
        $collection = $this->getRegionCollection();
        $collection->load();
        return $collection;
    }

    /**
     * @return Mage_Directory_Model_Resource_Region_Collection
     */
    public function getRegionCollection()
    {
        $collection = Mage::getResourceModel('directory/region_collection');
        $collection->addCountryFilter($this->getId());
        return $collection;
    }

    /**
     * @param bool $html
     * @return string
     */
    public function formatAddress(\Maho\DataObject $address, $html = false)
    {
        //TODO: is it still used?
        $address->getRegion();
        $address->getCountry();

        $template = $this->getData('address_template_' . ($html ? 'html' : 'plain'));
        if (empty($template)) {
            if (!$this->getId()) {
                $template = '{{firstname}} {{lastname}}';
            } elseif (!$html) {
                $template = '{{firstname}} {{lastname}}
{{company}}
{{street1}}
{{street2}}
{{city}}, {{region}} {{postcode}}';
            } else {
                $template = '{{firstname}} {{lastname}}<br/>
{{street}}<br/>
{{city}}, {{region}} {{postcode}}<br/>
T: {{telephone}}';
            }
        }

        $filter = new \Maho\Filter\Template\Simple();
        $addressText = $filter->setData($address->getData())->filter($template);

        if ($html) {
            $addressText = preg_replace('#(<br\s*/?>\s*){2,}#im', '<br/>', $addressText);
        } else {
            $addressText = preg_replace('#(\n\s*){2,}#m', "\n", $addressText);
        }

        return $addressText;
    }

    /**
     * Retrieve formats for
     *
     * @return Mage_Directory_Model_Resource_Country_Format_Collection
     */
    public function getFormats()
    {
        if (!isset(self::$_format[$this->getId()]) && $this->getId()) {
            self::$_format[$this->getId()] = Mage::getModel('directory/country_format')
                ->getCollection()
                ->setCountryFilter($this)
                ->load();
        }

        return self::$_format[$this->getId()] ?? null;
    }

    /**
     * Retrieve format
     *
     * @param string $type
     * @return Mage_Directory_Model_Country_Format|null
     */
    public function getFormat($type)
    {
        if ($this->getFormats()) {
            foreach ($this->getFormats() as $format) {
                if ($format->getType() == $type) {
                    return $format;
                }
            }
        }
        return null;
    }

    /**
     * @return string
     */
    public function getName()
    {
        // Always use ICU data for country names to ensure proper localization
        $countryId = $this->getData('country_id');
        if ($countryId) {
            $locale = Mage::app()->getLocale();
            $name = $locale->getTranslation($countryId, 'country');
            if ($name && $name !== $countryId) {
                return $name;
            }
        }

        // Fallback to database name or country_id
        $name = $this->getData('name');
        if (is_null($name)) {
            $name = $countryId ?: '';
        }
        return $name;
    }

    public function validate(): array|true
    {
        $errors = [];

        $requireIsoCodes = false;

        // Validate Country ID
        if (empty($this->getCountryId())) {
            $errors[] = Mage::helper('directory')->__('Country ID is required.');
        } elseif (!preg_match('/^[A-Z]{2}$/', $this->getCountryId())) {
            $errors[] = Mage::helper('directory')->__('Country ID must be exactly 2 uppercase letters.');
        }

        // Validate ISO2 code
        if (empty($this->getIso2Code())) {
            if ($requireIsoCodes) { // @phpstan-ignore if.alwaysFalse
                $errors[] = Mage::helper('directory')->__('ISO2 code is required.');
            }
        } elseif (!preg_match('/^[A-Z]{2}$/', $this->getIso2Code())) {
            $errors[] = Mage::helper('directory')->__('ISO2 code must be exactly 2 uppercase letters.');
        }

        // Validate ISO3 code
        if (empty($this->getIso3Code())) {
            if ($requireIsoCodes) { // @phpstan-ignore if.alwaysFalse
                $errors[] = Mage::helper('directory')->__('ISO3 code is required.');
            }
        } elseif (!preg_match('/^[A-Z]{3}$/', $this->getIso3Code())) {
            $errors[] = Mage::helper('directory')->__('ISO3 code must be exactly 3 uppercase letters.');
        }

        // Check for duplicate country ID (only for new countries)
        if (!$this->getOrigData('country_id') && !empty($this->getCountryId())) {
            $existingCountry = Mage::getModel('directory/country')->load($this->getCountryId());
            if ($existingCountry->getId()) {
                $errors[] = Mage::helper('directory')->__('A country with this ID already exists.');
            }
        }

        if (empty($errors)) {
            return true;
        }
        return $errors;
    }

}
