<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * A new set of $entityTypeCode, based on the default set of the type.
 */
function groupLabelTestCreateSet(string $entityTypeCode): Mage_Eav_Model_Entity_Attribute_Set
{
    $type = Mage::getSingleton('eav/config')->getEntityType($entityTypeCode);
    $set = Mage::getModel('eav/entity_attribute_set')
        ->setEntityTypeId((int) $type->getId())
        ->setAttributeSetName('Pest Labels ' . uniqid());
    $set->save();
    $set->initFromSkeleton((int) $type->getDefaultAttributeSetId())->save();
    return $set;
}

function groupLabelTestAddGroup(int $setId, string $name, int $sortOrder, array $labels = []): Mage_Eav_Model_Entity_Attribute_Group
{
    $group = Mage::getModel('eav/entity_attribute_group')
        ->setAttributeSetId($setId)
        ->setAttributeGroupName($name)
        ->setSortOrder($sortOrder)
        ->setStoreLabels($labels);
    $group->save();
    return $group;
}

/**
 * A user-defined text attribute of blog posts in group $groupId of set $setId.
 */
function groupLabelTestAddAttribute(string $code, int $setId, int $groupId): Maho_Blog_Model_Resource_Eav_Attribute
{
    $attribute = Mage::getModel('blog/resource_eav_attribute')->setData([
        'entity_type_id' => (int) Mage::getSingleton('eav/config')->getEntityType(Maho_Blog_Model_Post::ENTITY)->getId(),
        'attribute_code' => $code,
        'backend_type' => 'varchar',
        'frontend_input' => 'text',
        'frontend_label' => $code,
        'is_user_defined' => 1,
        'is_global' => 1,
        'is_visible_on_front' => 1,
        'attribute_set_id' => $setId,
        'attribute_group_id' => $groupId,
    ]);
    $attribute->save();
    Mage::getSingleton('eav/config')->clear();
    return $attribute;
}

beforeEach(function () {
    $this->storeId = (int) Mage::app()->getDefaultStoreView()->getId();
    $this->suffix = substr(uniqid(), -6);
    $this->records = [];
});

afterEach(function () {
    foreach (array_reverse($this->records) as $record) {
        if ($record->getId()) {
            $record->delete();
        }
    }
    Mage::getSingleton('eav/config')->clear();
});

it('saves the label of a group for each store view as plain text', function () {
    $set = $this->records[] = groupLabelTestCreateSet('customer');
    $group = groupLabelTestAddGroup((int) $set->getId(), 'Company &amp; Tax', 50, [
        $this->storeId => ' <b>Azienda</b> ',
        0 => 'Admin store',
        999999 => 'Unknown store',
    ]);

    $loaded = Mage::getModel('eav/entity_attribute_group')->load($group->getId());

    expect($loaded->getStoreLabels())->toBe([$this->storeId => 'Azienda'])
        ->and($loaded->getStoreLabel($this->storeId))->toBe('Azienda')
        ->and($loaded->getStoreLabel(0))->toBe('Company & Tax');
});

it('keeps the saved labels when a save does not post them, and copies them to a new set', function () {
    $set = $this->records[] = groupLabelTestCreateSet('customer');
    $group = groupLabelTestAddGroup((int) $set->getId(), 'Company', 50, [$this->storeId => 'Azienda']);

    Mage::getModel('eav/entity_attribute_group')->load($group->getId())->setSortOrder(60)->save();
    $copy = $this->records[] = Mage::getModel('eav/entity_attribute_set')
        ->setEntityTypeId((int) $set->getEntityTypeId())
        ->setAttributeSetName('Pest Copy ' . $this->suffix);
    $copy->save();
    $copy->initFromSkeleton((int) $set->getId())->save();
    $copied = Mage::getResourceModel('eav/entity_attribute_group_collection')
        ->setAttributeSetFilter((int) $copy->getId())
        ->addFieldToFilter('attribute_group_name', 'Company')
        ->getFirstItem();

    expect(Mage::getModel('eav/entity_attribute_group')->load($group->getId())->getStoreLabels())->toBe([$this->storeId => 'Azienda'])
        ->and($copied->getId())->not->toBe($group->getId())
        ->and($copied->getStoreLabels())->toBe([$this->storeId => 'Azienda']);
});

it('saves the labels that the set editor posts with a group', function () {
    $set = $this->records[] = groupLabelTestCreateSet('customer');
    $groups = Mage::getResourceModel('eav/entity_attribute_group_collection')
        ->setAttributeSetFilter((int) $set->getId())
        ->setSortOrder();
    $posted = [];
    foreach ($groups as $group) {
        $posted[] = [$group->getId(), $group->getAttributeGroupName(), (int) $group->getSortOrder(), [$this->storeId => 'Generale']];
    }
    $posted[] = ['ynode-1', 'Pest New', 99, [$this->storeId => 'Nuovo']];

    $set->organizeData([
        'attribute_set_name' => $set->getAttributeSetName(),
        'groups' => $posted,
        'attributes' => [],
        'not_attributes' => [],
        'removeGroups' => [],
    ]);
    $set->save();

    $labels = [];
    foreach (Mage::getResourceModel('eav/entity_attribute_group_collection')->setAttributeSetFilter((int) $set->getId())->addStoreLabel($this->storeId) as $group) {
        $labels[$group->getAttributeGroupName()] = $group->getStoreLabel();
    }

    expect($labels['Pest New'])->toBe('Nuovo')
        ->and(array_count_values($labels)['Generale'] ?? 0)->toBe(count($posted) - 1);
});

it('splits attributes by group, with the first group and the attributes in no set first', function () {
    $setId = (int) Mage::getSingleton('eav/config')->getEntityType(Maho_Blog_Model_Post::ENTITY)->getDefaultAttributeSetId();
    $firstGroupId = (int) Mage::getResourceModel('eav/entity_attribute_group_collection')
        ->setAttributeSetFilter($setId)->setSortOrder()->getFirstItem()->getId();
    $later = $this->records[] = groupLabelTestAddGroup($setId, 'Pest Later ' . $this->suffix, 900, [$this->storeId => 'Dopo']);
    $sooner = $this->records[] = groupLabelTestAddGroup($setId, 'Pest Sooner ' . $this->suffix, 800);
    $inLater = $this->records[] = groupLabelTestAddAttribute('pest_later_' . $this->suffix, $setId, (int) $later->getId());
    $inSooner = $this->records[] = groupLabelTestAddAttribute('pest_sooner_' . $this->suffix, $setId, (int) $sooner->getId());
    $inFirst = $this->records[] = groupLabelTestAddAttribute('pest_first_' . $this->suffix, $setId, $firstGroupId);

    $parts = Mage::helper('eav')->groupAttributesBySet([$inLater, $inSooner, $inFirst], $setId, $this->storeId);
    $summary = array_map(fn(array $part) => [
        'label' => $part['label'],
        'codes' => array_map(fn($attribute) => $attribute->getAttributeCode(), $part['attributes']),
    ], $parts);

    expect($summary)->toBe([
        ['label' => null, 'codes' => [$inFirst->getAttributeCode()]],
        ['label' => 'Pest Sooner ' . $this->suffix, 'codes' => [$inSooner->getAttributeCode()]],
        ['label' => 'Dopo', 'codes' => [$inLater->getAttributeCode()]],
    ]);
});

it('keeps an attribute of the platform in the first part, also when its group is not the first group', function () {
    $setId = (int) Mage::getSingleton('eav/config')->getEntityType(Maho_Blog_Model_Post::ENTITY)->getDefaultAttributeSetId();
    $group = $this->records[] = groupLabelTestAddGroup($setId, 'Pest System ' . $this->suffix, 900);
    $attribute = $this->records[] = groupLabelTestAddAttribute('pest_system_' . $this->suffix, $setId, (int) $group->getId());
    $attribute->setIsUserDefined(0);

    $parts = Mage::helper('eav')->groupAttributesBySet([$attribute], $setId, $this->storeId);

    expect($parts)->toHaveCount(1)
        ->and($parts[0]['label'])->toBeNull();
});

it('shows one storefront table for each group of a blog post', function () {
    $setId = (int) Mage::getSingleton('eav/config')->getEntityType(Maho_Blog_Model_Post::ENTITY)->getDefaultAttributeSetId();
    $firstGroupId = (int) Mage::getResourceModel('eav/entity_attribute_group_collection')
        ->setAttributeSetFilter($setId)->setSortOrder()->getFirstItem()->getId();
    $details = $this->records[] = groupLabelTestAddGroup($setId, 'Pest Details ' . $this->suffix, 900, [$this->storeId => 'Dettagli']);
    $main = $this->records[] = groupLabelTestAddAttribute('pest_main_' . $this->suffix, $setId, $firstGroupId);
    $extra = $this->records[] = groupLabelTestAddAttribute('pest_extra_' . $this->suffix, $setId, (int) $details->getId());

    $post = Mage::getModel('blog/post')->setAttributeSetId($setId)->setData([
        'attribute_set_id' => $setId,
        $main->getAttributeCode() => 'Main value',
        $extra->getAttributeCode() => 'Extra value',
    ]);
    $tables = Mage::app()->getLayout()->createBlock('eav/frontend_attributes')->setData('entity', $post)->getAttributeGroups();
    $summary = array_map(fn(array $table) => [$table['title'], array_column($table['rows'], 'value')], $tables);

    expect($summary)->toBe([
        ['Additional Information', ['Main value']],
        ['Dettagli', ['Extra value']],
    ]);
});
