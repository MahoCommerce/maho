<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2023 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Eav
 */

declare(strict_types=1);

/**
 * @method Mage_Eav_Model_Resource_Entity_Attribute_Set _getResource()
 * @method Mage_Eav_Model_Resource_Entity_Attribute_Set getResource()
 * @method Mage_Eav_Model_Resource_Entity_Attribute_Set_Collection getCollection()
 * @method Mage_Eav_Model_Resource_Entity_Attribute_Set_Collection getResourceCollection()
 *
 * @phpstan-type SetTree array{attribute_set_name: string, groups: list<array{0: int|string, 1: string, 2: int, 3?: array<int, string>}>, attributes: list<array{0: int, 1: int|string, 2: int}>, not_attributes: list<int>, removeGroups: list<int>}
 */
class Mage_Eav_Model_Entity_Attribute_Set extends Mage_Core_Model_Abstract
{
    /**
     * Prefix of model events names
     * @var string
     */
    #[\Override]
    protected $_eventPrefix = 'eav_entity_attribute_set';

    #[\Override]
    protected function _construct()
    {
        $this->_init('eav/entity_attribute_set');
    }

    /**
     * Init attribute set from skeleton (another attribute set)
     *
     * @param int $skeletonId
     * @return $this
     */
    public function initFromSkeleton($skeletonId)
    {
        $groups = Mage::getModel('eav/entity_attribute_group')
            ->getResourceCollection()
            ->setAttributeSetFilter($skeletonId)
            ->load();

        $newGroups = [];
        /** @var Mage_Eav_Model_Entity_Attribute_Group $group */
        foreach ($groups as $group) {
            $newGroup = clone $group;
            $newGroup->setStoreLabels($group->getStoreLabels())
                ->setId(null)
                ->setAttributeSetId($this->getId())
                ->setDefaultId($group->getDefaultId());

            $groupAttributesCollection = Mage::getModel('eav/entity_attribute')
                ->getResourceCollection()
                ->setAttributeGroupFilter($group->getId())
                ->load();

            $newAttributes = [];
            /** @var Mage_Eav_Model_Entity_Attribute $attribute */
            foreach ($groupAttributesCollection as $attribute) {
                $newAttribute = Mage::getModel('eav/entity_attribute')
                    ->setId($attribute->getId())
                    //->setAttributeGroupId($newGroup->getId())
                    ->setAttributeSetId($this->getId())
                    ->setEntityTypeId($this->getEntityTypeId())
                    ->setSortOrder($attribute->getSortOrder());
                $newAttributes[] = $newAttribute;
            }
            $newGroup->setAttributes($newAttributes);
            $newGroups[] = $newGroup;
        }
        $this->setGroups($newGroups);

        return $this;
    }

    /**
     * Collect the data for save from the tree of the set editor. Each group is an ID, a name, a sort order and the
     * optional labels for each store view. Each attribute is an ID, the ID of its group and a sort order. The ID of a
     * group that the editor added is not a number.
     *
     * @param SetTree $data
     * @return $this
     */
    public function organizeData($data)
    {
        $modelGroupArray = [];
        $modelAttributeArray = [];
        $attributeIds = [];
        if ($data['attributes']) {
            $ids = [];
            foreach ($data['attributes'] as $attribute) {
                $ids[] = $attribute[0];
            }
            $attributeIds = Mage::getResourceSingleton('eav/entity_attribute')
                ->getValidAttributeIds($ids);
        }
        if ($data['groups']) {
            foreach ($data['groups'] as $group) {
                $modelGroup = Mage::getModel('eav/entity_attribute_group');
                $modelGroup->setId(is_numeric($group[0]) && $group[0] > 0 ? $group[0] : null)
                    ->setAttributeGroupName($group[1])
                    ->setAttributeSetId($this->getId())
                    ->setSortOrder($group[2]);
                // The labels for each store view are optional, so an older client keeps the saved ones
                if (isset($group[3]) && is_array($group[3])) {
                    $modelGroup->setStoreLabels($group[3]);
                }

                if ($data['attributes']) {
                    foreach ($data['attributes'] as $attribute) {
                        if ($attribute[1] == $group[0] && in_array($attribute[0], $attributeIds)) {
                            $modelAttribute = Mage::getModel('eav/entity_attribute');
                            // A group that the editor added has no ID yet: its save sets the ID on its attributes
                            $modelAttribute->setId($attribute[0])
                                ->setAttributeGroupId(is_numeric($attribute[1]) ? (int) $attribute[1] : null)
                                ->setAttributeSetId($this->getId())
                                ->setEntityTypeId($this->getEntityTypeId())
                                ->setSortOrder($attribute[2]);
                            $modelAttributeArray[] = $modelAttribute;
                        }
                    }
                    $modelGroup->setAttributes($modelAttributeArray);
                    $modelAttributeArray = [];
                }
                $modelGroupArray[] = $modelGroup;
            }
            $this->setGroups($modelGroupArray);
        }

        if ($data['not_attributes']) {
            foreach ($data['not_attributes'] as $attributeId) {
                $modelAttribute = Mage::getModel('eav/entity_attribute');

                $modelAttribute->setEntityAttributeId($attributeId);
                $modelAttributeArray[] = $modelAttribute;
            }
            $this->setRemoveAttributes($modelAttributeArray);
        }

        if ($data['removeGroups']) {
            $modelGroupArray = [];
            foreach ($data['removeGroups'] as $groupId) {
                $modelGroup = Mage::getModel('eav/entity_attribute_group');
                $modelGroup->setId($groupId);

                $modelGroupArray[] = $modelGroup;
            }
            $this->setRemoveGroups($modelGroupArray);
        }
        $this->setAttributeSetName($data['attribute_set_name'])
            ->setEntityTypeId($this->getEntityTypeId());

        return $this;
    }

    /**
     * Refuse the name of another set of the entity type. Each save checks it.
     *
     * @return bool
     * @throws Mage_Core_Exception_Conflict
     */
    public function validate()
    {
        if (!$this->_getResource()->validate($this, (string) $this->getAttributeSetName())) {
            throw new Mage_Core_Exception_Conflict(
                Mage::helper('eav')->__('Attribute set with the "%s" name already exists.', $this->getAttributeSetName()),
            );
        }

        return true;
    }

    /**
     * Add set info to attributes
     *
     * @param string|Mage_Eav_Model_Entity_Type $entityType
     * @param array<Mage_Eav_Model_Entity_Attribute_Abstract|int|string> $attributes
     * @param int $setId
     * @return $this
     */
    public function addSetInfo($entityType, array $attributes, $setId = null)
    {
        $attributeIds   = [];
        $config         = Mage::getSingleton('eav/config');
        $entityType     = $config->getEntityType($entityType);
        foreach ($attributes as $attribute) {
            $attribute = $config->getAttribute($entityType, $attribute);
            if ($setId && is_array($attribute->getAttributeSetInfo()[$setId] ?? null)) {
                continue;
            }
            if (!$attribute->getAttributeId()) {
                continue;
            }
            $attributeIds[] = $attribute->getAttributeId();
        }

        if ($attributeIds) {
            $setInfo = $this->_getResource()
                ->getSetInfo($attributeIds, $setId);

            foreach ($attributes as $attribute) {
                $attribute = $config->getAttribute($entityType, $attribute);
                if (!$attribute->getAttributeId()) {
                    continue;
                }
                if (!in_array($attribute->getAttributeId(), $attributeIds)) {
                    continue;
                }
                if (is_numeric($setId)) {
                    $attributeSetInfo = $attribute->getAttributeSetInfo();
                    if (!is_array($attributeSetInfo)) {
                        $attributeSetInfo = [];
                    }
                    if (isset($setInfo[$attribute->getAttributeId()][$setId])) {
                        $attributeSetInfo[$setId] = $setInfo[$attribute->getAttributeId()][$setId];
                    }
                    $attribute->setAttributeSetInfo($attributeSetInfo);
                } else {
                    if (isset($setInfo[$attribute->getAttributeId()])) {
                        $attribute->setAttributeSetInfo($setInfo[$attribute->getAttributeId()]);
                    } else {
                        $attribute->setAttributeSetInfo([]);
                    }
                }
            }
        }

        return $this;
    }

    /**
     * Return default Group Id for current or defined Attribute Set
     *
     * @param int $setId
     * @return string|null
     */
    public function getDefaultGroupId($setId = null)
    {
        $setId ??= $this->getId();
        if ($setId) {
            $groupId = $this->_getResource()->getDefaultGroupId($setId);
        } else {
            $groupId = null;
        }
        return $groupId;
    }

    public function getAttributeSetId(): ?int
    {
        $value = $this->getData('attribute_set_id');
        return $value === null ? null : (int) $value;
    }

    public function getAttributeSetName(): ?string
    {
        $value = $this->getData('attribute_set_name');
        return $value === null ? null : (string) $value;
    }

    public function setAttributeSetName(?string $value): static
    {
        return $this->setData('attribute_set_name', $value);
    }

    public function getEntityTypeId(): ?int
    {
        $value = $this->getData('entity_type_id');
        return $value === null ? null : (int) $value;
    }

    public function setEntityTypeId(?int $value): static
    {
        return $this->setData('entity_type_id', $value);
    }

    /**
     * @return list<Mage_Eav_Model_Entity_Attribute_Group>|null
     */
    public function getGroups(): ?array
    {
        return $this->getData('groups');
    }

    /**
     * @param list<Mage_Eav_Model_Entity_Attribute_Group>|null $value
     */
    public function setGroups(?array $value): static
    {
        return $this->setData('groups', $value);
    }

    /**
     * @return list<Mage_Eav_Model_Entity_Attribute>|null
     */
    public function getRemoveAttributes(): ?array
    {
        return $this->getData('remove_attributes');
    }

    /**
     * @param list<Mage_Eav_Model_Entity_Attribute>|null $value
     */
    public function setRemoveAttributes(?array $value): static
    {
        return $this->setData('remove_attributes', $value);
    }

    /**
     * @return list<Mage_Eav_Model_Entity_Attribute_Group>|null
     */
    public function getRemoveGroups(): ?array
    {
        return $this->getData('remove_groups');
    }

    /**
     * @param list<Mage_Eav_Model_Entity_Attribute_Group>|null $value
     */
    public function setRemoveGroups(?array $value): static
    {
        return $this->setData('remove_groups', $value);
    }

    public function getSortOrder(): ?int
    {
        $value = $this->getData('sort_order');
        return $value === null ? null : (int) $value;
    }

    public function setSortOrder(?int $value): static
    {
        return $this->setData('sort_order', $value);
    }

}
