<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Eav
 */

class Mage_Eav_Model_Convert_Adapter_Grid extends Mage_Dataflow_Model_Convert_Adapter_Abstract
{
    protected $_entity;

    /**
     * @var string
     */
    protected $_entityType;

    /**
     * @return Mage_Eav_Model_Entity_Interface|null
     */
    public function getEntity()
    {
        if (!$this->_entity) {
            $entityType = $this->getVar('entity_type');
            $entity = $entityType ? Mage::getResourceSingleton($entityType) : null;
            if (!$entity instanceof Mage_Eav_Model_Entity_Interface) {
                $this->addException(Mage::helper('eav')->__('Invalid entity specified'), \Maho\Convert\Exception::FATAL);
                return null;
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
        if (!$this->getEntity()) {
            return $this;
        }
        try {
            $collection = Mage::getResourceModel($this->getVar('entity_type') . '_collection');
            $collection->load();
        } catch (Exception $e) {
            $this->addException(Mage::helper('eav')->__('An error occurred while loading the collection, aborting. Error: %s', $e->getMessage()), \Maho\Convert\Exception::FATAL);
            return $this;
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
        if (!$this->getEntity()) {
            return $this;
        }
        foreach ($this->getData() as $i => $row) {
            $this->setPosition('Line: ' . $i);
            $entity = Mage::getModel($this->getVar('entity_type'));
            if (!empty($row['entity_id'])) {
                try {
                    $entity->load($row['entity_id']);
                    $this->setPosition('Line: ' . $i . ', entity_id: ' . $row['entity_id']);
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
                $this->addException(Mage::helper('eav')->__('An error occurred while saving a record, aborting. Error: %s', $e->getMessage()), \Maho\Convert\Exception::FATAL);
            }
        }
        return $this;
    }
}
