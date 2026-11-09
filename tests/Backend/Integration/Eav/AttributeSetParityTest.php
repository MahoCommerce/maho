<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Post;
use Mage\Catalog\Api\AttributeSetProcessor;
use Mage\Catalog\Api\AttributeSetProvider;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Tests\Helpers\AdminApiParity;

uses(Tests\MahoBackendTestCase::class);

/**
 * The admin page of the product attribute sets and the attribute set API create, delete and change a set
 * through Mage_Eav_Service_Attribute_Set (#1348). Each test gives the same input to both transports and
 * compares the outcome.
 */

const SET_PARITY_PREFIX = 'Pest Set Parity';

function setParityDefaultId(): int
{
    return (int) Mage::getSingleton('eav/config')->getEntityType(Mage_Catalog_Model_Product::ENTITY)->getDefaultAttributeSetId();
}

function setParityCount(string $name): int
{
    return Mage::getResourceModel('eav/entity_attribute_set_collection')
        ->setEntityTypeFilter((int) Mage::getSingleton('eav/config')->getEntityType(Mage_Catalog_Model_Product::ENTITY)->getId())
        ->addFieldToFilter('attribute_set_name', $name)
        ->getSize();
}

function setParityCreate(string $name): Mage_Eav_Model_Entity_Attribute_Set
{
    /** @var Mage_Eav_Service_Attribute_Set $service */
    $service = Mage::getService('eav/attribute_set');
    return $service->create($service->newSet(Mage_Catalog_Model_Product::ENTITY)->setAttributeSetName($name));
}

/**
 * @param array<string, mixed> $fields
 * @return array{success: list<string>, errors: list<string>}
 */
function setParityAdmin(string $action, array $fields): array
{
    return AdminApiParity::admin(Mage_Adminhtml_Catalog_Product_SetController::class, $action, $fields);
}

/**
 * Post the tree $tree of the set editor for $set, and return the JSON answer of the save action.
 *
 * @param array<string, mixed> $tree
 * @return array<string, mixed>
 */
function setParityAdminTree(Mage_Eav_Model_Entity_Attribute_Set $set, array $tree): array
{
    $request = new Mage_Core_Controller_Request_Http(SymfonyRequest::create('http://localhost/admin/catalog_product_set/save', 'POST', [
        'id' => $set->getId(),
        'confirmed' => '1',
        'data' => Mage::helper('core')->jsonEncode($tree + [
            'attribute_set_name' => $set->getAttributeSetName(),
            'groups' => setParityGroups($set),
            'attributes' => [],
            'not_attributes' => [],
            'removeGroups' => [],
        ]),
    ]));
    $request->setRouteName('adminhtml')->setControllerName('catalog_product_set')->setActionName('save')->setDispatched(true);
    Mage::app()->setRequest($request);
    $response = new Mage_Core_Controller_Response_Http();
    try {
        new Mage_Adminhtml_Catalog_Product_SetController($request, $response)->saveAction();
    } catch (Throwable) {
        // The url of the answer needs a dispatched admin request. The body is the outcome
    }
    AdminApiParity::adminMessages();
    return Mage::helper('core')->jsonDecode((string) $response->getBody());
}

/**
 * @return list<array{0: int, 1: string, 2: int}> the groups of $set as the set editor posts them
 */
function setParityGroups(Mage_Eav_Model_Entity_Attribute_Set $set): array
{
    $groups = [];
    foreach (Mage::getResourceModel('eav/entity_attribute_group_collection')->setAttributeSetFilter((int) $set->getId())->setSortOrder() as $group) {
        $groups[] = [(int) $group->getId(), (string) $group->getAttributeGroupName(), (int) $group->getSortOrder()];
    }
    return $groups;
}

/**
 * @param array<string, mixed> $body
 * @param array<string, int> $uriVariables
 * @return array{status: int, result: mixed, errors: list<string>, fields: list<string>}
 */
function setParityApi(string $method, string $path, array $body, array $uriVariables = [], ?string $operationName = null): array
{
    $request = AdminApiParity::jsonRequest($method, $path, $body);
    $operation = $method === 'DELETE' ? new Delete(name: $operationName) : new Post(name: $operationName);
    return AdminApiParity::api($request, fn() => new AttributeSetProcessor(AdminApiParity::adminSecurity(), new AttributeSetProvider())
        ->process(null, $operation, $uriVariables, ['request' => $request]));
}

function setParityRowExists(int $setId, int $attributeId): bool
{
    $adapter = Mage::getSingleton('core/resource')->getConnection('core_read');
    return (bool) $adapter->fetchOne($adapter->select()
        ->from(Mage::getSingleton('core/resource')->getTableName('eav/entity_attribute'), ['entity_attribute_id'])
        ->where('attribute_set_id = ?', $setId)
        ->where('attribute_id = ?', $attributeId));
}

function setParityCleanup(): void
{
    foreach (Mage::getResourceModel('eav/entity_attribute_set_collection')->addFieldToFilter('attribute_set_name', ['like' => SET_PARITY_PREFIX . '%']) as $set) {
        $set->delete();
    }
    Mage::getSingleton('eav/config')->clear();
}

beforeEach(function (): void {
    Mage::unregister(Mage_Core_Model_Session_Abstract::REGISTRY_KEY);
    $session = new Session(new MockArraySessionStorage());
    $session->start();
    Mage::register(Mage_Core_Model_Session_Abstract::REGISTRY_KEY, $session);
    setParityCleanup();
    $this->name = SET_PARITY_PREFIX . ' ' . substr(uniqid(), -6);
});

afterEach(function (): void {
    setParityCleanup();
});

it('creates the same set from the default set on each transport', function (): void {
    $admin = setParityAdmin('save', ['gotoEdit' => '1', 'attribute_set_name' => $this->name, 'skeleton_set' => setParityDefaultId()]);
    expect($admin)->toBe(['success' => ['The attribute set has been saved.'], 'errors' => []])
        ->and(setParityCount($this->name))->toBe(1);
    $adminGroups = array_column(setParityGroups(Mage::getService('eav/attribute_set')->getById(
        (int) Mage::getModel('eav/entity_attribute_set')->load($this->name, 'attribute_set_name')->getId(),
        Mage_Catalog_Model_Product::ENTITY,
    )), 1);
    setParityCleanup();

    $api = setParityApi('POST', '/api/rest/v2/attribute-sets', ['name' => " <b>{$this->name}</b> "]);
    expect($api['status'])->toBe(200)
        ->and($api['result']->attributeSetName)->toBe($this->name)
        ->and(array_column($api['result']->groups, 'name'))->toBe($adminGroups);
});

it('rejects the same name with the same message on each transport', function (Closure $input): void {
    [$name, $status, $field, $message] = $input($this->name);

    $admin = setParityAdmin('save', ['gotoEdit' => '1', 'attribute_set_name' => $name, 'skeleton_set' => setParityDefaultId()]);
    $api = setParityApi('POST', '/api/rest/v2/attribute-sets', ['name' => $name]);

    expect($admin)->toBe(['success' => [], 'errors' => [$message]])
        ->and($api['status'])->toBe($status)
        ->and($api['errors'])->toBe([$message])
        ->and($api['fields'])->toBe($field === null ? [] : [$field]);
})->with([
    'an empty name' => [fn(string $name): array => ['  ', 422, 'attributeSetName', 'The name of an attribute set must have 1 to 255 characters.']],
    'a name that is too long' => [fn(string $name): array => [str_repeat('x', 256), 422, 'attributeSetName', 'The name of an attribute set must have 1 to 255 characters.']],
    'the name of another set' => [function (string $name): array {
        setParityCreate($name);
        return [$name, 409, null, "Attribute set with the \"{$name}\" name already exists."];
    }],
]);

it('refuses a skeleton of another entity type on each transport', function (): void {
    $categorySetId = (int) Mage::getSingleton('eav/config')->getEntityType(Mage_Catalog_Model_Category::ENTITY)->getDefaultAttributeSetId();
    $message = 'The skeleton must be an attribute set of the same entity type.';

    $admin = setParityAdmin('save', ['gotoEdit' => '1', 'attribute_set_name' => $this->name, 'skeleton_set' => $categorySetId]);
    $api = setParityApi('POST', '/api/rest/v2/attribute-sets', ['name' => $this->name, 'skeletonId' => $categorySetId]);

    expect($admin)->toBe(['success' => [], 'errors' => [$message]])
        ->and($api)->toMatchArray(['status' => 422, 'errors' => [$message], 'fields' => ['skeletonId']])
        ->and(setParityCount($this->name))->toBe(0);
});

it('deletes a set on each transport', function (): void {
    $set = setParityCreate($this->name);
    expect(setParityAdmin('delete', ['id' => $set->getId()]))->toBe(['success' => ['The attribute set has been removed.'], 'errors' => []])
        ->and(setParityCount($this->name))->toBe(0);

    $set = setParityCreate($this->name);
    expect(setParityApi('DELETE', "/api/rest/v2/attribute-sets/{$set->getId()}", [], ['id' => (int) $set->getId()])['status'])->toBe(200)
        ->and(setParityCount($this->name))->toBe(0);
});

it('refuses to delete the default set and an unknown set on each transport', function (Closure $input): void {
    [$setId, $status, $message] = $input();

    $admin = setParityAdmin('delete', ['id' => $setId]);
    $api = setParityApi('DELETE', "/api/rest/v2/attribute-sets/{$setId}", [], ['id' => $setId]);

    expect($admin)->toBe(['success' => [], 'errors' => [$message]])
        ->and($api)->toMatchArray(['status' => $status, 'errors' => [$message]]);
})->with([
    'the default set' => [fn(): array => [setParityDefaultId(), 409, 'The default attribute set cannot be deleted.']],
    'a set of another entity type' => [fn(): array => [
        (int) Mage::getSingleton('eav/config')->getEntityType(Mage_Catalog_Model_Category::ENTITY)->getDefaultAttributeSetId(),
        404,
        'Attribute set not found',
    ]],
    'an unknown ID' => [fn(): array => [999999999, 404, 'Attribute set not found']],
]);

it('refuses a group name that another group of the set has on each transport', function (): void {
    $set = setParityCreate($this->name);
    [$first, $second] = setParityGroups($set);
    $message = "Attribute group with the \"{$first[1]}\" name already exists";

    $groups = setParityGroups($set);
    $groups[1][1] = $first[1];
    $admin = setParityAdminTree($set, ['groups' => $groups]);
    $api = setParityApi('POST', "/api/rest/v2/attribute-sets/{$set->getId()}/groups", ['name' => $first[1]], ['id' => (int) $set->getId()], 'attribute_set_group_create');

    expect($admin)->toBe(['error' => true, 'message' => $message])
        ->and($api)->toMatchArray(['status' => 409, 'errors' => [$message]])
        ->and(array_column(setParityGroups($set), 1))->toContain($second[1]);
});

it('refuses to take a system attribute out of a set on each transport', function (): void {
    $set = setParityCreate($this->name);
    $nameId = (int) Mage::getSingleton('eav/config')->getAttribute(Mage_Catalog_Model_Product::ENTITY, 'name')->getId();
    $adapter = Mage::getSingleton('core/resource')->getConnection('core_read');
    $rowId = (int) $adapter->fetchOne($adapter->select()
        ->from(Mage::getSingleton('core/resource')->getTableName('eav/entity_attribute'), ['entity_attribute_id'])
        ->where('attribute_set_id = ?', (int) $set->getId())
        ->where('attribute_id = ?', $nameId));
    $message = 'A system attribute cannot be removed from an attribute set.';

    $admin = setParityAdminTree($set, ['not_attributes' => [$rowId]]);
    $api = setParityApi('DELETE', "/api/rest/v2/attribute-sets/{$set->getId()}/attributes/{$nameId}", [], ['id' => (int) $set->getId(), 'attributeId' => $nameId], 'attribute_set_attribute_unassign');

    expect($admin)->toBe(['error' => true, 'message' => $message])
        ->and($api)->toMatchArray(['status' => 409, 'errors' => [$message]])
        ->and(setParityRowExists((int) $set->getId(), $nameId))->toBeTrue();
});

it('refuses to take an attribute of configurable products out of their set on each transport', function (): void {
    $adapter = Mage::getSingleton('core/resource')->getConnection('core_read');
    $row = $adapter->fetchRow($adapter->select()
        ->from(['sa' => Mage::getSingleton('core/resource')->getTableName('catalog/product_super_attribute')], ['attribute_id'])
        ->join(['p' => Mage::getSingleton('core/resource')->getTableName('catalog/product')], 'p.entity_id = sa.product_id', ['attribute_set_id'])
        ->join(['a' => Mage::getSingleton('core/resource')->getTableName('eav/attribute')], 'a.attribute_id = sa.attribute_id', [])
        ->where('a.is_user_defined = ?', 1)
        ->limit(1));
    if (!$row) {
        $this->markTestSkipped('The test store has no configurable product');
    }
    $set = Mage::getService('eav/attribute_set')->getById((int) $row['attribute_set_id'], Mage_Catalog_Model_Product::ENTITY);
    $attribute = Mage::getSingleton('eav/config')->getAttribute(Mage_Catalog_Model_Product::ENTITY, (int) $row['attribute_id']);
    $rowId = (int) $adapter->fetchOne($adapter->select()
        ->from(Mage::getSingleton('core/resource')->getTableName('eav/entity_attribute'), ['entity_attribute_id'])
        ->where('attribute_set_id = ?', (int) $set->getId())
        ->where('attribute_id = ?', (int) $attribute->getId()));
    $message = "Attribute '{$attribute->getAttributeCode()}' used in configurable products";

    $admin = setParityAdminTree($set, ['not_attributes' => [$rowId]]);
    $api = setParityApi('DELETE', "/api/rest/v2/attribute-sets/{$set->getId()}/attributes/{$attribute->getId()}", [], ['id' => (int) $set->getId(), 'attributeId' => (int) $attribute->getId()], 'attribute_set_attribute_unassign');

    expect($admin)->toBe(['error' => true, 'message' => $message])
        ->and($api)->toMatchArray(['status' => 409, 'errors' => [$message]])
        ->and(setParityRowExists((int) $set->getId(), (int) $attribute->getId()))->toBeTrue();
});
