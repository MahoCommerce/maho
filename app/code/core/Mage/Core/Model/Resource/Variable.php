<?php

/**
 * Maho
 *
 * @package    Mage_Core
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Core_Model_Resource_Variable extends Mage_Core_Model_Resource_Db_Abstract
{
    #[\Override]
    protected function _construct()
    {
        $this->_init('core/variable', 'variable_id');
    }

    /**
     * Load variable by code
     *
     * @param string $code
     * @return $this
     */
    public function loadByCode(Mage_Core_Model_Variable $object, $code)
    {
        if ($result = $this->getVariableByCode($code, true, $object->getStoreId())) {
            $object->setData($result);
        }
        return $this;
    }

    /**
     * Retrieve variable data by code
     *
     * @param string $code
     * @param bool $withValue
     * @param int $storeId
     * @return array
     */
    public function getVariableByCode($code, $withValue = false, $storeId = 0)
    {
        $select = $this->_getReadAdapter()->select()
            ->from($this->getMainTable())
            ->where($this->getMainTable() . '.code = ?', $code);
        if ($withValue) {
            $this->_addValueToSelect($select, $storeId);
        }
        return $this->_getReadAdapter()->fetchRow($select);
    }

    /**
     * @param Mage_Core_Model_Variable $object
     */
    #[\Override]
    protected function _afterSave(Mage_Core_Model_Abstract $object)
    {
        parent::_afterSave($object);
        if ($object->getUseDefaultValue()) {
            /*
             * remove store value
             */
            $this->_getWriteAdapter()->delete(
                $this->getTable('core/variable_value'),
                [
                    'variable_id = ?' => $object->getId(),
                    'store_id = ?' => $object->getStoreId(),
                ],
            );
        } else {
            $data =  [
                'variable_id' => $object->getId(),
                'store_id'    => $object->getStoreId(),
                'plain_value' => $object->getPlainValue(),
                'html_value'  => $object->getHtmlValue(),
            ];
            $data = $this->_prepareDataForTable(new Varien_Object($data), $this->getTable('core/variable_value'));
            $this->_getWriteAdapter()->insertOnDuplicate(
                $this->getTable('core/variable_value'),
                $data,
                ['plain_value', 'html_value'],
            );
        }
        return $this;
    }

    /**
     * @param Mage_Core_Model_Variable $object
     */
    #[\Override]
    protected function _getLoadSelect($field, $value, $object)
    {
        $select = parent::_getLoadSelect($field, $value, $object);
        $this->_addValueToSelect($select, $object->getStoreId());
        return $select;
    }

    /**
     * Add variable store and default value to select
     *
     * @param int $storeId
     * @return $this
     */
    protected function _addValueToSelect(Zend_Db_Select $select, $storeId = Mage_Core_Model_App::ADMIN_STORE_ID)
    {
        $adapter = $this->_getReadAdapter();
        $ifNullPlainValue = $adapter->getCheckSql('store.plain_value IS NULL', 'def.plain_value', 'store.plain_value');
        $ifNullHtmlValue  = $adapter->getCheckSql('store.html_value IS NULL', 'def.html_value', 'store.html_value');

        $select->joinLeft(
            ['def' => $this->getTable('core/variable_value')],
            'def.variable_id = ' . $this->getMainTable() . '.variable_id AND def.store_id = 0',
            [],
        )
            ->joinLeft(
                ['store' => $this->getTable('core/variable_value')],
                'store.variable_id = def.variable_id AND store.store_id = ' . $adapter->quote($storeId),
                [],
            )
            ->columns([
                'plain_value'       => $ifNullPlainValue,
                'html_value'        => $ifNullHtmlValue,
                'store_plain_value' => 'store.plain_value',
                'store_html_value'  => 'store.html_value',
            ]);

        return $this;
    }
}
