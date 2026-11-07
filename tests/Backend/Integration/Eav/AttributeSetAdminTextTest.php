<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

beforeEach(function () {
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

it('gives typed page settings for a managed entity type and none for products', function () {
    $helper = Mage::helper('eav');
    $config = Mage::getSingleton('eav/config');

    $category = $helper->getAdminPageSettings($config->getEntityType(Mage_Catalog_Model_Category::ENTITY));
    $customer = $helper->getAdminPageSettings($config->getEntityType('customer'));
    $cms = $helper->getAdminPageSettings($config->getEntityType(Mage_Cms_Model_Page::ENTITY));

    expect($category)->toBeInstanceOf(Mage_Eav_Model_Entity_Attribute_AdminPageSettings::class)
        ->and($category->acl)->toBe('system/attributes/catalog_category')
        ->and($category->addToDefaultSet)->toBeTrue()
        ->and($category->hasSets())->toBeTrue()
        ->and($customer->setsUserDefinedOnly)->toBeTrue()
        ->and($cms->hasSets())->toBeFalse()
        ->and($helper->getAdminPageSettings($config->getEntityType(Mage_Catalog_Model_Product::ENTITY)))->toBeNull();
});

it('names the attributes whose values a set delete loses', function () {
    $type = Mage::getSingleton('eav/config')->getEntityType(Mage_Catalog_Model_Category::ENTITY);
    $set = Mage::getModel('eav/entity_attribute_set')
        ->setEntityTypeId((int) $type->getId())
        ->setAttributeSetName('Pest Delete ' . $this->suffix);
    $set->save();
    $set->initFromSkeleton((int) $type->getDefaultAttributeSetId())->save();
    $this->records[] = $set;

    $label = 'Pest Lost ' . $this->suffix;
    $this->records[] = Mage::getModel('catalog/resource_eav_attribute')->setData([
        'entity_type_id' => (int) $type->getId(),
        'attribute_code' => 'pest_lost_' . $this->suffix,
        'backend_type' => 'varchar',
        'frontend_input' => 'text',
        'frontend_label' => $label,
        'is_user_defined' => 1,
        'is_global' => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_GLOBAL,
        'attribute_set_id' => (int) $set->getId(),
        'attribute_group_id' => (int) Mage::getResourceModel('eav/entity_attribute_group_collection')
            ->setAttributeSetFilter((int) $set->getId())
            ->setSortOrder()
            ->getFirstItem()
            ->getId(),
    ])->save();

    $question = Mage::helper('eav')->getSetDeleteQuestion($set);

    expect($question)->toContain($label)
        ->and($question)->not->toContain('Name,');
});
