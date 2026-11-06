<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

uses(Tests\MahoBackendTestCase::class);

/**
 * Run one action of an admin controller with POST parameters.
 */
function attributeAdminNoteRun(string $class, string $controllerName, string $action, array $params): void
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
 * Create a set of $entityTypeCode from its default set, and a user-defined text attribute in the
 * first group of the default set only.
 *
 * @return array{0: Mage_Eav_Model_Entity_Attribute_Set, 1: Mage_Eav_Model_Entity_Attribute}
 */
function attributeAdminNoteSetAndAttribute(string $entityTypeCode, string $attributeModel, string $suffix): array
{
    $type = Mage::getSingleton('eav/config')->getEntityType($entityTypeCode);
    $defaultSetId = (int) $type->getDefaultAttributeSetId();
    $set = Mage::getModel('eav/entity_attribute_set')
        ->setEntityTypeId((int) $type->getId())
        ->setAttributeSetName('Pest Change ' . $suffix);
    $set->save();
    $set->initFromSkeleton($defaultSetId)->save();

    $attribute = Mage::getModel($attributeModel)->setData([
        'entity_type_id' => (int) $type->getId(),
        'attribute_code' => 'pest_change_' . $suffix,
        'backend_type' => 'varchar',
        'frontend_input' => 'text',
        'frontend_label' => 'Pest Change ' . $suffix,
        'is_user_defined' => 1,
        'is_global' => 1,
        'attribute_set_id' => $defaultSetId,
        'attribute_group_id' => (int) Mage::getResourceModel('eav/entity_attribute_group_collection')
            ->setAttributeSetFilter($defaultSetId)
            ->setSortOrder()
            ->getFirstItem()
            ->getId(),
    ]);
    $attribute->save();
    Mage::getSingleton('eav/config')->clear();
    return [$set, $attribute];
}

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

it('names the attributes whose values a change of the set deletes', function () {
    [$set, $attribute] = attributeAdminNoteSetAndAttribute(Mage_Catalog_Model_Category::ENTITY, 'catalog/resource_eav_attribute', $this->suffix);
    array_push($this->records, $set, $attribute);
    $defaultSetId = (int) Mage::getSingleton('eav/config')->getEntityType(Mage_Catalog_Model_Category::ENTITY)->getDefaultAttributeSetId();

    $questions = Mage::helper('eav')->getSetChangeQuestions($defaultSetId, [
        ['value' => $defaultSetId, 'label' => 'Default'],
        ['value' => (int) $set->getId(), 'label' => 'Pest'],
    ]);

    expect($questions)->toHaveKey((string) $set->getId())
        ->and($questions[(string) $set->getId()])->toContain($attribute->getFrontendLabel())
        ->and($questions)->not->toHaveKey((string) $defaultSetId)
        ->and(Mage::helper('eav')->getSetChangeQuestions(0, [['value' => (int) $set->getId(), 'label' => 'Pest']]))->toBe([]);
});

it('changes the set of a saved category from the admin save', function () {
    [$set, $attribute] = attributeAdminNoteSetAndAttribute(Mage_Catalog_Model_Category::ENTITY, 'catalog/resource_eav_attribute', $this->suffix);
    array_push($this->records, $set, $attribute);
    $category = Mage::getModel('catalog/category')
        ->setName('Pest Set Change ' . $this->suffix)
        ->setIsActive(1)
        ->setPath('1/' . Mage::app()->getStore(1)->getRootCategoryId());
    $category->assignAttributeSet(null)->save();
    $this->records[] = $category;

    attributeAdminNoteRun(Mage_Adminhtml_Catalog_CategoryController::class, 'catalog_category', 'save', [
        'id' => $category->getId(),
        'store' => 0,
        'general' => ['name' => 'Pest Set Change ' . $this->suffix, 'is_active' => 1, 'attribute_set_id' => (int) $set->getId()],
        'use_config' => ['available_sort_by', 'default_sort_by'],
    ]);

    expect(Mage::getModel('catalog/category')->load($category->getId())->getAttributeSetId())->toBe((int) $set->getId());
});

it('keeps the set of a saved category when the admin save posts no set', function () {
    [$set, $attribute] = attributeAdminNoteSetAndAttribute(Mage_Catalog_Model_Category::ENTITY, 'catalog/resource_eav_attribute', $this->suffix);
    array_push($this->records, $set, $attribute);
    $category = Mage::getModel('catalog/category')
        ->setName('Pest Set Keep ' . $this->suffix)
        ->setIsActive(1)
        ->setPath('1/' . Mage::app()->getStore(1)->getRootCategoryId())
        ->setAttributeSetId((int) $set->getId());
    $category->save();
    $this->records[] = $category;

    attributeAdminNoteRun(Mage_Adminhtml_Catalog_CategoryController::class, 'catalog_category', 'save', [
        'id' => $category->getId(),
        'store' => 0,
        'general' => ['name' => 'Pest Set Keep Renamed ' . $this->suffix, 'is_active' => 1],
        'use_config' => ['available_sort_by', 'default_sort_by'],
    ]);

    $saved = Mage::getModel('catalog/category')->setStoreId(0)->load($category->getId());
    expect($saved->getName())->toBe('Pest Set Keep Renamed ' . $this->suffix)
        ->and($saved->getAttributeSetId())->toBe((int) $set->getId());
});

it('changes the set of a saved blog post from the admin save', function () {
    [$set, $attribute] = attributeAdminNoteSetAndAttribute(Maho_Blog_Model_Post::ENTITY, 'blog/resource_eav_attribute', $this->suffix);
    array_push($this->records, $set, $attribute);
    $post = Mage::getModel('blog/post')->setData([
        'title' => 'Pest Set Change ' . $this->suffix,
        'url_key' => 'pest-set-change-' . $this->suffix,
        'content' => '<p>Body</p>',
        'is_active' => 1,
        'stores' => [0],
    ]);
    $post->save();
    $this->records[] = $post;

    attributeAdminNoteRun(Maho_Blog_Adminhtml_Blog_PostController::class, 'blog_post', 'save', [
        'id' => $post->getId(),
        'title' => 'Pest Set Change ' . $this->suffix,
        'url_key' => 'pest-set-change-' . $this->suffix,
        'content' => '<p>Body</p>',
        'is_active' => 1,
        'stores' => [0],
        'attribute_set_id' => (int) $set->getId(),
    ]);

    expect(Mage::getModel('blog/post')->load($post->getId())->getAttributeSetId())->toBe((int) $set->getId());
});

it('names the forms, the customer groups and the websites of a customer attribute', function () {
    $type = Mage::getSingleton('eav/config')->getEntityType('customer');
    $attribute = Mage::getModel('customer/attribute')->setData([
        'entity_type_id' => (int) $type->getId(),
        'attribute_code' => 'pest_note_' . $this->suffix,
        'backend_type' => 'varchar',
        'frontend_input' => 'text',
        'frontend_label' => 'Pest Note',
        'is_user_defined' => 1,
        'is_visible' => 1,
        'is_system' => 0,
        'used_in_forms' => ['customer_account_create', 'adminhtml_customer'],
    ]);
    $attribute->save();
    $this->records[] = $attribute;

    $note = (string) Mage::getModel('customer/attribute')->load($attribute->getId())->getAttributeNote();

    expect($note)->toContain('Forms that show the attribute: Admin Customer, Customer Registration.')
        ->and($note)->toContain('Customer groups: all, because the attribute is in no set.')
        ->and($note)->toContain(Mage::app()->getWebsite(1)->getName() . ' (Optional)');
});

it('tells the admin that only a theme shows a CMS page attribute', function () {
    $attribute = Mage::getModel('cms/attribute')->setData('attribute_code', 'pest_campaign');

    expect((string) $attribute->getAttributeNote())->toContain("\$this->getPage()->getData('pest_campaign')");
});
