<?php

/**
 * Maho
 *
 * @package    Mage_Eav
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * @method $this setExceptionLocation(string $string)
 */
class Mage_Eav_Model_Convert_Adapter_Grid extends Mage_Dataflow_Model_Convert_Adapter_Abstract
{
    protected $_entity;

    /**
     * @var string
     */
    protected $_entityType;

    /**
     * @return Mage_Eav_Model_Entity_Interface
     */
    public function getEntity()
    {
        if (!$this->_entityType) {
            if (!($entityType = $this->getVar('entity_type'))
                || !(($entity = Mage::getResourceSingleton($entityType)) instanceof Mage_Eav_Model_Entity_Interface)
            ) {
                $this->addException(Mage::helper('eav')->__('Invalid entity specified'), \Maho\Convert\Exception::FATAL);
            }
            $this->_entity = $entity;
        }
        return $this->_entity;
    }

    /**
     * @return $this
     */
    #[\Override]
    public function load()
    {
        try {
            $collection = Mage::getResourceModel($this->getEntity() . '_collection');
            $collection->load();
        } catch (Exception $e) {
            $this->addException(Mage::helper('eav')->__('An error occurred while loading the collection, aborting. Error: %s', $e->getMessage()), \Maho\Convert\Exception::FATAL);
        }

        $data = [];
        foreach ($collection->getIterator() as $entity) {
            $data[] = $entity->getData();
        }
        $this->setData($data);
        return $this;
    }

    /**
     * @return $this
     */
    #[\Override]
    public function save()
    {
        foreach ($this->getData() as $i => $row) {
            $this->setExceptionLocation('Line: ' . $i);
            $entity = Mage::getResourceModel($this->getEntity());
            if (!empty($row['entity_id'])) {
                try {
                    $entity->load($row['entity_id']);
                    $this->setPosition('Line: ' . $i . (isset($row['entity_id']) ? ', entity_id: ' . $row['entity_id'] : ''));
                } catch (Exception $e) {
                    $this->addException(Mage::helper('eav')->__('An error occurred while loading a record, aborting. Error: %s', $e->getMessage()), \Maho\Convert\Exception::FATAL);
                }
                if (!$entity->getId()) {
                    $this->addException(Mage::helper('eav')->__('Invalid entity_id, skipping the record.'), \Maho\Convert\Exception::ERROR);
                    continue;
                }
            }
            try {
                $entity->addData($row)->save();
            } catch (Exception $e) {
                $this->addException(Mage::helper('eav')->__('An error occurred while saving a record, aborting. Error: ', $e->getMessage()), \Maho\Convert\Exception::FATAL);
            }
        }
        return $this;
    }
}
