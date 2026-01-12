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

class Mage_Directory_Model_Resource_Region extends Mage_Core_Model_Resource_Db_Abstract
{
    /**
     * Table with localized region names
     *
     * @var string
     */
    protected $_regionNameTable;

    /**
     * Define main and locale region name tables
     */
    #[\Override]
    protected function _construct()
    {
        $this->_init('directory/country_region', 'region_id');
        $this->_regionNameTable = $this->getTable('directory/country_region_name');
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

        $regionField = $adapter->quoteIdentifier($this->getMainTable() . '.' . $this->getIdFieldName());

        $condition = $adapter->quoteInto('lrn.locale = ?', $locale);
        $select->joinLeft(
            ['lrn' => $this->_regionNameTable],
            "{$regionField} = lrn.region_id AND {$condition}",
            [],
        );

        if ($locale != $systemLocale) {
            $nameExpr  = $adapter->getCheckSql('lrn.region_id is null', 'srn.name', 'lrn.name');
            $condition = $adapter->quoteInto('srn.locale = ?', $systemLocale);
            $select->joinLeft(
                ['srn' => $this->_regionNameTable],
                "{$regionField} = srn.region_id AND {$condition}",
                ['name' => $nameExpr],
            );
        } else {
            $select->columns(['name'], 'lrn');
        }

        return $select;
    }

    /**
     * Load object by country id and code or default name
     *
     * @param Mage_Core_Model_Abstract $object
     * @param string $countryId
     * @param string $value
     * @param string $field
     *
     * @return $this
     */
    protected function _loadByCountry($object, $countryId, $value, $field)
    {
        $adapter        = $this->_getReadAdapter();
        $locale         = Mage::app()->getLocale()->getLocaleCode();
        $joinCondition  = $adapter->quoteInto('rname.region_id = region.region_id AND rname.locale = ?', $locale);
        $select         = $adapter->select()
            ->from(['region' => $this->getMainTable()])
            ->joinLeft(
                ['rname' => $this->_regionNameTable],
                $joinCondition,
                ['name'],
            )
            ->where('region.country_id = ?', $countryId)
            ->where("region.{$field} = ?", $value);

        $data = $adapter->fetchRow($select);
        if ($data) {
            $object->setData($data);
        }

        $this->_afterLoad($object);

        return $this;
    }

    /**
     * Loads region by region code and country id
     *
     * @param string $regionCode
     * @param string $countryId
     * @return $this
     */
    public function loadByCode(Mage_Directory_Model_Region $region, $regionCode, $countryId)
    {
        return $this->_loadByCountry($region, $countryId, (string) $regionCode, 'code');
    }

    /**
     * Load data by country id and default region name
     *
     * @param string $regionName
     * @param string $countryId
     * @return $this
     */
    public function loadByName(Mage_Directory_Model_Region $region, $regionName, $countryId)
    {
        return $this->_loadByCountry($region, $countryId, (string) $regionName, 'default_name');
    }

    /**
     * Return collection of translated region names
     */
    public function getTranslationCollection(?Mage_Directory_Model_Region $region = null): \Maho\Data\Collection\Db
    {
        $collection = new \Maho\Data\Collection\Db($this->_getReadAdapter());

        $collection->getSelect()
            ->from(
                ['rname' => $this->_regionNameTable],
                ['locale', 'name'],
            )
            ->joinLeft(
                ['region' => $this->getMainTable()],
                'rname.region_id = region.region_id',
                ['region_id', 'country_id', 'code', 'default_name'],
            )
            ->columns([
                'id' => new Maho\Db\Expr("CONCAT(rname.region_id, '|', rname.locale)"),
            ]);

        if ($region) {
            $collection->getSelect()
                ->where('region.region_id = ?', $region->getRegionId());
        }

        return $collection;
    }

    /**
     * Check if region has at least one translation
     */
    public function hasTranslation(Mage_Directory_Model_Region $region): bool
    {
        $select = $this->_getReadAdapter()->select()
            ->from($this->_regionNameTable, 'COUNT(*)')
            ->where('region_id = ?', $region->getRegionId());

        return (int) $this->_getReadAdapter()->fetchOne($select) > 0;
    }

    public function getTranslation(Mage_Directory_Model_Region $region, string $locale): \Maho\DataObject
    {
        $select = $this->_getReadAdapter()->select()
            ->from($this->_regionNameTable)
            ->where('region_id = ?', $region->getRegionId())
            ->where('locale = ?', $locale)
            ->columns([
                'id' => new Maho\Db\Expr("CONCAT(region_id, '|', locale)"),
            ]);

        return new \Maho\DataObject($this->_getReadAdapter()->fetchRow($select));
    }

    public function insertOrUpdateTranslation(Mage_Directory_Model_Region $region, array $data): bool
    {
        $data = array_intersect_key($data, array_flip(['locale', 'name']));
        $data['region_id'] = $region->getRegionId();

        return (bool) $this->_getWriteAdapter()
            ->insertOnDuplicate($this->_regionNameTable, $data, ['name']);
    }

    public function deleteTranslation(Mage_Directory_Model_Region $region, string $locale): bool
    {
        return (bool) $this->_getWriteAdapter()
            ->delete($this->_regionNameTable, [
                'region_id = ?' => $region->getRegionId(),
                'locale = ?' => $locale,
            ]);
    }
}
