<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * An attribute can have a condition that shows it in a form only when other fields have some values (#1729). A
 * hidden field is not required, and a save does not keep its value.
 */

function dependencyService(): Mage_Eav_Service_Attribute
{
    /** @var Mage_Eav_Service_Attribute */
    return Mage::getService('eav/attribute');
}

/**
 * @param array<string, mixed> $data
 */
function dependencyAttribute(string $entityType, array $data, ?array $dependsOn = null): Mage_Eav_Model_Entity_Attribute
{
    $attribute = dependencyService()->newAttribute($entityType)->addData($data + [
        'frontend_input' => 'text',
        'frontend_label' => ['Pest Dependency'],
    ]);
    if ($dependsOn !== null) {
        $attribute->setDependsOn($dependsOn);
    }
    dependencyService()->save($attribute);
    Mage::getSingleton('eav/config')->clear();
    return $attribute;
}

function dependencyItaly(): array
{
    return [['operator' => 'AND', 'condition' => ['country_id' => ['IT']]]];
}

beforeEach(function () {
    $this->code = 'pest_dep_' . substr(uniqid(), -6);
    $this->created = [];
    $this->customer = null;
});

afterEach(function () {
    $this->customer?->delete();
    foreach (array_reverse($this->created) as $attribute) {
        Mage::getModel('eav/entity_attribute')->load($attribute->getId())->delete();
    }
    $resource = Mage::getSingleton('core/resource');
    $connection = $resource->getConnection('core_write');
    foreach (['sales/quote_address', 'sales/order_address', 'cms/page'] as $table) {
        $table = $resource->getTableName($table);
        foreach ($this->created as $attribute) {
            if ($connection->tableColumnExists($table, (string) $attribute->getAttributeCode())) {
                $connection->dropColumn($table, (string) $attribute->getAttributeCode());
            }
        }
        $connection->resetDdlCache($table);
    }
    Mage::getSingleton('eav/config')->clear();
});

it('stores the condition and reads the source field with its options', function () {
    $this->created[] = dependencyAttribute('customer_address', [
        'attribute_code' => $this->code,
        'used_in_forms' => ['customer_address_edit'],
    ], dependencyItaly());

    $loaded = dependencyService()->getByCode($this->code, 'customer_address');
    $sources = $loaded->getDependencySources();

    expect($loaded->getDependsOn())->toBe(dependencyItaly())
        ->and($sources)->toHaveKey('country_id')
        ->and(array_column($sources['country_id']['options'], 'value'))->toContain('IT')
        ->and($sources)->not->toHaveKey($this->code);
});

it('refuses a condition that a form cannot apply', function (array $condition, string $message) {
    $attribute = dependencyService()->newAttribute('customer_address')->addData([
        'attribute_code' => $this->code,
        'frontend_input' => 'text',
        'frontend_label' => ['Pest Dependency'],
    ])->setDependsOn($condition);

    expect(fn() => dependencyService()->save($attribute))->toThrow(Mage_Core_Exception_Input::class, $message);
})->with([
    'unknown field' => [['pest_missing' => ['1']], 'which this form does not have'],
    'unknown value' => [['country_id' => ['XX']], 'has no option with the value XX'],
    'bad operator' => [[['operator' => 'NAND', 'condition' => ['country_id' => ['IT']]]], 'Invalid operator'],
]);

it('refuses a condition that reads the attribute itself', function () {
    $this->created[] = dependencyAttribute('customer_address', [
        'attribute_code' => $this->code,
        'frontend_input' => 'select',
        'option' => ['value' => ['opt_a' => ['A']]],
    ]);
    $attribute = dependencyService()->getByCode($this->code, 'customer_address');
    $optionId = array_key_first(dependencyService()->getOptions($attribute));
    $attribute->setDependsOn([$this->code => [(string) $optionId]]);

    expect(fn() => dependencyService()->save($attribute))->toThrow(Mage_Core_Exception_Input::class, 'cannot read the attribute itself');
});

it('refuses a chain: a condition that reads a field with a condition, and a condition on a field that a condition reads', function () {
    $this->created[] = dependencyAttribute('customer_address', [
        'attribute_code' => $this->code,
        'frontend_input' => 'select',
        'option' => ['value' => ['opt_a' => ['A']]],
    ], dependencyItaly());
    $source = dependencyService()->getByCode($this->code, 'customer_address');
    $optionId = (string) array_key_first(dependencyService()->getOptions($source));

    $reader = dependencyService()->newAttribute('customer_address')->addData([
        'attribute_code' => $this->code . '_r',
        'frontend_input' => 'text',
        'frontend_label' => ['Pest Reader'],
    ])->setDependsOn([$this->code => [$optionId]]);
    expect(fn() => dependencyService()->save($reader))->toThrow(Mage_Core_Exception_Input::class, 'has a condition of its own');

    $source->setDependsOn(null);
    dependencyService()->save($source);
    Mage::getSingleton('eav/config')->clear();
    $this->created[] = dependencyService()->save($reader->setDependsOn([$this->code => [$optionId]]));

    $source = dependencyService()->getByCode($this->code, 'customer_address')->setDependsOn(dependencyItaly());
    expect(fn() => dependencyService()->save($source))->toThrow(Mage_Core_Exception_Input::class, 'reads it');
});

it('refuses to delete an attribute that the condition of another attribute reads', function () {
    $this->created[] = dependencyAttribute('customer_address', [
        'attribute_code' => $this->code,
        'frontend_input' => 'boolean',
    ]);
    $this->created[] = dependencyAttribute('customer_address', ['attribute_code' => $this->code . '_r'], [$this->code => ['1']]);

    $source = dependencyService()->getByCode($this->code, 'customer_address');
    expect(fn() => dependencyService()->delete($source))->toThrow(Mage_Core_Exception_Conflict::class, $this->code . '_r');
});

it('does not require a hidden field in the address form, and clears its value', function () {
    $this->created[] = dependencyAttribute('customer_address', [
        'attribute_code' => $this->code,
        'is_required' => 1,
        'used_in_forms' => ['customer_address_edit'],
    ], dependencyItaly());
    $message = '"Pest Dependency" is a required value.';

    $address = Mage::getModel('customer/address')->setData($this->code, 'old');
    /** @var Mage_Customer_Model_Form $form */
    $form = Mage::getModel('customer/form')->setFormCode('customer_address_edit')->setEntity($address);

    $usErrors = $form->validateData(['country_id' => 'US', $this->code => '']);
    $itErrors = $form->validateData(['country_id' => 'IT', $this->code => '']);
    $form->compactData(['country_id' => 'US', $this->code => 'new']);

    expect(is_array($usErrors) ? $usErrors : [])->not->toContain($message)
        ->and($itErrors)->toContain($message)
        ->and($address->getData($this->code))->toBeNull();
});

it('clears the value of a hidden field when an address is saved', function () {
    $this->created[] = dependencyAttribute('customer_address', [
        'attribute_code' => $this->code,
        'used_in_forms' => ['customer_address_edit'],
    ], dependencyItaly());

    $this->customer = Mage::getModel('customer/customer')->setWebsiteId(1)->addData([
        'email' => $this->code . '@example.com',
        'firstname' => 'Pest',
        'lastname' => 'Dependency',
    ])->save();
    $address = Mage::getModel('customer/address')->addData([
        'parent_id' => $this->customer->getId(),
        'firstname' => 'Pest',
        'lastname' => 'Dependency',
        'street' => 'Via Roma 1',
        'city' => 'Roma',
        'postcode' => '00100',
        'telephone' => '555',
        'country_id' => 'IT',
        $this->code => 'IT123',
    ])->save();
    $italian = Mage::getModel('customer/address')->load($address->getId())->getData($this->code);

    Mage::getModel('customer/address')->load($address->getId())->setCountryId('US')->save();
    $american = Mage::getModel('customer/address')->load($address->getId())->getData($this->code);

    expect($italian)->toBe('IT123')
        ->and($american)->toBeNull();
});

it('keeps the value when a partial update does not hold the field that the condition reads', function () {
    $this->created[] = $attribute = dependencyAttribute('customer_address', ['attribute_code' => $this->code], dependencyItaly());
    $object = new \Maho\DataObject([$this->code => 'kept']);

    $hidden = Mage::helper('eav')->clearHiddenValues($object, [$attribute]);

    expect($hidden)->toBe([])
        ->and($object->getData($this->code))->toBe('kept');
});

it('does not require a hidden field in the API values, and does not keep a value for it', function () {
    $this->created[] = dependencyAttribute('cms_page', [
        'attribute_code' => $this->code,
        'is_required' => 1,
    ], [['operator' => 'AND', 'condition' => ['is_active' => ['1']]]]);
    $attributes = [$this->code => dependencyService()->getByCode($this->code, 'cms_page')];

    $disabled = new \Maho\DataObject(['is_active' => 0]);
    \Maho\ApiPlatform\Service\CustomAttributes::apply($disabled, [$this->code => 'sent'], $attributes, isNew: true);
    $enabled = new \Maho\DataObject(['is_active' => 1]);

    expect($disabled->getData($this->code))->toBeNull()
        ->and(fn() => \Maho\ApiPlatform\Service\CustomAttributes::apply($enabled, [], $attributes, isNew: true))
        ->toThrow(Mage_Core_Exception_Input::class, 'required');
});

it('does not require a hidden field on a CMS page save, and clears its value', function () {
    $this->created[] = dependencyAttribute('cms_page', [
        'attribute_code' => $this->code,
        'is_required' => 1,
    ], [['operator' => 'AND', 'condition' => ['is_active' => ['1']]]]);
    /** @var Mage_Cms_Model_Resource_Page $resource */
    $resource = Mage::getResourceSingleton('cms/page');

    $disabled = Mage::getModel('cms/page')->setData(['is_active' => 0, $this->code => 'old']);
    $resource->prepareCustomAttributes($disabled);
    $enabled = Mage::getModel('cms/page')->setData(['is_active' => 1]);

    expect($disabled->getData($this->code))->toBeNull()
        ->and(fn() => $resource->prepareCustomAttributes($enabled))->toThrow(Mage_Core_Exception_Input::class, 'required');
});

it('keeps the value when the field that the condition reads is false, the value of a store view that uses the default', function () {
    $this->created[] = $attribute = dependencyAttribute('customer_address', ['attribute_code' => $this->code], dependencyItaly());
    $object = new \Maho\DataObject(['country_id' => false, $this->code => 'kept']);

    expect(Mage::helper('eav')->clearHiddenValues($object, [$attribute]))->toBe([])
        ->and($object->getData($this->code))->toBe('kept');
});

it('offers the Yes and No options of a Yes/No field that stores no source model', function () {
    $this->created[] = dependencyAttribute('customer_address', [
        'attribute_code' => $this->code,
        'frontend_input' => 'boolean',
    ]);
    $reader = dependencyService()->newAttribute('customer_address')->setAttributeCode($this->code . '_r');

    expect(array_column($reader->getDependencySources()[$this->code]['options'] ?? [], 'value'))->toEqualCanonicalizing(['0', '1']);
});

it('reads the website of the store for a new customer, as the save gives it', function () {
    $this->created[] = $attribute = dependencyAttribute('customer', ['attribute_code' => $this->code], [
        'website_id' => [(string) Mage::app()->getStore()->getWebsiteId()],
    ]);

    expect(dependencyService()->getByCode($this->code, 'customer')->isDependencyMet([]))->toBeTrue();
});

it('applies the defaults and the status of a new CMS page before it checks a condition', function () {
    $this->created[] = $source = dependencyAttribute('cms_page', [
        'attribute_code' => $this->code,
        'frontend_input' => 'boolean',
        'default_value' => '0',
    ]);
    $this->created[] = dependencyAttribute('cms_page', [
        'attribute_code' => $this->code . '_r',
        'is_required' => 1,
    ], [['operator' => 'AND', 'condition' => [$this->code => ['1']]]]);
    $this->created[] = dependencyAttribute('cms_page', [
        'attribute_code' => $this->code . '_s',
        'is_required' => 1,
    ], [['operator' => 'AND', 'condition' => ['is_active' => ['1']]]]);
    /** @var Mage_Cms_Model_Resource_Page $resource */
    $resource = Mage::getResourceSingleton('cms/page');

    $page = Mage::getModel('cms/page')->setData('is_active', false);
    $resource->prepareCustomAttributes($page);

    expect($page->getData($this->code))->toBe('0')
        ->and($page->getData('is_active'))->toBe(0)
        ->and($page->getData($this->code . '_r'))->toBeNull()
        ->and($page->getData($this->code . '_s'))->toBeNull();
});
