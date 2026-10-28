<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Tests\Helpers\AdminRoleFixture;

uses(Tests\MahoBackendTestCase::class);

/**
 * Run one action of an admin controller with the given request parameters.
 */
function categoryAttributeAdminRun(string $class, string $controllerName, string $action, array $params): void
{
    $request = new Mage_Core_Controller_Request_Http(
        SymfonyRequest::create("/index.php/admin/{$controllerName}/{$action}", 'POST', $params),
    );
    $request->setRouteName('adminhtml')
        ->setControllerName($controllerName)
        ->setActionName($action)
        ->setDispatched(true);
    Mage::app()->setRequest($request);

    $controller = new $class($request, new Mage_Core_Controller_Response_Http());
    try {
        $controller->{$action . 'Action'}();
    } catch (Throwable) {
        // The trailing redirect builds an admin url that needs no assertion here
    }
}

/**
 * @return array{errors: list<string>, success: list<string>}
 */
function categoryAttributeAdminMessages(): array
{
    $messages = Mage::getSingleton('adminhtml/session')->getMessages(true);
    return [
        'errors' => array_map(fn($m) => $m->getText(), $messages->getErrors()),
        'success' => array_map(fn($m) => $m->getText(), $messages->getItemsByType('success')),
    ];
}

function categoryAttributeAdminTypeId(): int
{
    return (int) Mage::getSingleton('eav/config')->getEntityType(Mage_Catalog_Model_Category::ENTITY)->getId();
}

function categoryAttributeAdminDefaultSetId(): int
{
    return (int) Mage::getSingleton('eav/config')->getEntityType(Mage_Catalog_Model_Category::ENTITY)->getDefaultAttributeSetId();
}

function categoryAttributeAdminCreateSet(string $name): Mage_Eav_Model_Entity_Attribute_Set
{
    $set = Mage::getModel('eav/entity_attribute_set')
        ->setEntityTypeId(categoryAttributeAdminTypeId())
        ->setAttributeSetName($name);
    $set->save();
    $set->initFromSkeleton(categoryAttributeAdminDefaultSetId())->save();
    return $set;
}

beforeEach(function () {
    $this->user = AdminRoleFixture::login([
        'admin/catalog/attributes/category_attributes',
        'admin/catalog/attributes/category_sets',
    ]);
    $this->code = 'pest_cat_' . substr(uniqid(), -6);
    $this->setIds = [];
    Mage::getSingleton('adminhtml/session')->getMessages(true);
});

afterEach(function () {
    $attribute = Mage::getModel('catalog/resource_eav_attribute')
        ->loadByCode(Mage_Catalog_Model_Category::ENTITY, $this->code);
    if ($attribute->getId()) {
        $attribute->delete();
    }
    foreach ($this->setIds as $setId) {
        Mage::getModel('eav/entity_attribute_set')->setId($setId)->delete();
    }
    Mage::getSingleton('eav/config')->clear();
    AdminRoleFixture::delete($this->user);
});

it('creates a category dropdown attribute with its options', function () {
    categoryAttributeAdminRun(Mage_Adminhtml_Catalog_Category_AttributeController::class, 'catalog_category_attribute', 'save', [
        'attribute_code' => $this->code,
        'frontend_input' => 'select',
        'frontend_label' => ['Pest <b>Color</b>'],
        'is_global' => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_GLOBAL,
        'is_visible_on_front' => 1,
        'option' => [
            'value' => ['option_0' => ['Red']],
            'order' => ['option_0' => 1],
            'delete' => ['option_0' => ''],
        ],
    ]);

    expect(categoryAttributeAdminMessages()['success'])->toBe(['The attribute has been saved.']);

    $attribute = Mage::getModel('catalog/resource_eav_attribute')
        ->loadByCode(Mage_Catalog_Model_Category::ENTITY, $this->code);
    $labels = array_column($attribute->setStoreId(0)->getSource()->getAllOptions(false), 'label');

    expect((int) $attribute->getEntityTypeId())->toBe(categoryAttributeAdminTypeId())
        ->and($attribute->getIsUserDefined())->toBeTrue()
        ->and($attribute->getBackendType())->toBe('int')
        ->and($attribute->getFrontendLabel())->toBe('Pest Color')
        ->and((int) $attribute->getIsVisibleOnFront())->toBe(1)
        ->and($labels)->toBe(['Red']);
});

it('rejects an input type that the category entity does not offer', function () {
    categoryAttributeAdminRun(Mage_Adminhtml_Catalog_Category_AttributeController::class, 'catalog_category_attribute', 'save', [
        'attribute_code' => $this->code,
        'frontend_input' => 'price',
        'frontend_label' => ['Pest Price'],
    ]);

    $attribute = Mage::getModel('catalog/resource_eav_attribute')
        ->loadByCode(Mage_Catalog_Model_Category::ENTITY, $this->code);

    expect(categoryAttributeAdminMessages()['errors'])->toBe(['Input type "price" not found in the input types list.'])
        ->and($attribute->getId())->toBeNull();
});

it('offers the image input type for categories, not for products', function () {
    $category = Mage::getModel('catalog/resource_eav_attribute')
        ->setEntityTypeId(categoryAttributeAdminTypeId());
    $product = Mage::getModel('catalog/resource_eav_attribute')
        ->setEntityTypeId((int) Mage::getSingleton('eav/config')->getEntityType(Mage_Catalog_Model_Product::ENTITY)->getId());

    expect($category->getInputTypes())->toHaveKey('image')
        ->and($category->getInputTypes())->not->toHaveKey('price')
        ->and($product->getInputTypes())->toHaveKey('price')
        ->and($product->getInputTypes())->not->toHaveKey('image');
});

it('ignores the entity type, the storage and the models in the posted data', function () {
    $productTypeId = (int) Mage::getSingleton('eav/config')->getEntityType(Mage_Catalog_Model_Product::ENTITY)->getId();
    $posted = [
        'entity_type_id' => $productTypeId,
        'backend_type' => 'decimal',
        'backend_model' => 'eav/entity_attribute_backend_array',
        'source_model' => 'eav/entity_attribute_source_boolean',
        'frontend_model' => 'eav/entity_attribute_frontend_datetime',
    ];
    categoryAttributeAdminRun(Mage_Adminhtml_Catalog_Category_AttributeController::class, 'catalog_category_attribute', 'save', $posted + [
        'attribute_code' => $this->code,
        'frontend_input' => 'text',
        'frontend_label' => ['Pest Text'],
    ]);
    $attribute = Mage::getModel('catalog/resource_eav_attribute')
        ->loadByCode(Mage_Catalog_Model_Category::ENTITY, $this->code);

    categoryAttributeAdminRun(Mage_Adminhtml_Catalog_Category_AttributeController::class, 'catalog_category_attribute', 'save', $posted + [
        'attribute_id' => $attribute->getId(),
        'frontend_label' => ['Pest Text'],
    ]);
    $reloaded = Mage::getModel('catalog/resource_eav_attribute')->load($attribute->getId());

    expect((int) $reloaded->getEntityTypeId())->toBe(categoryAttributeAdminTypeId())
        ->and($reloaded->getBackendType())->toBe('varchar')
        ->and($reloaded->getData('backend_model'))->toBeEmpty()
        ->and($reloaded->getData('source_model'))->toBeEmpty()
        ->and($reloaded->getData('frontend_model'))->toBeEmpty();
});

it('does not update a product attribute through the category page', function () {
    $name = Mage::getModel('catalog/resource_eav_attribute')
        ->loadByCode(Mage_Catalog_Model_Product::ENTITY, 'name');

    categoryAttributeAdminRun(Mage_Adminhtml_Catalog_Category_AttributeController::class, 'catalog_category_attribute', 'save', [
        'attribute_id' => $name->getId(),
        'frontend_label' => ['Hijacked'],
    ]);

    $reloaded = Mage::getModel('catalog/resource_eav_attribute')->load($name->getId());
    expect(categoryAttributeAdminMessages()['errors'])->toBe(['This attribute cannot be updated.'])
        ->and($reloaded->getFrontendLabel())->toBe($name->getFrontendLabel());
});

it('deletes a user-defined category attribute and refuses a system one', function () {
    $system = Mage::getModel('catalog/resource_eav_attribute')
        ->loadByCode(Mage_Catalog_Model_Category::ENTITY, 'name');
    categoryAttributeAdminRun(Mage_Adminhtml_Catalog_Category_AttributeController::class, 'catalog_category_attribute', 'delete', [
        'attribute_id' => $system->getId(),
    ]);
    expect(categoryAttributeAdminMessages()['errors'])->toBe(['This attribute cannot be deleted.']);

    $attribute = Mage::getModel('catalog/resource_eav_attribute')->setData([
        'entity_type_id' => categoryAttributeAdminTypeId(),
        'attribute_code' => $this->code,
        'backend_type' => 'varchar',
        'frontend_input' => 'text',
        'frontend_label' => 'Pest Text',
        'is_user_defined' => 1,
    ])->save();

    categoryAttributeAdminRun(Mage_Adminhtml_Catalog_Category_AttributeController::class, 'catalog_category_attribute', 'delete', [
        'attribute_id' => $attribute->getId(),
    ]);

    expect(categoryAttributeAdminMessages()['success'])->toBe(['The attribute has been deleted.'])
        ->and(Mage::getModel('catalog/resource_eav_attribute')->load($attribute->getId())->getId())->toBeNull();
});

it('creates a category set with the groups of the set it is based on', function () {
    $name = 'Pest Set ' . uniqid();
    categoryAttributeAdminRun(Mage_Adminhtml_Catalog_Category_SetController::class, 'catalog_category_set', 'save', [
        'gotoEdit' => '1',
        'attribute_set_name' => $name,
        'skeleton_set' => categoryAttributeAdminDefaultSetId(),
    ]);

    $set = Mage::getResourceModel('eav/entity_attribute_set_collection')
        ->setEntityTypeFilter(categoryAttributeAdminTypeId())
        ->addFieldToFilter('attribute_set_name', $name)
        ->getFirstItem();
    $this->setIds[] = (int) $set->getId();
    $groupCount = fn(int $setId) => Mage::getResourceModel('eav/entity_attribute_group_collection')
        ->setAttributeSetFilter($setId)
        ->getSize();

    expect(categoryAttributeAdminMessages()['success'])->toBe(['The attribute set has been saved.'])
        ->and($set->getId())->not->toBeNull()
        ->and($groupCount((int) $set->getId()))->toBe($groupCount(categoryAttributeAdminDefaultSetId()));
});

it('moves the categories of a deleted set to the default set', function () {
    $set = categoryAttributeAdminCreateSet('Pest Delete ' . uniqid());
    $adapter = Mage::getSingleton('core/resource')->getConnection('core_write');
    $table = Mage::getSingleton('core/resource')->getTableName('catalog/category');
    $categoryId = (int) $adapter->fetchOne($adapter->select()->from($table, ['entity_id'])->where('level = 2')->limit(1));
    $originalSetId = (int) $adapter->fetchOne($adapter->select()->from($table, ['attribute_set_id'])->where('entity_id = ?', $categoryId));
    $adapter->update($table, ['attribute_set_id' => (int) $set->getId()], ['entity_id = ?' => $categoryId]);

    try {
        categoryAttributeAdminRun(Mage_Adminhtml_Catalog_Category_SetController::class, 'catalog_category_set', 'delete', [
            'id' => $set->getId(),
        ]);
        $setId = (int) $adapter->fetchOne($adapter->select()->from($table, ['attribute_set_id'])->where('entity_id = ?', $categoryId));

        expect(categoryAttributeAdminMessages()['success'])->toBe(['The attribute set has been removed.'])
            ->and($setId)->toBe(categoryAttributeAdminDefaultSetId())
            ->and(Mage::getModel('eav/entity_attribute_set')->load($set->getId())->getId())->toBeNull();
    } finally {
        $adapter->update($table, ['attribute_set_id' => $originalSetId], ['entity_id = ?' => $categoryId]);
    }
});

it('refuses to delete the default category set or a product set', function () {
    $productSetId = (int) Mage::getSingleton('eav/config')->getEntityType(Mage_Catalog_Model_Product::ENTITY)->getDefaultAttributeSetId();

    foreach ([categoryAttributeAdminDefaultSetId(), $productSetId] as $setId) {
        categoryAttributeAdminRun(Mage_Adminhtml_Catalog_Category_SetController::class, 'catalog_category_set', 'delete', [
            'id' => $setId,
        ]);
        expect(categoryAttributeAdminMessages()['errors'])->toBe(['This attribute set cannot be deleted.'])
            ->and(Mage::getModel('eav/entity_attribute_set')->load($setId)->getId())->not->toBeNull();
    }
});

it('gives a category the attributes of its own set', function () {
    $set = categoryAttributeAdminCreateSet('Pest Brand ' . uniqid());
    $this->setIds[] = (int) $set->getId();
    $groupId = (int) Mage::getResourceModel('eav/entity_attribute_group_collection')
        ->setAttributeSetFilter((int) $set->getId())
        ->setSortOrder()
        ->getFirstItem()
        ->getId();

    Mage::getModel('catalog/resource_eav_attribute')->setData([
        'entity_type_id' => categoryAttributeAdminTypeId(),
        'attribute_code' => $this->code,
        'backend_type' => 'varchar',
        'frontend_input' => 'text',
        'frontend_label' => 'Pest Brand Story',
        'is_user_defined' => 1,
        'attribute_set_id' => (int) $set->getId(),
        'attribute_group_id' => $groupId,
    ])->save();
    Mage::getSingleton('eav/config')->clear();

    $inSet = Mage::getModel('catalog/category')->setAttributeSetId((int) $set->getId());
    $inDefault = Mage::getModel('catalog/category')->setAttributeSetId(categoryAttributeAdminDefaultSetId());

    expect($inSet->getAttributes())->toHaveKey($this->code)
        ->and($inSet->getAttributes())->toHaveKey('name')
        ->and($inDefault->getAttributes())->not->toHaveKey($this->code);
});
