<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use Mage\Catalog\Api\Category;
use Mage\Catalog\Api\CategoryProcessor;
use Tests\Helpers\AdminApiParity;

uses(Tests\MahoBackendTestCase::class);

/**
 * The admin category page and the category API save, move and delete a category through
 * Mage_Catalog_Service_Category (#1348). Each test gives the same input to both transports and compares the outcome.
 */

function categoryParityUnregister(): void
{
    Mage::unregister('category');
    Mage::unregister('current_category');
}

/**
 * @param array<string, mixed> $fields the fields of the admin form
 * @return array{success: list<string>, errors: list<string>}
 */
function categoryViaAdmin(string $action, array $fields): array
{
    categoryParityUnregister();
    return AdminApiParity::admin(Mage_Adminhtml_Catalog_CategoryController::class, $action, $fields);
}

/**
 * Post $fields to the move action, which answers with JSON.
 *
 * @param array<string, mixed> $fields
 * @return array<string, mixed>
 */
function categoryMoveViaAdmin(array $fields): array
{
    categoryParityUnregister();
    $request = new Mage_Core_Controller_Request_Http(
        Symfony\Component\HttpFoundation\Request::create('http://localhost/admin/catalog_category/move', 'POST', $fields),
    );
    $request->setRouteName('adminhtml')->setControllerName('catalog_category')->setActionName('move')->setDispatched(true);
    Mage::app()->setRequest($request);
    $response = new Mage_Core_Controller_Response_Http();
    new Mage_Adminhtml_Catalog_CategoryController($request, $response)->moveAction();
    return json_decode((string) $response->getBody(), true);
}

/**
 * @param array<string, mixed> $body the JSON body
 * @return array{status: int, result: mixed, errors: list<string>, fields: list<string>}
 */
function categoryViaApi(array $body, ?int $id = null, bool $delete = false): array
{
    $method = $delete ? 'DELETE' : ($id ? 'PUT' : 'POST');
    $request = AdminApiParity::jsonRequest($method, '/api/rest/v2/categories' . ($id ? "/{$id}" : ''), $body);
    $dto = new Category();
    foreach ($body as $property => $value) {
        $dto->{$property} = $value;
    }
    $operation = match ($method) {
        'DELETE' => new Delete(),
        'PUT' => new Put(),
        default => new Post(),
    };
    return AdminApiParity::api($request, fn() => new CategoryProcessor(AdminApiParity::adminSecurity())
        ->process($delete ? null : $dto, $operation, $id ? ['id' => $id] : [], ['request' => $request]));
}

function categoryParityCreate(string $name, int $parentId): Mage_Catalog_Model_Category
{
    /** @var Mage_Catalog_Service_Category $service */
    $service = Mage::getService('catalog/category');
    $category = $service->newCategory($parentId)->setName($name)->setIsActive(1)->setIncludeInMenu(1);
    return $service->save($category);
}

function categoryParityCleanup(): void
{
    Mage::register('isSecureArea', true, true);
    $collection = Mage::getModel('catalog/category')->getCollection()
        ->addAttributeToFilter('name', ['like' => 'Pest Parity%'])
        ->setOrder('level', 'DESC');
    foreach ($collection as $category) {
        try {
            Mage::getModel('catalog/category')->load($category->getId())->delete();
        } catch (Throwable) {
            // A parent removed the category first
        }
    }
    Mage::unregister('isSecureArea');
}

beforeEach(function () {
    $this->rootId = (int) Mage::app()->getDefaultStoreView()->getRootCategoryId();
    categoryParityCleanup();
});

afterEach(function () {
    categoryParityCleanup();
    categoryParityUnregister();
});

it('creates a category under the same parent on both transports', function () {
    $admin = categoryViaAdmin('save', ['parent' => $this->rootId, 'general' => ['name' => 'Pest Parity Admin', 'is_active' => 1, 'include_in_menu' => 1]]);
    $api = categoryViaApi(['name' => 'Pest Parity Api', 'parentId' => $this->rootId]);

    $saved = Mage::getModel('catalog/category')->loadByAttribute('name', 'Pest Parity Admin');
    expect($admin['errors'])->toBe([])
        ->and($admin['success'])->toBe(['The category has been saved.'])
        ->and((int) $saved->getParentId())->toBe($this->rootId)
        ->and($api['status'])->toBe(200)
        ->and($api['result']->parentId)->toBe($this->rootId)
        ->and($api['result']->urlKey)->toBe('pest-parity-api');
});

it('refuses a parent that does not exist on both transports', function () {
    $admin = categoryViaAdmin('save', ['parent' => 999999999, 'general' => ['name' => 'Pest Parity Orphan', 'is_active' => 1, 'include_in_menu' => 1]]);
    $api = categoryViaApi(['name' => 'Pest Parity Orphan', 'parentId' => 999999999]);

    expect($admin['errors'])->toBe(['Category save error: Parent category not found'])
        ->and($api['status'])->toBe(422)
        ->and($api['errors'])->toBe(['Parent category not found']);
});

it('refuses a category without a name on both transports', function () {
    $admin = categoryViaAdmin('save', ['parent' => $this->rootId, 'general' => ['name' => ' ', 'is_active' => 1, 'include_in_menu' => 1]]);
    $api = categoryViaApi(['name' => ' ', 'parentId' => $this->rootId]);

    expect($admin['errors'])->toBe(['Category save error: Attribute "Name" is required.'])
        ->and($api['status'])->toBe(422)
        ->and($api['fields'])->toBe(['name'])
        ->and($api['errors'])->toBe(['Attribute "Name" is required.']);
});

it('refuses a layout update that names a template path on both transports', function () {
    $xml = '<reference name="content"><block type="core/template" template="../../../../app/etc/local.xml"/></reference>';
    $admin = categoryViaAdmin('save', ['parent' => $this->rootId, 'general' => ['name' => 'Pest Parity Layout', 'is_active' => 1, 'include_in_menu' => 1, 'custom_layout_update' => $xml]]);
    $api = categoryViaApi(['name' => 'Pest Parity Layout', 'parentId' => $this->rootId, 'customLayoutUpdate' => $xml]);

    expect($api['status'])->toBe(422)
        ->and($api['fields'])->toBe(['customLayoutUpdate'])
        ->and($admin['errors'])->toBe(['Category save error: ' . $api['errors'][0]]);
});

it('refuses a sort order that the catalog does not have on both transports', function () {
    $admin = categoryViaAdmin('save', ['parent' => $this->rootId, 'general' => ['name' => 'Pest Parity Sort', 'is_active' => 1, 'include_in_menu' => 1, 'available_sort_by' => ['not_a_sort_code']]]);
    $api = categoryViaApi(['name' => 'Pest Parity Sort', 'parentId' => $this->rootId, 'availableSortBy' => ['not_a_sort_code']]);

    expect($api['status'])->toBe(422)
        ->and($api['fields'])->toBe(['availableSortBy'])
        ->and($admin['errors'])->toBe(['Category save error: ' . $api['errors'][0]]);
});

it('refuses to delete the root category of a store on both transports', function () {
    $admin = categoryViaAdmin('delete', ['id' => $this->rootId]);
    $api = categoryViaApi([], $this->rootId, delete: true);

    expect($admin['errors'])->toBe(["Category delete error: Can't delete root category."])
        ->and($api['status'])->toBe(409)
        ->and($api['errors'])->toBe(["Can't delete root category."])
        ->and(Mage::getModel('catalog/category')->load($this->rootId)->getId())->not->toBeNull();
});

it('deletes a category on both transports', function () {
    $forAdmin = categoryParityCreate('Pest Parity Delete Admin', $this->rootId);
    $forApi = categoryParityCreate('Pest Parity Delete Api', $this->rootId);

    $admin = categoryViaAdmin('delete', ['id' => (int) $forAdmin->getId()]);
    $api = categoryViaApi([], (int) $forApi->getId(), delete: true);

    expect($admin['success'])->toBe(['The category has been deleted.'])
        ->and($api['status'])->toBe(200)
        ->and(Mage::getModel('catalog/category')->load($forAdmin->getId())->getId())->toBeNull()
        ->and(Mage::getModel('catalog/category')->load($forApi->getId())->getId())->toBeNull();
});

it('refuses to move a root category on both transports', function () {
    $target = categoryParityCreate('Pest Parity Root Target', Mage_Catalog_Model_Category::TREE_ROOT_ID);
    $root = categoryParityCreate('Pest Parity Root', Mage_Catalog_Model_Category::TREE_ROOT_ID);

    $admin = categoryMoveViaAdmin(['id' => (int) $root->getId(), 'pid' => (int) $target->getId()]);
    $api = categoryViaApi(['parentId' => (int) $target->getId()], (int) $root->getId());

    expect($admin['message'] ?? null)->toBe('Category move error: A root category cannot be moved.')
        ->and($api['status'])->toBe(409)
        ->and($api['errors'])->toBe(['A root category cannot be moved.']);
});

it('refuses to move a category under its own subcategory on both transports', function () {
    $parent = categoryParityCreate('Pest Parity Parent', $this->rootId);
    $child = categoryParityCreate('Pest Parity Child', (int) $parent->getId());

    $admin = categoryMoveViaAdmin(['id' => (int) $parent->getId(), 'pid' => (int) $child->getId()]);
    $api = categoryViaApi(['parentId' => (int) $child->getId()], (int) $parent->getId());

    $message = 'A category cannot be moved under one of its own subcategories.';
    expect($admin['message'] ?? null)->toBe("Category move error: {$message}")
        ->and($api['status'])->toBe(422)
        ->and($api['errors'])->toBe([$message])
        ->and((int) Mage::getModel('catalog/category')->load($parent->getId())->getParentId())->toBe($this->rootId);
});

it('moves a category on both transports', function () {
    $target = categoryParityCreate('Pest Parity Target', $this->rootId);
    $forAdmin = categoryParityCreate('Pest Parity Move Admin', $this->rootId);
    $forApi = categoryParityCreate('Pest Parity Move Api', $this->rootId);

    $admin = categoryMoveViaAdmin(['id' => (int) $forAdmin->getId(), 'pid' => (int) $target->getId()]);
    $api = categoryViaApi(['parentId' => (int) $target->getId()], (int) $forApi->getId());

    expect($admin)->toBe(['success' => true])
        ->and($api['status'])->toBe(200)
        ->and((int) Mage::getModel('catalog/category')->load($forAdmin->getId())->getParentId())->toBe((int) $target->getId())
        ->and($api['result']->parentId)->toBe((int) $target->getId());
});

it('reverts a store view value with use default, which the API refuses without a store view', function () {
    $storeId = (int) Mage::app()->getDefaultStoreView()->getId();
    $category = categoryParityCreate('Pest Parity Default', $this->rootId);
    Mage::getModel('catalog/category')->setStoreId($storeId)->load($category->getId())->setData('meta_title', 'Store title')->save();

    $admin = categoryViaAdmin('save', ['id' => (int) $category->getId(), 'store' => $storeId, 'use_default' => ['meta_title'], 'general' => ['name' => 'Pest Parity Default']]);
    $api = categoryViaApi(['useDefault' => ['meta_title']], (int) $category->getId());

    expect($admin['errors'])->toBe([])
        ->and(Mage::getModel('catalog/category')->setStoreId($storeId)->load($category->getId())->getMetaTitle())->toBeNull()
        ->and($api['status'])->toBe(422)
        ->and($api['fields'])->toBe(['useDefault']);
});
