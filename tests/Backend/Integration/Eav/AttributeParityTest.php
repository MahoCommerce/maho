<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use Mage\Catalog\Api\ProductAttributeProcessor;
use Mage\Catalog\Api\ProductAttributeProvider;
use Tests\Helpers\AdminApiParity;

uses(Tests\MahoBackendTestCase::class);

/**
 * The admin attribute pages, the product attribute API and the attribute importer save attributes and their options
 * through Mage_Eav_Service_Attribute (#1348). Each test gives the same input to the admin page of Catalog > Attributes
 * and to the API, and compares the outcome.
 */

function attributeParityService(): Mage_Eav_Service_Attribute
{
    /** @var Mage_Eav_Service_Attribute */
    return Mage::getService('eav/attribute');
}

function attributeParityCode(string $suffix): string
{
    return 'pest_par_' . $suffix . '_' . substr(uniqid(), -5);
}

/**
 * @param array<string, mixed> $fields the fields of the admin form
 * @return array{success: list<string>, errors: list<string>}
 */
function attributeViaAdmin(string $action, array $fields): array
{
    Mage::unregister('entity_attribute');
    return AdminApiParity::admin(Mage_Adminhtml_Catalog_Product_AttributeController::class, $action, $fields);
}

/**
 * @param array<string, mixed> $body the JSON body
 * @return array{status: int, result: mixed, errors: list<string>, fields: list<string>}
 */
function attributeViaApi(string $method, array $body = [], ?int $id = null, ?string $name = null, ?int $optionId = null): array
{
    $path = '/api/rest/v2/product-attributes' . ($id ? "/{$id}" : '') . ($name !== null ? '/options' : '') . ($optionId ? "/{$optionId}" : '');
    $request = AdminApiParity::jsonRequest($method, $path, $body);
    $operation = match ($method) {
        'DELETE' => new Delete(name: $name),
        'PUT' => new Put(name: $name),
        default => new Post(name: $name),
    };
    $uriVariables = array_filter(['id' => $id, 'optionId' => $optionId]);
    $security = AdminApiParity::adminSecurity();
    return AdminApiParity::api($request, fn() => new ProductAttributeProcessor($security, new ProductAttributeProvider($security))
        ->process(null, $operation, $uriVariables, ['request' => $request]));
}

function attributeParityLoad(string $code): Mage_Catalog_Model_Resource_Eav_Attribute
{
    /** @var Mage_Catalog_Model_Resource_Eav_Attribute */
    return Mage::getModel('catalog/resource_eav_attribute')->loadByCode(Mage_Catalog_Model_Product::ENTITY, $code);
}

function attributeParityCreate(string $code, string $input = 'text'): Mage_Eav_Model_Entity_Attribute
{
    $service = attributeParityService();
    $attribute = $service->newAttribute(Mage_Catalog_Model_Product::ENTITY)->addData([
        'attribute_code' => $code,
        'frontend_input' => $input,
        'frontend_label' => 'Pest Parity',
    ]);
    return $service->save($attribute);
}

/**
 * @return array<int, string> the label of each store view of the first option of $code
 */
function attributeParityOptionLabels(string $code): array
{
    $options = attributeParityService()->getOptions(attributeParityLoad($code));
    return reset($options)['labels'] ?? [];
}

function attributeParityCleanup(): void
{
    $collection = Mage::getResourceModel('catalog/product_attribute_collection')
        ->addFieldToFilter('main_table.attribute_code', ['like' => 'pest_par_%']);
    foreach ($collection as $attribute) {
        Mage::getModel('catalog/resource_eav_attribute')->load($attribute->getId())->delete();
    }
    Mage::getSingleton('eav/config')->clear();
}

beforeEach(function () {
    attributeParityCleanup();
});

afterEach(function () {
    attributeParityCleanup();
    Mage::unregister('entity_attribute');
});

it('creates a product attribute on both transports, with the storage of its input type and a label without tags', function () {
    $adminCode = attributeParityCode('admin');
    $apiCode = attributeParityCode('api');

    $admin = attributeViaAdmin('save', ['attribute_code' => $adminCode, 'frontend_input' => 'price', 'frontend_label' => ['Pest <b>Price</b>']]);
    $api = attributeViaApi('POST', ['attributeCode' => $apiCode, 'frontendInput' => 'price', 'frontendLabel' => 'Pest <b>Price</b>']);

    $fromAdmin = attributeParityLoad($adminCode);
    $fromApi = attributeParityLoad($apiCode);
    expect($admin['errors'])->toBe([])
        ->and($admin['success'])->toBe(['The product attribute has been saved.'])
        ->and($api['status'])->toBe(200)
        ->and($fromAdmin->getBackendType())->toBe('decimal')
        ->and($fromApi->getBackendType())->toBe('decimal')
        ->and($fromAdmin->getBackendModel())->toBe($fromApi->getBackendModel())
        ->and($fromAdmin->getIsUserDefined())->toBeTrue()
        ->and($fromApi->getIsUserDefined())->toBeTrue()
        ->and($fromAdmin->getFrontendLabel())->toBe('Pest Price')
        ->and($fromApi->getFrontendLabel())->toBe('Pest Price');
});

it('gives a file attribute the same models on both transports', function () {
    $adminCode = attributeParityCode('admin');
    $apiCode = attributeParityCode('api');

    attributeViaAdmin('save', ['attribute_code' => $adminCode, 'frontend_input' => 'file', 'frontend_label' => ['Pest File']]);
    $api = attributeViaApi('POST', ['attributeCode' => $apiCode, 'frontendInput' => 'file', 'frontendLabel' => 'Pest File']);

    expect($api['status'])->toBe(200)
        ->and(attributeParityLoad($adminCode)->getBackendModel())->toBe('catalog/product_attribute_backend_file')
        ->and(attributeParityLoad($apiCode)->getBackendModel())->toBe('catalog/product_attribute_backend_file');
});

it('refuses an invalid code on both transports', function () {
    $admin = attributeViaAdmin('save', ['attribute_code' => 'Bad-Code', 'frontend_input' => 'text', 'frontend_label' => ['Pest']]);
    $api = attributeViaApi('POST', ['attributeCode' => 'Bad-Code', 'frontendLabel' => 'Pest']);

    expect($api['status'])->toBe(422)
        ->and($api['fields'])->toBe(['attributeCode'])
        ->and($admin['errors'])->toBe($api['errors']);
});

it('refuses a code that the product model reserves on both transports', function () {
    $admin = attributeViaAdmin('save', ['attribute_code' => 'stock_item', 'frontend_input' => 'text', 'frontend_label' => ['Pest']]);
    $api = attributeViaApi('POST', ['attributeCode' => 'stock_item', 'frontendLabel' => 'Pest']);

    expect($api['status'])->toBe(422)
        ->and($api['fields'])->toBe(['attributeCode'])
        ->and($api['errors'])->toBe(["The attribute code 'stock_item' is reserved by system. Please try another attribute code"])
        ->and($admin['errors'])->toBe($api['errors']);
});

it('refuses a code that another product attribute has on both transports', function () {
    $code = attributeParityCode('taken');
    attributeParityCreate($code);

    $admin = attributeViaAdmin('save', ['attribute_code' => $code, 'frontend_input' => 'text', 'frontend_label' => ['Pest']]);
    $api = attributeViaApi('POST', ['attributeCode' => $code, 'frontendLabel' => 'Pest']);

    expect($admin['errors'])->toBe(['Attribute with the same code already exists'])
        ->and($api['status'])->toBe(409)
        ->and($api['errors'])->toBe(['Attribute with the same code already exists']);
});

it('refuses an input type that products do not have on both transports', function () {
    $admin = attributeViaAdmin('save', ['attribute_code' => attributeParityCode('admin'), 'frontend_input' => 'wysiwyg', 'frontend_label' => ['Pest']]);
    $api = attributeViaApi('POST', ['attributeCode' => attributeParityCode('api'), 'frontendInput' => 'wysiwyg', 'frontendLabel' => 'Pest']);

    expect($admin['errors'])->toBe(['Input type "wysiwyg" not found in the input types list.'])
        ->and($api['status'])->toBe(422)
        ->and($api['fields'])->toBe(['frontendInput'])
        ->and($api['errors'])->toBe($admin['errors']);
});

it('keeps the input type of a saved attribute on both transports', function () {
    $attribute = attributeParityCreate(attributeParityCode('fixed'));

    $admin = attributeViaAdmin('save', ['attribute_id' => $attribute->getId(), 'frontend_input' => 'select', 'frontend_label' => ['Pest']]);
    $api = attributeViaApi('PUT', ['frontendInput' => 'select'], (int) $attribute->getId());

    expect($admin['errors'])->toBe(['The input type cannot change after the attribute is created.'])
        ->and($api['status'])->toBe(422)
        ->and($api['fields'])->toBe(['frontendInput'])
        ->and($api['errors'])->toBe($admin['errors'])
        ->and(Mage::getModel('catalog/resource_eav_attribute')->load($attribute->getId())->getFrontendInput())->toBe('text');
});

it('keeps the unique flag of a system attribute on both transports', function () {
    $name = attributeParityLoad('name');
    $unique = !$name->getIsUnique();

    $admin = attributeViaAdmin('save', ['attribute_id' => $name->getId(), 'frontend_label' => [$name->getFrontendLabel()], 'is_unique' => (int) $unique]);
    $api = attributeViaApi('PUT', ['isUnique' => $unique], (int) $name->getId());

    expect($admin['errors'])->toBe(['The field is_unique cannot change for this attribute.'])
        ->and($api['status'])->toBe(422)
        ->and($api['fields'])->toBe(['isUnique'])
        ->and($api['errors'])->toBe($admin['errors'])
        ->and(attributeParityLoad('name')->getIsUnique())->toBe($name->getIsUnique());
});

it('refuses to delete a system attribute on both transports', function () {
    $nameId = (int) attributeParityLoad('name')->getId();

    $admin = attributeViaAdmin('delete', ['attribute_id' => $nameId]);
    $api = attributeViaApi('DELETE', [], $nameId);

    expect($admin['errors'])->toBe(['A system attribute cannot be deleted.'])
        ->and($api['status'])->toBe(409)
        ->and($api['errors'])->toBe($admin['errors'])
        ->and(attributeParityLoad('name')->getId())->not->toBeNull();
});

it('deletes a user-defined attribute on both transports', function () {
    $forAdmin = attributeParityCreate(attributeParityCode('admin'));
    $forApi = attributeParityCreate(attributeParityCode('api'));

    $admin = attributeViaAdmin('delete', ['attribute_id' => (int) $forAdmin->getId()]);
    $api = attributeViaApi('DELETE', [], (int) $forApi->getId());

    expect($admin['success'])->toBe(['The product attribute has been deleted.'])
        ->and($api['status'])->toBe(200)
        ->and(Mage::getModel('catalog/resource_eav_attribute')->load($forAdmin->getId())->getId())->toBeNull()
        ->and(Mage::getModel('catalog/resource_eav_attribute')->load($forApi->getId())->getId())->toBeNull();
});

it('answers that an attribute of another entity type does not exist on both transports', function () {
    $categoryName = Mage::getModel('catalog/resource_eav_attribute')->loadByCode(Mage_Catalog_Model_Category::ENTITY, 'name');

    $admin = attributeViaAdmin('delete', ['attribute_id' => (int) $categoryName->getId()]);
    $api = attributeViaApi('DELETE', [], (int) $categoryName->getId());

    expect($admin['errors'])->toBe(['This attribute no longer exists.'])
        ->and($api['status'])->toBe(404)
        ->and($api['errors'])->toBe($admin['errors']);
});

it('removes the tags from an option label on both transports', function () {
    $adminCode = attributeParityCode('admin');
    $apiCode = attributeParityCode('api');
    attributeViaAdmin('save', [
        'attribute_code' => $adminCode,
        'frontend_input' => 'select',
        'frontend_label' => ['Pest Color'],
        'option' => ['value' => ['option_0' => ['<b>Red</b>']], 'order' => ['option_0' => 1], 'delete' => ['option_0' => '']],
    ]);
    $attribute = attributeParityCreate($apiCode, 'select');
    $api = attributeViaApi('POST', ['label' => '<b>Red</b>', 'sortOrder' => 1], (int) $attribute->getId(), 'product_attribute_option_create');

    expect($api['status'])->toBe(200)
        ->and(attributeParityOptionLabels($adminCode))->toBe([0 => 'Red'])
        ->and(attributeParityOptionLabels($apiCode))->toBe([0 => 'Red']);
});

it('refuses an option without an admin label on both transports', function () {
    $code = attributeParityCode('admin');
    $admin = attributeViaAdmin('save', [
        'attribute_code' => $code,
        'frontend_input' => 'select',
        'frontend_label' => ['Pest Color'],
        'option' => ['value' => ['option_0' => [' ']], 'order' => ['option_0' => 1], 'delete' => ['option_0' => '']],
    ]);
    $attribute = attributeParityCreate(attributeParityCode('api'), 'select');
    $api = attributeViaApi('POST', ['label' => ' '], (int) $attribute->getId(), 'product_attribute_option_create');

    expect($admin['errors'])->toBe(['An option must have an admin label.'])
        ->and($api['status'])->toBe(422)
        ->and($api['fields'])->toBe(['label'])
        ->and($api['errors'])->toBe($admin['errors'])
        ->and(attributeParityLoad($code)->getId())->toBeNull();
});

it('keeps the default option when the API adds another option', function () {
    $code = attributeParityCode('default');
    $attribute = attributeParityCreate($code, 'select');
    attributeParityService()->saveOptions($attribute, ['option_0' => ['labels' => [0 => 'Matte'], 'default' => true]]);
    $matteId = (int) array_key_first(attributeParityService()->getOptions(attributeParityLoad($code)));

    $api = attributeViaApi('POST', ['label' => 'Gloss'], (int) $attribute->getId(), 'product_attribute_option_create');

    expect($api['status'])->toBe(200)
        ->and(attributeParityLoad($code)->getDefaultValue())->toBe((string) $matteId);
});
