<?php

/**
 * Maho
 *
 * @package    Mage_Directory
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Directory_Model_Resource_Region_Collection extends Mage_Core_Model_Resource_Db_Collection_Abstract
{
    /**
     * Locale region name table name
     *
     * @var string
     */
    protected $_regionNameTable;

    /**
     * Country table name
     *
     * @var string
     */
    protected $_countryTable;

    /**
     * Define main, country, locale region name tables
     *
     */
    #[\Override]
    protected function _construct()
    {
        $this->_init('directory/region');

        $this->_countryTable    = $this->getTable('directory/country');
        $this->_regionNameTable = $this->getTable('directory/country_region_name');

        $this->addOrder('name', Varien_Data_Collection::SORT_ORDER_ASC);
        $this->addOrder('default_name', Varien_Data_Collection::SORT_ORDER_ASC);
    }

    /**
     * Initialize select object
     *
     * @return $this
     */
    #[\Override]
    protected function _initSelect()
    {
        parent::_initSelect();
        $locale = Mage::app()->getLocale()->getLocaleCode();

        $this->addBindParam(':region_locale', $locale);
        $this->getSelect()->joinLeft(
            ['rname' => $this->_regionNameTable],
            'main_table.region_id = rname.region_id AND rname.locale = :region_locale',
            ['name'],
        );

        return $this;
    }

    /**
     * Filter by country_id
     *
     * @param string|array $countryId
     * @return $this
     */
    public function addCountryFilter($countryId)
    {
        if (!empty($countryId)) {
            if (is_array($countryId)) {
                $this->addFieldToFilter('main_table.country_id', ['in' => $countryId]);
            } else {
                $this->addFieldToFilter('main_table.country_id', $countryId);
            }
        }
        return $this;
    }

    /**
     * Filter by country code (ISO 3)
     *
     * @param string $countryCode
     * @return $this
     */
    public function addCountryCodeFilter($countryCode)
    {
        $this->getSelect()
            ->joinLeft(
                ['country' => $this->_countryTable],
                'main_table.country_id = country.country_id',
            )
            ->where('country.iso3_code = ?', $countryCode);

        return $this;
    }

    /**
     * Filter by Region code
     *
     * @param string|array $regionCode
     * @return $this
     */
    public function addRegionCodeFilter($regionCode)
    {
        if (!empty($regionCode)) {
            if (is_array($regionCode)) {
                $this->addFieldToFilter('main_table.code', ['in' => $regionCode]);
            } else {
                $this->addFieldToFilter('main_table.code', $regionCode);
            }
        }
        return $this;
    }

    /**
     * Filter by region name
     *
     * @param string|array $regionName
     * @return $this
     */
    public function addRegionNameFilter($regionName)
    {
        if (!empty($regionName)) {
            if (is_array($regionName)) {
                $this->addFieldToFilter('main_table.default_name', ['in' => $regionName]);
            } else {
                $this->addFieldToFilter('main_table.default_name', $regionName);
            }
        }
        return $this;
    }

    /**
     * Filter region by its code or name
     *
     * @param string|array $region
     * @return $this
     */
    public function addRegionCodeOrNameFilter($region)
    {
        if (!empty($region)) {
            $condition = is_array($region) ? ['in' => $region] : $region;
            $this->addFieldToFilter(['main_table.code', 'main_table.default_name'], [$condition, $condition]);
        }
        return $this;
    }

    /**
     * Convert collection items to select options array
     *
     * @return array
     */
    #[\Override]
    public function toOptionArray()
    {
        $options = $this->_toOptionArray('region_id', 'default_name', ['title' => 'default_name']);
        if (count($options) > 0) {
            array_unshift($options, [
                'title ' => null,
                'value' => '',
                'label' => Mage::helper('directory')->__('-- Please select --'),
            ]);
        }
        return $options;
    }
}
