<?php

/**
 * Maho
 *
 * @package    Mage_CatalogSearch
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_CatalogSearch_Model_Resource_Query extends Mage_Core_Model_Resource_Db_Abstract
{
    /**
     * Init resource data
     *
     */
    #[\Override]
    protected function _construct()
    {
        $this->_init('catalogsearch/search_query', 'query_id');
    }

    /**
     * Custom load model by search query string
     *
     * @param string $value
     * @return $this
     */
    public function loadByQuery(Mage_Core_Model_Abstract $object, $value)
    {
        $readAdapter = $this->_getReadAdapter();
        $select = $readAdapter->select();

        $synonymSelect = clone $select;
        $synonymSelect
            ->from($this->getMainTable())
            ->where('store_id = ?', $object->getStoreId());

        $querySelect = clone $synonymSelect;
        $querySelect->where('query_text = ?', $value);

        $synonymSelect->where('synonym_for = ?', $value);

        $select->union([$querySelect, "($synonymSelect)"], Zend_Db_Select::SQL_UNION_ALL)
            ->order('synonym_for ASC')
            ->limit(1);

        $data = $readAdapter->fetchRow($select);
        if ($data) {
            $object->setData($data);
            $this->_afterLoad($object);
        }

        return $this;
    }

    /**
     * Custom load model only by query text (skip synonym for)
     *
     * @param string $value
     * @return $this
     */
    public function loadByQueryText(Mage_Core_Model_Abstract $object, $value)
    {
        $select = $this->_getReadAdapter()->select()
            ->from($this->getMainTable())
            ->where('query_text = ?', $value)
            ->where('store_id = ?', $object->getStoreId())
            ->limit(1);
        if ($data = $this->_getReadAdapter()->fetchRow($select)) {
            $object->setData($data);
            $this->_afterLoad($object);
        }
        return $this;
    }

    /**
     * Loading string as a value or regular numeric
     *
     * @param int|string $value
     * @param null|string $field
     */
    #[\Override]
    public function load(Mage_Core_Model_Abstract $object, $value, $field = null)
    {
        if (is_numeric($value)) {
            return parent::load($object, $value);
        } else {
            $this->loadByQuery($object, $value);
        }
        return $this;
    }

    /**
     * @return $this
     */
    #[\Override]
    public function _beforeSave(Mage_Core_Model_Abstract $object)
    {
        $object->setUpdatedAt($this->formatDate(Mage::getModel('core/date')->gmtTimestamp()));
        return $this;
    }
}
