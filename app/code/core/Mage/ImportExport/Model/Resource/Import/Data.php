<?php

/**
 * Maho
 *
 * @package    Mage_ImportExport
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_ImportExport_Model_Resource_Import_Data extends Mage_Core_Model_Resource_Db_Abstract implements IteratorAggregate
{
    /**
     * @var Iterator<int, array>|null
     */
    protected $_iterator = null;

    #[\Override]
    protected function _construct()
    {
        $this->_init('importexport/importdata', 'id');
    }

    /**
     * Retrieve an external iterator
     *
     * @return Iterator<int, array>
     */
    #[\ReturnTypeWillChange]
    #[\Override]
    public function getIterator()
    {
        $adapter = $this->_getWriteAdapter();
        $select = $adapter->select()
            ->from($this->getMainTable(), ['data'])
            ->order('id ASC');
        $result = $adapter->query($select);

        // Doctrine DBAL Result objects support iteration directly
        if ($result instanceof IteratorAggregate) {
            $iterator = $result->getIterator();
            // Ensure we return an Iterator, not just Traversable
            if (!$iterator instanceof Iterator) {
                $iterator = new IteratorIterator($iterator);
            }
        } else {
            // For Varien statements, fetch all records as numeric arrays (fetch mode 3)
            $rows = $result->fetchAll(3);
            $iterator = new ArrayIterator($rows);
        }

        return $iterator;
    }

    /**
     * Clean all bunches from table.
     *
     * @return int
     */
    public function cleanBunches()
    {
        return $this->_getWriteAdapter()->delete($this->getMainTable());
    }

    /**
     * Return behavior from import data table.
     *
     * @throws Exception
     * @return string
     */
    public function getBehavior()
    {
        $adapter = $this->_getReadAdapter();
        $behaviors = array_unique($adapter->fetchCol(
            $adapter->select()
                ->from($this->getMainTable(), ['behavior']),
        ));
        if (count($behaviors) != 1) {
            Mage::throwException(Mage::helper('importexport')->__('Error in data structure: behaviors are mixed'));
        }
        return $behaviors[0];
    }

    /**
     * Return entity type code from import data table.
     *
     * @throws Exception
     * @return string
     */
    public function getEntityTypeCode()
    {
        $adapter = $this->_getReadAdapter();
        $entityCodes = array_unique($adapter->fetchCol(
            $adapter->select()
                ->from($this->getMainTable(), ['entity']),
        ));
        if (count($entityCodes) != 1) {
            Mage::throwException(Mage::helper('importexport')->__('Error in data structure: entity codes are mixed'));
        }
        return $entityCodes[0];
    }

    /**
     * Get next bunch of validated rows.
     *
     * @return array|null
     */
    public function getNextBunch()
    {
        if ($this->_iterator === null) {
            $this->_iterator = $this->getIterator();
            $this->_iterator->rewind();
        }
        if ($this->_iterator->valid()) {
            $dataRow = $this->_iterator->current();
            $dataRow = Mage::helper('core')->jsonDecode($dataRow[0]);
            $this->_iterator->next();
        } else {
            $this->_iterator = null;
            $dataRow = null;
        }
        return $dataRow;
    }

    /**
     * Save import rows bunch.
     *
     * @param string $entity
     * @param string $behavior
     * @return int
     */
    public function saveBunch($entity, $behavior, array $data)
    {
        return $this->_getWriteAdapter()->insert(
            $this->getMainTable(),
            ['behavior' => $behavior, 'entity' => $entity, 'data' => Mage::helper('core')->jsonEncode($data)],
        );
    }
}
