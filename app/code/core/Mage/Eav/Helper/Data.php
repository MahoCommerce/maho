<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Eav
 */

class Mage_Eav_Helper_Data extends Mage_Core_Helper_Abstract
{
    /**
     * XML path to input types validator data in config
     */
    public const XML_PATH_VALIDATOR_DATA_INPUT_TYPES = 'general/validator_data/input_types';

    #[\Override]
    protected $_moduleName = 'Mage_Eav';

    protected $_attributesLockedFields = [];

    protected $_entityTypeFrontendClasses = [];

    /**
     * Return default frontend classes value labal array
     *
     * @return array
     */
    protected function _getDefaultFrontendClasses()
    {
        return [
            [
                'value' => '',
                'label' => Mage::helper('eav')->__('None'),
            ],
            [
                'value' => 'validate-number',
                'label' => Mage::helper('eav')->__('Decimal Number'),
            ],
            [
                'value' => 'validate-digits',
                'label' => Mage::helper('eav')->__('Integer Number'),
            ],
            [
                'value' => 'validate-email',
                'label' => Mage::helper('eav')->__('Email'),
            ],
            [
                'value' => 'validate-url',
                'label' => Mage::helper('eav')->__('URL'),
            ],
            [
                'value' => 'validate-alpha',
                'label' => Mage::helper('eav')->__('Letters'),
            ],
            [
                'value' => 'validate-alphanum',
                'label' => Mage::helper('eav')->__('Letters (a-z, A-Z) or Numbers (0-9)'),
            ],
        ];
    }

    /**
     * Return merged default and entity type frontend classes value label array
     *
     * @param string $entityTypeCode
     * @return array
     */
    public function getFrontendClasses($entityTypeCode)
    {
        $_defaultClasses = $this->_getDefaultFrontendClasses();
        if (isset($this->_entityTypeFrontendClasses[$entityTypeCode])) {
            return array_merge(
                $_defaultClasses,
                $this->_entityTypeFrontendClasses[$entityTypeCode],
            );
        }
        $_entityTypeClasses = Mage::app()->getConfig()
            ->getNode('global/eav_frontendclasses/' . $entityTypeCode);
        if ($_entityTypeClasses) {
            foreach ($_entityTypeClasses->children() as $item) {
                $this->_entityTypeFrontendClasses[$entityTypeCode][] = [
                    'value' => (string) $item->value,
                    'label' => (string) $item->label,
                ];
            }
            return array_merge(
                $_defaultClasses,
                $this->_entityTypeFrontendClasses[$entityTypeCode],
            );
        }
        return $_defaultClasses;
    }

    /**
     * Retrieve attributes locked fields to edit
     *
     * @param string $entityTypeCode
     * @return array
     */
    public function getAttributeLockedFields($entityTypeCode)
    {
        if (!$entityTypeCode) {
            return [];
        }
        if (isset($this->_attributesLockedFields[$entityTypeCode])) {
            return $this->_attributesLockedFields[$entityTypeCode];
        }
        $_data = Mage::app()->getConfig()->getNode('global/eav_attributes/' . $entityTypeCode);
        if ($_data) {
            foreach ($_data->children() as $attribute) {
                $this->_attributesLockedFields[$entityTypeCode][(string) $attribute->code]
                    = array_keys($attribute->locked_fields->asArray());
            }
            return $this->_attributesLockedFields[$entityTypeCode];
        }
        return [];
    }

    /**
     * Get input types validator data
     *
     * @return array
     */
    public function getInputTypesValidatorData()
    {
        return Mage::getStoreConfig(self::XML_PATH_VALIDATOR_DATA_INPUT_TYPES);
    }

    /**
     * Return the attributes of an entity type that the pages of System > Attributes show: the
     * attributes that an admin added. The edit pages and the storefront templates of these types
     * place the system attributes themselves, so these pages have nothing to change on them.
     * A catalog type also leaves out the attributes with is_visible = 0.
     */
    public function getManagedAttributeCollection(Mage_Eav_Model_Entity_Type $entityType): Mage_Eav_Model_Resource_Entity_Attribute_Collection
    {
        /** @var Mage_Eav_Model_Resource_Entity_Attribute_Collection $collection */
        $collection = Mage::getResourceModel($entityType->getEntityAttributeCollection());
        $collection->setEntityTypeFilter((int) $entityType->getId())
            ->addFieldToFilter('main_table.is_user_defined', 1);
        // A customer attribute with is_visible = 0 is one that the admin set to Hidden, so it stays
        if (!$collection instanceof Mage_Eav_Model_Resource_Attribute_Collection && method_exists($collection, 'addVisibleFilter')) {
            $collection->addVisibleFilter();
        }
        return $collection;
    }

    /**
     * Set to null the value of each attribute of $attributes whose condition $object does not meet, because a form
     * hides that field. An attribute whose condition reads a field that $object does not hold keeps its value.
     *
     * @param iterable<Mage_Eav_Model_Entity_Attribute_Abstract> $attributes
     * @return list<string> the codes of the attributes that the condition hides
     */
    public function clearHiddenValues(\Maho\DataObject $object, iterable $attributes): array
    {
        $data = $object->getData();
        $hidden = [];
        foreach ($attributes as $attribute) {
            $condition = $attribute->getDependsOn();
            if ($condition === null) {
                continue;
            }
            // A partial load or update does not hold every field, and an unknown value must not delete data
            foreach (\Maho\Data\Form\Condition::fields($condition) as $field) {
                if (!array_key_exists($field, $data)) {
                    continue 2;
                }
            }
            if (!\Maho\Data\Form\Condition::evaluate($condition, $data)) {
                $code = (string) $attribute->getAttributeCode();
                $hidden[] = $code;
                if ($object->getData($code) !== null) {
                    $object->setData($code);
                }
            }
        }
        return $hidden;
    }

    /**
     * Throw an exception when the attribute set $setId is not a set of $entityType.
     *
     * @throws Mage_Core_Exception
     */
    public function assertSetOfType(int $setId, Mage_Eav_Model_Entity_Type $entityType): void
    {
        $set = Mage::getModel('eav/entity_attribute_set')->load($setId);
        if ((int) $set->getEntityTypeId() !== (int) $entityType->getId()) {
            Mage::throwException($this->__('Attribute set %d is not a set of the entity type %s.', $setId, $entityType->getEntityTypeCode()));
        }
    }

    /**
     * Return the settings of the admin attribute pages of $entityType, or null when System > Attributes
     * does not manage the type. See Mage_Eav_Model_Entity_Attribute::getAdminPageSettings().
     */
    public function getAdminPageSettings(Mage_Eav_Model_Entity_Type $entityType): ?Mage_Eav_Model_Entity_Attribute_AdminPageSettings
    {
        return $this->getAdminAttributeModel($entityType)?->getAdminPageSettings();
    }

    /**
     * Return the question that the edit page of a record asks before it moves the record from set
     * $savedSetId to each set of $sets, keyed by set ID. A set that holds every attribute of the saved
     * set gets no question.
     *
     * @param list<array{value: int|string, label: string}> $sets the options of the set select
     * @return array<string, string>
     */
    public function getSetChangeQuestions(int $savedSetId, array $sets): array
    {
        if (!$savedSetId) {
            return [];
        }
        $resource = Mage::getResourceSingleton('eav/entity_attribute_set');
        $questions = [];
        foreach ($sets as $set) {
            $labels = (int) $set['value'] === $savedSetId ? [] : $resource->getMissingAttributeLabels($savedSetId, (int) $set['value']);
            if ($labels) {
                $questions[(string) $set['value']] = $this->__('When you save, the values of these attributes are deleted, because the new set does not have them: %s. Change the set?', implode(', ', $labels));
            }
        }
        return $questions;
    }

    /**
     * Return the text that the edit page of $set shows above the groups, or null for no text.
     */
    public function getSetNote(Mage_Eav_Model_Entity_Attribute_Set $set): ?string
    {
        $entityType = Mage::getSingleton('eav/config')->getEntityType((int) $set->getEntityTypeId());
        return $this->getAdminAttributeModel($entityType)?->getSetNote($set);
    }

    /**
     * Return the question that the edit page of $set asks before it deletes the set.
     */
    public function getSetDeleteQuestion(Mage_Eav_Model_Entity_Attribute_Set $set): string
    {
        $entityType = Mage::getSingleton('eav/config')->getEntityType((int) $set->getEntityTypeId());
        return $this->getAdminAttributeModel($entityType)?->getSetDeleteQuestion($set)
            ?? $this->__('The records of this set will move to the default set. Delete the set?');
    }

    /**
     * Return the question that the edit page of $set asks before a save takes $attributes out of
     * the set, or null for no question.
     *
     * @param list<Mage_Eav_Model_Entity_Attribute_Abstract> $attributes
     */
    public function getSetRemoveQuestion(Mage_Eav_Model_Entity_Attribute_Set $set, array $attributes): ?string
    {
        $entityType = Mage::getSingleton('eav/config')->getEntityType((int) $set->getEntityTypeId());
        return $this->getAdminAttributeModel($entityType)?->getSetRemoveQuestion($set, $attributes);
    }

    /**
     * Return an empty attribute model of $entityType, which gives the rules of the admin attribute pages.
     */
    protected function getAdminAttributeModel(Mage_Eav_Model_Entity_Type $entityType): ?Mage_Eav_Model_Entity_Attribute
    {
        $attribute = Mage::getModel((string) $entityType->getAttributeModel());
        if (!$attribute instanceof Mage_Eav_Model_Entity_Attribute) {
            return null;
        }
        return $attribute->setEntityTypeId((int) $entityType->getId());
    }

    /**
     * Split $attributes by the groups of attribute set $setId, in the order of the groups. Each
     * group keeps the order of $attributes.
     *
     * The first part holds the attributes of the first group, the attributes that are not in the
     * set and the attributes that are not user-defined, with no label, because they join the fields
     * that the page shows itself. Each other group that holds an attribute follows, with its label
     * in $storeId.
     *
     * @template T of Mage_Eav_Model_Entity_Attribute_Abstract
     * @param list<T> $attributes
     * @return list<array{id: ?int, label: ?string, attributes: list<T>}>
     */
    public function groupAttributesBySet(array $attributes, ?int $setId, ?int $storeId = null): array
    {
        if (!$setId || !$attributes) {
            return [['id' => null, 'label' => null, 'attributes' => $attributes]];
        }

        $resource = Mage::getSingleton('core/resource');
        $adapter = $resource->getConnection('core_read');
        $groupOf = $adapter->fetchPairs(
            $adapter->select()
                ->from($resource->getTableName('eav/entity_attribute'), ['attribute_id', 'attribute_group_id'])
                ->where('attribute_set_id = ?', $setId),
        );
        $groups = Mage::getResourceModel('eav/entity_attribute_group_collection')
            ->setAttributeSetFilter($setId)
            ->setSortOrder()
            ->addStoreLabel($storeId ?? (int) Mage::app()->getStore()->getId());

        $firstGroupId = null;
        $parts = [];
        foreach ($groups as $group) {
            $firstGroupId ??= (int) $group->getId();
            $parts[(int) $group->getId()] = ['id' => (int) $group->getId(), 'label' => $group->getStoreLabel(), 'attributes' => []];
        }
        $first = ['id' => $firstGroupId, 'label' => null, 'attributes' => []];
        foreach ($attributes as $attribute) {
            $groupId = (int) ($groupOf[$attribute->getId()] ?? 0);
            if ($groupId && $groupId !== $firstGroupId && isset($parts[$groupId]) && $attribute->getIsUserDefined()) {
                $parts[$groupId]['attributes'][] = $attribute;
            } else {
                $first['attributes'][] = $attribute;
            }
        }

        $result = $first['attributes'] ? [$first] : [];
        foreach ($parts as $part) {
            if ($part['attributes']) {
                $result[] = $part;
            }
        }
        return $result;
    }
}
