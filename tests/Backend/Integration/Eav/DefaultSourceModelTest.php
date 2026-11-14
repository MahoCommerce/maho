<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * An attribute with no source model gets a default one from getDefaultSourceModel(), which must always name a
 * model (#1802). A Yes/No attribute never stores a source model, so its default is the Yes/No source.
 */

it('gives the Yes/No source to a boolean customer attribute that the core creates', function (string $code) {
    $attribute = Mage::getSingleton('eav/config')->getAttribute('customer', $code);

    expect($attribute->getFrontendInput())->toBe('boolean')
        ->and($attribute->getSource())->toBeInstanceOf(Mage_Eav_Model_Entity_Attribute_Source_Boolean::class)
        ->and(array_column($attribute->getSource()->getAllOptions(), 'value'))->toEqualCanonicalizing([0, 1]);
})->with(['is_active', 'disable_auto_group_change']);

it('gives the Yes/No source to a new boolean attribute of each attribute model', function (string $alias) {
    $attribute = Mage::getModel($alias)->setAttributeCode('pest_flag')->setFrontendInput('boolean');

    expect($attribute->getDefaultSourceModel())->toBe('eav/entity_attribute_source_boolean')
        ->and($attribute->getSource())->toBeInstanceOf(Mage_Eav_Model_Entity_Attribute_Source_Boolean::class);
})->with(['eav/entity_attribute', 'customer/attribute', 'catalog/resource_eav_attribute']);

it('names a default source for an attribute that has no entity model', function () {
    $attribute = Mage::getModel('customer/attribute')
        ->setEntityTypeId((int) Mage::getSingleton('eav/config')->getEntityType('customer')->getId())
        ->setAttributeCode('pest_choice')
        ->setFrontendInput('select');

    expect($attribute->getDefaultSourceModel())->toBe(Mage_Eav_Model_Entity::DEFAULT_SOURCE_MODEL);
});

it('keeps the option table as the default source of a product attribute that is not boolean', function () {
    $attribute = Mage::getModel('catalog/resource_eav_attribute')->setAttributeCode('pest_choice')->setFrontendInput('select');

    expect($attribute->getDefaultSourceModel())->toBe('eav/entity_attribute_source_table');
});

it('keeps the store source of the store_id attribute', function () {
    $attribute = Mage::getModel('eav/entity_attribute')->setAttributeCode('store_id')->setFrontendInput('select');

    expect($attribute->getDefaultSourceModel())->toBe('eav/entity_attribute_source_store');
});

it('gives a new dropdown and a new multiple select of each managed type the option table, which holds their options', function (string $entityType, string $input) {
    /** @var Mage_Eav_Service_Attribute $service */
    $service = Mage::getService('eav/attribute');
    $code = 'pest_src_' . substr(uniqid(), -6);
    $attribute = $service->newAttribute($entityType)->addData([
        'attribute_code' => $code,
        'frontend_input' => $input,
        'frontend_label' => ['Pest Source'],
        'option' => ['value' => ['opt_a' => ['Pest A']]],
    ]);
    try {
        $service->save($attribute);
        Mage::getSingleton('eav/config')->clear();
        $saved = Mage::getSingleton('eav/config')->getAttribute($entityType, $code);

        expect($saved->getSourceModel())->toBe('eav/entity_attribute_source_table')
            ->and(array_column($saved->getSource()->getAllOptions(false), 'label'))->toBe(['Pest A']);
    } finally {
        if ($attribute->getId()) {
            $service->delete($service->getById((int) $attribute->getId(), $entityType));
        }
        $resource = Mage::getSingleton('core/resource');
        $connection = $resource->getConnection('core_write');
        foreach (['sales/quote_address', 'sales/order_address', 'sales/quote', 'sales/order', 'cms/page'] as $table) {
            $table = $resource->getTableName($table);
            foreach ([$code, Mage_Sales_Model_Observer_CustomerAttributes::COLUMN_PREFIX . $code] as $column) {
                if ($connection->tableColumnExists($table, $column)) {
                    $connection->dropColumn($table, $column);
                }
            }
            $connection->resetDdlCache($table);
        }
        Mage::getSingleton('eav/config')->clear();
    }
})->with([
    ['customer', 'select'],
    ['customer_address', 'multiselect'],
    ['blog_post', 'select'],
    ['catalog_category', 'select'],
]);
