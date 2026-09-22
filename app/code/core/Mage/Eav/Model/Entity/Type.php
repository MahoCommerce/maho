<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2025 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Eav
 */

/**
 * @method Mage_Eav_Model_Resource_Entity_Type _getResource()
 * @method Mage_Eav_Model_Resource_Entity_Type getResource()
 * @method Mage_Eav_Model_Resource_Entity_Type_Collection getCollection()
 */
class Mage_Eav_Model_Entity_Type extends Mage_Core_Model_Abstract
{
    /**
     * Collection of attributes
     *
     * @var Mage_Eav_Model_Resource_Entity_Attribute_Collection
     */
    protected $_attributes;

    /**
     * Array of attributes
     *
     * @var array
     */
    protected $_attributesBySet             = [];

    /**
     * Collection of sets
     *
     * @var Mage_Eav_Model_Resource_Entity_Attribute_Set_Collection|null
     */
    protected $_sets;

    #[\Override]
    protected function _construct()
    {
        $this->_init('eav/entity_type');
    }

    /**
     * Load type by code
     *
     * @param string $code
     * @return $this
     */
    public function loadByCode($code)
    {
        $this->_getResource()->loadByCode($this, $code);
        $this->_afterLoad();
        return $this;
    }

    /**
     * Retrieve entity type attributes collection
     *
     * @param int|null $setId
     * @return Mage_Eav_Model_Resource_Entity_Attribute_Collection
     */
    public function getAttributeCollection($setId = null)
    {
        if ($setId === null && $this->_attributes !== null) {
            return $this->_attributes;
        }
        if ($setId !== null && isset($this->_attributesBySet[$setId])) {
            return $this->_attributesBySet[$setId];
        }

        $collection = $this->newAttributeCollection($setId);

        if ($setId === null) {
            $this->_attributes = $collection;
        } else {
            $this->_attributesBySet[$setId] = $collection;
        }

        return $collection;
    }

    /**
     * Create entity type attributes collection
     *
     * @param int|null $setId
     * @return Mage_Eav_Model_Resource_Entity_Attribute_Collection
     */
    public function newAttributeCollection($setId = null)
    {
        $collection = $this->_getAttributeCollection()
            ->setEntityTypeFilter($this);

        if ($setId !== null) {
            $collection->setAttributeSetFilter($setId);
        }

        return $collection;
    }

    /**
     * Init and retrieve attribute collection
     *
     * @return Mage_Core_Model_Resource_Db_Collection_Abstract|object
     */
    protected function _getAttributeCollection()
    {
        $collectionClass = $this->getEntityAttributeCollection();
        $collection = Mage::getResourceModel($collectionClass);
        $objectsModel = $this->getAttributeModel();
        if ($objectsModel) {
            $collection->setModel($objectsModel);
        }

        return $collection;
    }

    /**
     * Retrieve entity tpe sets collection
     *
     * @return Mage_Eav_Model_Resource_Entity_Attribute_Set_Collection
     */
    public function getAttributeSetCollection()
    {
        if (empty($this->_sets)) {
            $this->_sets = Mage::getModel('eav/entity_attribute_set')->getResourceCollection()
                ->setEntityTypeFilter($this->getId());
        }
        return $this->_sets;
    }

    /**
     * Retrieve new incrementId
     *
     * @param int $storeId
     * @return false|string
     * @throws Exception
     */
    public function fetchNewIncrementId($storeId = null)
    {
        if (!$this->getIncrementModel()) {
            return false;
        }

        if (!$this->getIncrementPerStore() || ($storeId === null)) {
            /**
             * store_id null we can have for entity from removed store
             */
            $storeId = 0;
        }

        // The allocation needs a transaction of its own, so that the locking read
        // creates the read view itself. Nested in an entity save, it would join the
        // read view of that save, and MariaDB innodb_snapshot_isolation would abort
        // the save with ER_CHECKREAD (1020).
        return Mage::getSingleton('core/resource')->runOutsideTransaction(
            fn($connection) => $this->_allocateIncrementId($connection, $storeId),
        );
    }

    /**
     * Allocate the next increment id on the given connection.
     *
     * Uses plain SQL, because the eav/entity_store model is bound to the shared
     * write connection.
     *
     * @param Maho\Db\Adapter\AdapterInterface $connection
     * @param int $storeId
     * @return string
     * @throws Exception
     */
    protected function _allocateIncrementId($connection, $storeId)
    {
        $table = Mage::getSingleton('core/resource')->getTableName('eav/entity_store');
        $entityTypeId = (int) $this->getId();
        $storeId = (int) $storeId;
        $where = [
            'entity_type_id = ?' => $entityTypeId,
            'store_id = ?' => $storeId,
        ];

        $connection->beginTransaction();
        try {
            $select = $connection->select()
                ->from($table)
                ->forUpdate(true)
                ->where('entity_type_id = ?', $entityTypeId)
                ->where('store_id = ?', $storeId);
            $row = $connection->fetchRow($select);

            if (!$row) {
                $connection->insert($table, [
                    'entity_type_id' => $entityTypeId,
                    'store_id' => $storeId,
                    'increment_prefix' => $storeId,
                ]);
                $row = $connection->fetchRow($select);
            }

            $incrementId = Mage::getModel($this->getIncrementModel())
                ->setPrefix($row['increment_prefix'])
                ->setPadLength($this->getIncrementPadLength())
                ->setPadChar($this->getIncrementPadChar())
                ->setLastId($row['increment_last_id'])
                ->setEntityTypeId($row['entity_type_id'])
                ->setStoreId($row['store_id'])
                ->getNextId();

            $connection->update($table, ['increment_last_id' => $incrementId], $where);
            $connection->commit();
        } catch (Exception $e) {
            $connection->rollBack();
            throw $e;
        }

        return $incrementId;
    }

    /**
     * Retrieve entity id field
     *
     * @return string|null
     */
    public function getEntityIdField()
    {
        return $this->_data['entity_id_field'] ?? null;
    }

    /**
     * Retrieve entity table name
     *
     * @return string|null
     */
    public function getEntityTable()
    {
        return $this->_data['entity_table'] ?? null;
    }

    /**
     * Retrieve entity table prefix name
     *
     * @return string|null
     */
    public function getValueTablePrefix()
    {
        $prefix = $this->getEntityTablePrefix();
        if ($prefix) {
            return $this->getResource()->getTable($prefix);
        }

        return null;
    }

    /**
     * Retrieve entity table prefix
     *
     * @return string
     */
    public function getEntityTablePrefix()
    {
        $tablePrefix = trim((string) $this->_data['value_table_prefix']);

        if (empty($tablePrefix)) {
            $tablePrefix = $this->getEntityTable();
        }

        return $tablePrefix;
    }

    /**
     * Get default attribute set identifier for entity type
     *
     * @return int|null
     */
    public function getDefaultAttributeSetId()
    {
        return isset($this->_data['default_attribute_set_id']) ? (int) $this->_data['default_attribute_set_id'] : null;
    }

    /**
     * Retrieve entity type id
     *
     * @return int|null
     */
    public function getEntityTypeId()
    {
        return isset($this->_data['entity_type_id']) ? (int) $this->_data['entity_type_id'] : null;
    }

    /**
     * Retrieve entity type code
     *
     * @return string|null
     */
    public function getEntityTypeCode()
    {
        return $this->_data['entity_type_code'] ?? null;
    }

    /**
     * Retrieve attribute codes
     *
     * @return array|null
     */
    public function getAttributeCodes()
    {
        return $this->_data['attribute_codes'] ?? null;
    }

    /**
     * Get attribute model code for entity type
     *
     * @return string
     */
    public function getAttributeModel()
    {
        if (empty($this->_data['attribute_model'])) {
            return Mage_Eav_Model_Entity::DEFAULT_ATTRIBUTE_MODEL;
        }

        return $this->_data['attribute_model'];
    }

    /**
     * Retrieve resource entity object
     *
     * @return Mage_Eav_Model_Entity_Abstract
     */
    public function getEntity()
    {
        $entity = Mage::getResourceSingleton($this->_data['entity_model']);
        if (!$entity instanceof Mage_Eav_Model_Entity_Abstract) {
            throw new Mage_Core_Exception('Invalid entity model');
        }
        return $entity;
    }

    /**
     * Return attribute collection. If not specify return default
     *
     * @return string
     */
    public function getEntityAttributeCollection()
    {
        $collection = $this->_getData('entity_attribute_collection');
        if ($collection) {
            return $collection;
        }
        return 'eav/entity_attribute_collection';
    }

    public function getAdditionalAttributeTable(): ?string
    {
        $value = $this->getData('additional_attribute_table');
        return $value === null ? null : (string) $value;
    }

    public function setAdditionalAttributeTable(?string $value): static
    {
        return $this->setData('additional_attribute_table', $value);
    }

    public function setAttributeCodes(?array $value): static
    {
        return $this->setData('attribute_codes', $value);
    }

    public function setAttributeModel(?string $value): static
    {
        return $this->setData('attribute_model', $value);
    }

    public function getDataSharingKey(): ?string
    {
        $value = $this->getData('data_sharing_key');
        return $value === null ? null : (string) $value;
    }

    public function setDataSharingKey(?string $value): static
    {
        return $this->setData('data_sharing_key', $value);
    }

    public function setDefaultAttributeSetId(?int $value): static
    {
        return $this->setData('default_attribute_set_id', $value);
    }

    public function setEntityAttributeCollection(?string $value): static
    {
        return $this->setData('entity_attribute_collection', $value);
    }

    public function setEntityIdField(?string $value): static
    {
        return $this->setData('entity_id_field', $value);
    }

    public function getEntityModel(): ?string
    {
        $value = $this->getData('entity_model');
        return $value === null ? null : (string) $value;
    }

    public function setEntityModel(?string $value): static
    {
        return $this->setData('entity_model', $value);
    }

    public function setEntityTable(?string $value): static
    {
        return $this->setData('entity_table', $value);
    }

    public function setEntityTypeCode(?string $value): static
    {
        return $this->setData('entity_type_code', $value);
    }

    public function getIncrementModel(): ?string
    {
        $value = $this->getData('increment_model');
        return $value === null ? null : (string) $value;
    }

    public function setIncrementModel(?string $value): static
    {
        return $this->setData('increment_model', $value);
    }

    public function getIncrementPadChar(): ?string
    {
        $value = $this->getData('increment_pad_char');
        return $value === null ? null : (string) $value;
    }

    public function setIncrementPadChar(?string $value): static
    {
        return $this->setData('increment_pad_char', $value);
    }

    public function getIncrementPadLength(): ?int
    {
        $value = $this->getData('increment_pad_length');
        return $value === null ? null : (int) $value;
    }

    public function setIncrementPadLength(?int $value): static
    {
        return $this->setData('increment_pad_length', $value);
    }

    public function getIncrementPerStore(): ?bool
    {
        $value = $this->getData('increment_per_store');
        return $value === null ? null : (bool) $value;
    }

    public function setIncrementPerStore(?bool $value = true): static
    {
        return $this->setData('increment_per_store', $value);
    }

    public function getIsDataSharing(): ?bool
    {
        $value = $this->getData('is_data_sharing');
        return $value === null ? null : (bool) $value;
    }

    public function setIsDataSharing(?bool $value = true): static
    {
        return $this->setData('is_data_sharing', $value);
    }

    public function setValueTablePrefix(?string $value): static
    {
        return $this->setData('value_table_prefix', $value);
    }

}
