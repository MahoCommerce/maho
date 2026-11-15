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
 * Run one action of the System > Attributes set pages for $entityType and return the response.
 */
function setRemoveTestRun(string $entityType, string $action, array $params): Mage_Core_Controller_Response_Http
{
    $request = new Mage_Core_Controller_Request_Http(
        SymfonyRequest::create("/index.php/admin/eav_set/{$action}", 'POST', $params + ['entity_type' => $entityType]),
    );
    $request->setRouteName('adminhtml')->setControllerName('eav_set')->setActionName($action)->setDispatched(true);
    Mage::app()->setRequest($request);
    $response = new Mage_Core_Controller_Response_Http();
    $controller = new Mage_Adminhtml_Eav_SetController($request, $response);
    try {
        $controller->{$action . 'Action'}();
    } catch (Throwable) {
        // The trailing redirect builds an admin url that needs no assertion here
    }
    return $response;
}

/**
 * Post a save of $set that takes $attribute out of the set, as the set editor does.
 */
function setRemoveTestSave(string $entityType, Mage_Eav_Model_Entity_Attribute_Set $set, Mage_Eav_Model_Entity_Attribute_Abstract $attribute, bool $confirmed): Mage_Core_Controller_Response_Http
{
    $groups = [];
    foreach (Mage::getResourceModel('eav/entity_attribute_group_collection')->setAttributeSetFilter((int) $set->getId())->setSortOrder() as $group) {
        $groups[] = [$group->getId(), $group->getAttributeGroupName(), (int) $group->getSortOrder()];
    }
    $data = [
        'attribute_set_name' => $set->getAttributeSetName(),
        'groups' => $groups,
        'attributes' => [],
        'not_attributes' => [setRemoveTestRowId($set, $attribute)],
        'removeGroups' => [],
    ];
    return setRemoveTestRun($entityType, 'save', [
        'id' => $set->getId(),
        'data' => Mage::helper('core')->jsonEncode($data),
    ] + ($confirmed ? ['confirmed' => '1'] : []));
}

function setRemoveTestRowId(Mage_Eav_Model_Entity_Attribute_Set $set, Mage_Eav_Model_Entity_Attribute_Abstract $attribute): int
{
    $adapter = Mage::getSingleton('core/resource')->getConnection('core_read');
    return (int) $adapter->fetchOne($adapter->select()
        ->from(Mage::getSingleton('core/resource')->getTableName('eav/entity_attribute'), ['entity_attribute_id'])
        ->where('attribute_set_id = ?', (int) $set->getId())
        ->where('attribute_id = ?', (int) $attribute->getId()));
}

function setRemoveTestFirstGroupId(int $setId): int
{
    return (int) Mage::getResourceModel('eav/entity_attribute_group_collection')
        ->setAttributeSetFilter($setId)
        ->setSortOrder()
        ->getFirstItem()
        ->getId();
}

beforeEach(function () {
    $this->suffix = substr(uniqid(), -6);
    $this->records = [];
    $this->user = AdminRoleFixture::login(['admin/system/attributes']);
    Mage::getSingleton('adminhtml/session')->getMessages(true);
});

afterEach(function () {
    foreach (array_reverse($this->records) as $record) {
        if ($record->getId()) {
            $record->delete();
        }
    }
    Mage::getSingleton('eav/config')->clear();
    AdminRoleFixture::delete($this->user);
    // A message that an action left in the admin session is not an outcome of the next test
    Mage::getSingleton('adminhtml/session')->getMessages(true);
});

it('asks before a save takes an attribute out of a category set, and names the categories with a value', function () {
    $type = Mage::getSingleton('eav/config')->getEntityType(Mage_Catalog_Model_Category::ENTITY);
    $set = Mage::getModel('eav/entity_attribute_set')
        ->setEntityTypeId((int) $type->getId())
        ->setAttributeSetName('Pest Remove ' . $this->suffix);
    $set->save();
    $set->initFromSkeleton((int) $type->getDefaultAttributeSetId())->save();
    $this->records[] = $set;
    $attribute = Mage::getModel('catalog/resource_eav_attribute')->setData([
        'entity_type_id' => (int) $type->getId(),
        'attribute_code' => 'pest_season_' . $this->suffix,
        'backend_type' => 'varchar',
        'frontend_input' => 'text',
        'frontend_label' => 'Pest Season',
        'is_user_defined' => 1,
        'attribute_set_id' => (int) $set->getId(),
        'attribute_group_id' => setRemoveTestFirstGroupId((int) $set->getId()),
    ]);
    $attribute->save();
    $this->records[] = $attribute;
    Mage::getSingleton('eav/config')->clear();
    $category = Mage::getModel('catalog/category')
        ->setName('Pest Remove ' . $this->suffix)
        ->setIsActive(1)
        ->setPath('1/' . Mage::app()->getStore(1)->getRootCategoryId())
        ->setAttributeSetId((int) $set->getId())
        ->setData('pest_season_' . $this->suffix, 'Winter');
    $category->save();
    $this->records[] = $category;

    $asked = Mage::helper('core')->jsonDecode(setRemoveTestSave(Mage_Catalog_Model_Category::ENTITY, $set, $attribute, false)->getBody());

    expect($asked['confirm'] ?? '')->toContain('Pest Season (records with a value: 1)')
        ->and(setRemoveTestRowId($set, $attribute))->toBeGreaterThan(0);

    $saved = Mage::helper('core')->jsonDecode(setRemoveTestSave(Mage_Catalog_Model_Category::ENTITY, $set, $attribute, true)->getBody());

    expect($saved)->not->toHaveKey('confirm')
        ->and(setRemoveTestRowId($set, $attribute))->toBe(0);
});

it('says that the values of the customers stay when an attribute leaves a customer set', function () {
    $type = Mage::getSingleton('eav/config')->getEntityType('customer');
    $set = Mage::getModel('eav/entity_attribute_set')->load((int) $type->getDefaultAttributeSetId());
    $attribute = Mage::getModel('customer/attribute')->setData([
        'entity_type_id' => (int) $type->getId(),
        'attribute_code' => 'pest_vat_' . $this->suffix,
        'backend_type' => 'varchar',
        'frontend_input' => 'text',
        'frontend_label' => 'Pest Registration Number',
        'is_user_defined' => 1,
        'is_visible' => 1,
        'is_system' => 0,
        'attribute_set_id' => (int) $set->getId(),
        'attribute_group_id' => setRemoveTestFirstGroupId((int) $set->getId()),
    ]);
    $attribute->save();
    $this->records[] = $attribute;
    Mage::getSingleton('eav/config')->clear();

    $asked = Mage::helper('core')->jsonDecode(setRemoveTestSave('customer', $set, $attribute, false)->getBody());

    expect($asked['confirm'] ?? '')->toContain('Pest Registration Number')
        ->and($asked['confirm'] ?? '')->toContain('The values of the customers stay')
        ->and(setRemoveTestRowId($set, $attribute))->toBeGreaterThan(0);
});

it('saves a set without a question when no attribute leaves it', function () {
    $type = Mage::getSingleton('eav/config')->getEntityType(Mage_Catalog_Model_Category::ENTITY);
    $set = Mage::getModel('eav/entity_attribute_set')->load((int) $type->getDefaultAttributeSetId());
    $groups = [];
    foreach (Mage::getResourceModel('eav/entity_attribute_group_collection')->setAttributeSetFilter((int) $set->getId())->setSortOrder() as $group) {
        $groups[] = [$group->getId(), $group->getAttributeGroupName(), (int) $group->getSortOrder()];
    }

    $response = setRemoveTestRun(Mage_Catalog_Model_Category::ENTITY, 'save', [
        'id' => $set->getId(),
        'data' => Mage::helper('core')->jsonEncode([
            'attribute_set_name' => $set->getAttributeSetName(),
            'groups' => $groups,
            'attributes' => [],
            'not_attributes' => [],
            'removeGroups' => [],
        ]),
    ]);

    expect(Mage::helper('core')->jsonDecode($response->getBody()))->toHaveKey('url');
});

it('opens the one set of CMS pages instead of a set grid, and refuses a new set', function () {
    $setId = (int) Mage::getSingleton('eav/config')->getEntityType(Mage_Cms_Model_Page::ENTITY)->getDefaultAttributeSetId();
    $sets = fn() => Mage::getResourceModel('eav/entity_attribute_set_collection')
        ->setEntityTypeFilter((int) Mage::getSingleton('eav/config')->getEntityType(Mage_Cms_Model_Page::ENTITY)->getId())
        ->getSize();
    $before = $sets();

    $index = setRemoveTestRun(Mage_Cms_Model_Page::ENTITY, 'index', []);
    setRemoveTestRun(Mage_Cms_Model_Page::ENTITY, 'save', [
        'gotoEdit' => '1',
        'attribute_set_name' => 'Pest Second ' . $this->suffix,
        'skeleton_set' => $setId,
    ]);
    $errors = array_map(
        fn(Mage_Core_Model_Message_Abstract $message): string => $message->getText(),
        Mage::getSingleton('adminhtml/session')->getMessages(true)->getErrors(),
    );

    $location = '';
    foreach ($index->getHeaders() as $header) {
        if (strtolower($header['name']) === 'location') {
            $location = (string) $header['value'];
        }
    }
    expect($location)->toContain('/eav_set/edit/')
        ->and($location)->toContain('/id/' . $setId . '/')
        ->and($sets())->toBe($before)
        ->and($errors)->toBe([Mage::helper('eav')->__('This entity type has one attribute set.')]);
});
