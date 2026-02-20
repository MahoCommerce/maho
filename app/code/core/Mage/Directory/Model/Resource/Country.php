<?php

/**
 * Maho
 *
 * @package    Mage_Directory
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Directory_Model_Resource_Country extends Mage_Core_Model_Resource_Db_Abstract
{
    protected $_isPkAutoIncrement = false;

    protected string $_countryNameTable;

    #[\Override]
    protected function _construct()
    {
        $this->_init('directory/country', 'country_id');
        $this->_countryNameTable = $this->getTable('directory/country_name');
    }

    /**
     * Retrieve select object for load object data
     *
     * @param string $field
     * @param mixed $value
     * @param Mage_Core_Model_Abstract $object
     *
     * @return Maho\Db\Select
     */
    #[\Override]
    protected function _getLoadSelect($field, $value, $object)
    {
        $select  = parent::_getLoadSelect($field, $value, $object);
        $adapter = $this->_getReadAdapter();

        $locale       = Mage::app()->getLocale()->getLocaleCode();
        $systemLocale = Mage::app()->getDistroLocaleCode();

        $countryField = $adapter->quoteIdentifier($this->getMainTable() . '.' . $this->getIdFieldName());

        $condition = $adapter->quoteInto('lcn.locale = ?', $locale);
        $select->joinLeft(
            ['lcn' => $this->_countryNameTable],
            "{$countryField} = lcn.country_id AND {$condition}",
            [],
        );

        if ($locale != $systemLocale) {
            $nameExpr  = $adapter->getCheckSql('lcn.country_id is null', 'scn.name', 'lcn.name');
            $condition = $adapter->quoteInto('scn.locale = ?', $systemLocale);
            $select->joinLeft(
                ['scn' => $this->_countryNameTable],
                "{$countryField} = scn.country_id AND {$condition}",
                ['name' => $nameExpr],
            );
        } else {
            $select->columns(['name'], 'lcn');
        }

        return $select;
    }

    /**
     * Load country by ISO code
     *
     * @param string $code
     *
     * @throws Mage_Core_Exception
     * @return $this
     */
    public function loadByCode(Mage_Directory_Model_Country $country, $code)
    {
        switch (strlen($code)) {
            case 2:
                $field = 'iso2_code';
                break;

            case 3:
                $field = 'iso3_code';
                break;

            default:
                Mage::throwException(Mage::helper('directory')->__('Invalid country code: %s', $code));
        }

        return $this->load($country, $code, $field);
    }

    /**
     * Return collection of translated country names
     */
    public function getTranslationCollection(?Mage_Directory_Model_Country $country = null): \Maho\Data\Collection\Db
    {
        $collection = new \Maho\Data\Collection\Db($this->_getReadAdapter());

        $collection->getSelect()
            ->from(
                ['cname' => $this->_countryNameTable],
                ['locale', 'name'],
            )
            ->joinLeft(
                ['country' => $this->getMainTable()],
                'cname.country_id = country.country_id',
                ['country_id', 'iso2_code', 'iso3_code'],
            )
            ->columns([
                'id' => new Maho\Db\Expr("CONCAT(cname.country_id, '|', cname.locale)"),
            ]);

        if ($country) {
            $collection->getSelect()
                ->where('country.country_id = ?', $country->getCountryId());
        }

        return $collection;
    }

    /**
     * Check if country has at least one translation
     */
    public function hasTranslation(Mage_Directory_Model_Country $country): bool
    {
        $select = $this->_getReadAdapter()->select()
            ->from($this->_countryNameTable, 'COUNT(*)')
            ->where('country_id = ?', $country->getCountryId())
            ->columns([
                'id' => new Maho\Db\Expr("CONCAT(country_id, '|', locale)"),
            ]);

        return (int) $this->_getReadAdapter()->fetchOne($select) > 0;
    }

    public function getTranslation(Mage_Directory_Model_Country $country, string $locale): \Maho\DataObject
    {
        $select = $this->_getReadAdapter()->select()
            ->from($this->_countryNameTable)
            ->where('country_id = ?', $country->getId())
            ->where('locale = ?', $locale);

        return new \Maho\DataObject($this->_getReadAdapter()->fetchRow($select));
    }

    public function insertOrUpdateTranslation(Mage_Directory_Model_Country $country, array $data): bool
    {
        $data = array_intersect_key($data, array_flip(['locale', 'name']));
        $data['country_id'] = $country->getCountryId();

        return (bool) $this->_getWriteAdapter()
            ->insertOnDuplicate($this->_countryNameTable, $data, ['name']);
    }

    public function deleteTranslation(Mage_Directory_Model_Country $country, string $locale): bool
    {
        return (bool) $this->_getWriteAdapter()
            ->delete($this->_countryNameTable, [
                'country_id = ?' => $country->getCountryId(),
                'locale = ?' => $locale,
            ]);
    }
}
