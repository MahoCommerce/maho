<?php

/**
 * Maho
 *
 * @package    Mage_Directory
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Directory Country Resource Collection
 *
 * @package    Mage_Directory
 *
 * @property Mage_Directory_Model_Country[] $_items
 * @method  Mage_Directory_Model_Country getFirstItem()
 * @method  Mage_Directory_Model_Country getLastItem()
 */
class Mage_Directory_Model_Resource_Country_Collection extends Mage_Core_Model_Resource_Db_Collection_Abstract
{
    protected string $_countryNameTable;

    #[\Override]
    protected function _construct()
    {
        $this->_init('directory/country');
        $this->_countryNameTable = $this->getTable('directory/country_name');
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

        // Try to get English names as fallback for grid display
        $this->getSelect()->joinLeft(
            ['cname' => $this->_countryNameTable],
            $this->getConnection()->quoteInto('main_table.country_id = cname.country_id AND cname.locale = ?', 'en_US'),
            ['name'],
        );

        $this->getSelect()->order(['name', 'country_id']);

        return $this;
    }

    /**
     * Get Store Config
     *
     * @param string $path
     * @param mixed|null $store
     * @return string
     */
    protected function _getStoreConfig($path, $store = null)
    {
        return Mage::getStoreConfig($path, $store);
    }

    /**
     * Load allowed countries for specific store
     *
     * @param mixed $store
     * @return $this
     */
    public function loadByStore($store = null)
    {
        $allowCountries = explode(',', (string) $this->_getStoreConfig('general/country/allow', $store));
        if (!empty($allowCountries)) {
            $this->addFieldToFilter('main_table.country_id', ['in' => $allowCountries]);
        }
        return $this;
    }

    /**
     * Loads Item By Id
     *
     * @param string $countryId
     * @return Mage_Directory_Model_Resource_Country|Mage_Directory_Model_Country
     */
    #[\Override]
    public function getItemById($countryId)
    {
        foreach ($this->_items as $country) {
            if ($country->getCountryId() == $countryId) {
                return $country;
            }
        }
        return Mage::getResourceModel('directory/country');
    }

    /**
     * Add filter by country code to collection.
     * $countryCode can be either array of country codes or string representing one country code.
     * $iso can be either array containing 'iso2', 'iso3' values or string with containing one of that values directly.
     * The collection will contain countries where at least one of country $iso fields matches $countryCode.
     *
     * @param string|array $countryCode
     * @param string|array $iso
     * @return $this
     */
    public function addCountryCodeFilter($countryCode, $iso = ['iso3', 'iso2'])
    {
        if (!empty($countryCode)) {
            if (is_array($countryCode)) {
                if (is_array($iso)) {
                    $whereOr = [];
                    foreach ($iso as $isoType) {
                        $whereOr[] = $this->_getConditionSql("{$isoType}_code", ['in' => $countryCode]);
                    }
                    $this->_select->where('(' . implode(') OR (', $whereOr) . ')');
                } else {
                    $this->addFieldToFilter("{$iso}_code", ['in' => $countryCode]);
                }
            } else {
                if (is_array($iso)) {
                    $whereOr = [];
                    foreach ($iso as $isoType) {
                        $whereOr[] = $this->_getConditionSql("{$isoType}_code", $countryCode);
                    }
                    $this->_select->where('(' . implode(') OR (', $whereOr) . ')');
                } else {
                    $this->addFieldToFilter("{$iso}_code", $countryCode);
                }
            }
        }
        return $this;
    }

    /**
     * Add filter by country code(s) to collection
     *
     * @param string|array $countryId
     * @return $this
     */
    public function addCountryIdFilter($countryId)
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

    #[\Override]
    public function toOptionHash(): array
    {
        $res = [];

        foreach ($this as $countryId => $country) {
            $res[$countryId] = $country->getName();
        }

        Mage::helper('core/string')->sortMultibyte($res, true);
        return $res;
    }

    #[\Override]
    public function toOptionArray(bool $addEmpty = true): array
    {
        $res = $this->toOptionHash();
        $options = [];

        foreach ($res as $countryId => $name) {
            $options[] = [
                'title' => $name,
                'value' => $countryId,
                'label' => $name,
            ];
        }

        if (count($options) > 0 && $addEmpty) {
            array_unshift($options, [
                'title ' => null,
                'value' => '',
                'label' => '',
            ]);
        }

        return $options;
    }
}
