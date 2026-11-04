<?php

/**
 * The tabs of the edit page of a blog post or blog category.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Blog
 */

declare(strict_types=1);

abstract class Maho_Blog_Block_Adminhtml_Edit_Tabs extends Mage_Adminhtml_Block_Widget_Tabs
{
    abstract public function getEntity(): Maho_Blog_Model_Post|Maho_Blog_Model_Category;

    /**
     * Return the name that wraps the fields of the form, for example "category" for category[name].
     */
    protected function getFieldNameSuffix(): ?string
    {
        return null;
    }

    /**
     * Add one tab for each group of the set of the record that holds a user-defined attribute. The
     * main tab of the page shows the attributes of the first group, so that group has no tab.
     */
    protected function addAttributeTabs(): void
    {
        $groups = self::getAttributeGroups($this->getEntity());
        array_shift($groups);
        foreach ($groups as ['group' => $group, 'attributes' => $attributes]) {
            if (!$attributes) {
                continue;
            }
            $this->addTab('attribute_group_' . $group->getId(), $this->getLayout()->createBlock('blog/adminhtml_edit_tab_attributes')
                ->setData('entity', $this->getEntity())
                ->setData('group', $group)
                ->setData('attributes', $attributes)
                ->setData('field_name_suffix', $this->getFieldNameSuffix()));
        }
    }

    /**
     * Return the user-defined attributes of the first group of the set of $entity. The main tab of
     * the edit page shows them.
     *
     * @return list<Maho_Blog_Model_Resource_Eav_Attribute>
     */
    public static function getMainTabAttributes(Maho_Blog_Model_Post|Maho_Blog_Model_Category $entity): array
    {
        return self::getAttributeGroups($entity)[0]['attributes'] ?? [];
    }

    /**
     * Return the groups of the set of $entity in the order of the set, each with its user-defined
     * attributes in the order of the group. A new record uses the default set.
     *
     * @return list<array{group: Mage_Eav_Model_Entity_Attribute_Group, attributes: list<Maho_Blog_Model_Resource_Eav_Attribute>}>
     */
    public static function getAttributeGroups(Maho_Blog_Model_Post|Maho_Blog_Model_Category $entity): array
    {
        $setId = $entity->getAttributeSetId() ?: (int) $entity->getResource()->getEntityType()->getDefaultAttributeSetId();
        $byGroup = [];
        foreach ($entity->getResource()->loadAllAttributes($entity)->getSortedAttributes($setId) as $attribute) {
            $groupId = (int) $attribute->getData("attribute_set_info/{$setId}/group_id");
            if ($groupId && $attribute->getIsUserDefined()) {
                $byGroup[$groupId][] = $attribute;
            }
        }

        $groups = [];
        foreach (Mage::getResourceModel('eav/entity_attribute_group_collection')->setAttributeSetFilter($setId)->setSortOrder() as $group) {
            $groups[] = ['group' => $group, 'attributes' => $byGroup[(int) $group->getId()] ?? []];
        }
        return $groups;
    }

    /**
     * Add the attribute set select to $fieldset for a new record, when the entity type has more than
     * one set. A record keeps its set after the first save, as a product does.
     */
    public static function addAttributeSetField(\Maho\Data\Form\Element\Fieldset $fieldset, Maho_Blog_Model_Post|Maho_Blog_Model_Category $entity): void
    {
        if ($entity->getId()) {
            return;
        }
        $sets = Mage::getResourceModel('eav/entity_attribute_set_collection')
            ->setEntityTypeFilter((int) $entity->getResource()->getTypeId())
            ->setOrder('attribute_set_name', 'asc')
            ->toOptionArray();
        if (count($sets) < 2) {
            return;
        }
        $fieldset->addField('attribute_set_id', 'select', [
            'name' => 'attribute_set_id',
            'label' => Mage::helper('blog')->__('Attribute Set'),
            'title' => Mage::helper('blog')->__('Attribute Set'),
            'note' => Mage::helper('blog')->__('The fields of the set show after you save. You cannot change the set later.'),
            'values' => $sets,
        ]);
        $entity->setAttributeSetId((int) $entity->getResource()->getEntityType()->getDefaultAttributeSetId());
    }
}
