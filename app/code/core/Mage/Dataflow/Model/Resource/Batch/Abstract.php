<?php

/**
 * Maho
 *
 * @package    Mage_Dataflow
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

abstract class Mage_Dataflow_Model_Resource_Batch_Abstract extends Mage_Core_Model_Resource_Db_Abstract
{
    /**
     * Retrieve Id collection
     *
     * @return array
     */
    public function getIdCollection(Mage_Dataflow_Model_Batch_Abstract $object)
    {
        if (!$object->getBatchId()) {
            return [];
        }

        $ids = [];
        $select = $this->_getWriteAdapter()->select()
            ->from($this->getMainTable(), [$this->getIdFieldName()])
            ->where('batch_id = :batch_id');
        $ids = $this->_getWriteAdapter()->fetchCol($select, ['batch_id' => $object->getBatchId()]);
        return $ids;
    }

    /**
     * Delete current Batch collection
     *
     * @return Mage_Dataflow_Model_Resource_Batch_Abstract
     */
    public function deleteCollection(Mage_Dataflow_Model_Batch_Abstract $object)
    {
        if (!$object->getBatchId()) {
            return $this;
        }

        $this->_getWriteAdapter()->delete($this->getMainTable(), ['batch_id=?' => $object->getBatchId()]);
        return $this;
    }
}
