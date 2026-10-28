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

function customerSetTestCreateSet(string $entityTypeCode, string $name): Mage_Eav_Model_Entity_Attribute_Set
{
    $type = Mage::getSingleton('eav/config')->getEntityType($entityTypeCode);
    $set = Mage::getModel('eav/entity_attribute_set')
        ->setEntityTypeId((int) $type->getId())
        ->setAttributeSetName($name);
    $set->save();
    $set->initFromSkeleton((int) $type->getDefaultAttributeSetId())->save();
    return $set;
}

/**
 * A user-defined text attribute in the first group of a set, or in no set when $setId is null.
 */
function customerSetTestCreateAttribute(string $entityTypeCode, string $code, ?int $setId, array $forms): Mage_Customer_Model_Attribute
{
    $type = Mage::getSingleton('eav/config')->getEntityType($entityTypeCode);
    $data = [
        'entity_type_id' => (int) $type->getId(),
        'attribute_code' => $code,
        'backend_type' => 'varchar',
        'frontend_input' => 'text',
        'frontend_label' => $code,
        'is_user_defined' => 1,
        'is_visible' => 1,
        'is_system' => 0,
        'used_in_forms' => $forms,
    ];
    if ($setId) {
        $data['attribute_set_id'] = $setId;
        $data['attribute_group_id'] = (int) Mage::getResourceModel('eav/entity_attribute_group_collection')
            ->setAttributeSetFilter($setId)
            ->setSortOrder()
            ->getFirstItem()
            ->getId();
    }
    $attribute = Mage::getModel('customer/attribute')->setData($data);
    $attribute->save();
    Mage::getSingleton('eav/config')->clear();
    return $attribute;
}

/**
 * @return list<string>
 */
function customerSetTestFormCodes(string $formCode, Mage_Core_Model_Abstract $entity): array
{
    $form = Mage::getModel('customer/form')
        ->setFormCode($formCode)
        ->setEntityType($entity instanceof Mage_Customer_Model_Address_Abstract ? 'customer_address' : 'customer')
        ->setEntity($entity);
    return array_keys($form->getAttributes());
}

function customerSetTestTaxClassId(): int
{
    return (int) Mage::getModel('customer/group')->load(1)->getData('tax_class_id');
}

beforeEach(function () {
    $this->suffix = substr(uniqid(), -6);
    $this->attributes = [];
    $this->sets = [];
    $this->groups = [];
});

afterEach(function () {
    foreach ($this->attributes as $attribute) {
        $attribute->delete();
    }
    foreach ($this->groups as $group) {
        $group->delete();
    }
    foreach ($this->sets as $set) {
        $set->delete();
    }
    Mage::getSingleton('eav/config')->clear();
});

it('shows a customer attribute only to the groups whose set holds it', function () {
    $set = $this->sets[] = customerSetTestCreateSet('customer', 'Pest Wholesale ' . $this->suffix);
    $code = 'pest_reg_' . $this->suffix;
    $this->attributes[] = customerSetTestCreateAttribute('customer', $code, (int) $set->getId(), ['customer_account_create']);

    $wholesale = $this->groups[] = Mage::getModel('customer/group')
        ->setCode('Pest W ' . $this->suffix)
        ->setTaxClassId(customerSetTestTaxClassId())
        ->setCustomerAttributeSetId((int) $set->getId());
    $wholesale->save();
    $retail = $this->groups[] = Mage::getModel('customer/group')
        ->setCode('Pest R ' . $this->suffix)
        ->setTaxClassId(customerSetTestTaxClassId());
    $retail->save();

    $wholesaleCustomer = Mage::getModel('customer/customer')->setGroupId((int) $wholesale->getId());
    $retailCustomer = Mage::getModel('customer/customer')->setGroupId((int) $retail->getId());

    expect(customerSetTestFormCodes('customer_account_create', $wholesaleCustomer))->toContain($code)
        ->and(customerSetTestFormCodes('customer_account_create', $wholesaleCustomer))->toContain('email')
        ->and(customerSetTestFormCodes('customer_account_create', $retailCustomer))->not->toContain($code)
        ->and(customerSetTestFormCodes('customer_account_create', $retailCustomer))->toContain('email');
});

it('uses the address set of the group of the quote for a quote address', function () {
    $set = $this->sets[] = customerSetTestCreateSet('customer_address', 'Pest B2B Address ' . $this->suffix);
    $code = 'pest_hours_' . $this->suffix;
    $this->attributes[] = customerSetTestCreateAttribute('customer_address', $code, (int) $set->getId(), ['customer_address_edit']);

    $group = $this->groups[] = Mage::getModel('customer/group')
        ->setCode('Pest B2B ' . $this->suffix)
        ->setTaxClassId(customerSetTestTaxClassId())
        ->setCustomerAddressAttributeSetId((int) $set->getId());
    $group->save();

    $b2bAddress = Mage::getModel('sales/quote_address')
        ->setQuote(Mage::getModel('sales/quote')->setCustomerGroupId((int) $group->getId()));
    $guestAddress = Mage::getModel('sales/quote_address')
        ->setQuote(Mage::getModel('sales/quote')->setCustomerGroupId(Mage_Customer_Model_Group::NOT_LOGGED_IN_ID));

    expect(customerSetTestFormCodes('customer_address_edit', $b2bAddress))->toContain($code)
        ->and(customerSetTestFormCodes('customer_address_edit', $guestAddress))->not->toContain($code)
        ->and(customerSetTestFormCodes('customer_address_edit', $guestAddress))->toContain('city');
});

it('shows an attribute that is in no set to every group', function () {
    $code = 'pest_loose_' . $this->suffix;
    $this->attributes[] = customerSetTestCreateAttribute('customer', $code, null, ['customer_account_edit']);
    Mage::getSingleton('core/resource')->getConnection('core_write')->delete(
        Mage::getSingleton('core/resource')->getTableName('eav/entity_attribute'),
        ['attribute_id = ?' => (int) $this->attributes[0]->getId()],
    );

    $customer = Mage::getModel('customer/customer')->setGroupId(1);

    expect(customerSetTestFormCodes('customer_account_edit', $customer))->toContain($code);
});

it('refuses a group set of another entity type', function () {
    $productSetId = (int) Mage::getSingleton('eav/config')->getEntityType('catalog_product')->getDefaultAttributeSetId();
    $group = Mage::getModel('customer/group')
        ->setCode('Pest Bad ' . $this->suffix)
        ->setTaxClassId(customerSetTestTaxClassId())
        ->setCustomerAttributeSetId($productSetId);

    expect(fn() => $group->save())->toThrow(Mage_Core_Exception::class);
});

it('saves the forms, the rules and the website modes of a new customer attribute from the admin', function () {
    $user = AdminRoleFixture::login(['admin/customer/attributes/customer_attributes']);
    $code = 'pest_admin_' . $this->suffix;
    $websiteId = (int) Mage::app()->getWebsite(true)->getId();
    $params = [
        'attribute_code' => $code,
        'frontend_input' => 'text',
        'frontend_label' => ['Pest Admin'],
        'used_in_forms' => ['customer_account_create', 'not_a_form'],
        'max_text_length' => '20',
        'input_validation' => 'alphanumeric',
        'website_values' => [$websiteId => 'required'],
    ];
    $request = new Mage_Core_Controller_Request_Http(
        SymfonyRequest::create('/index.php/admin/customer_attribute/save', 'POST', $params),
    );
    $request->setRouteName('adminhtml')->setControllerName('customer_attribute')->setActionName('save')->setDispatched(true);
    Mage::app()->setRequest($request);

    try {
        $controller = new Mage_Adminhtml_Customer_AttributeController($request, new Mage_Core_Controller_Response_Http());
        try {
            $controller->saveAction();
        } catch (Throwable) {
            // The trailing redirect builds an admin url that needs no assertion here
        }
    } finally {
        AdminRoleFixture::delete($user);
    }

    /** @var Mage_Customer_Model_Attribute $attribute */
    $attribute = Mage::getModel('customer/attribute')->loadByCode('customer', $code);
    if ($attribute->getId()) {
        $this->attributes[] = $attribute;
    }

    expect($attribute->getId())->not->toBeNull()
        ->and($attribute->getUsedInForms())->toBe(['customer_account_create'])
        ->and($attribute->getValidateRules())->toBe(['max_text_length' => 20, 'input_validation' => 'alphanumeric'])
        ->and($attribute->getResource()->getWebsiteValues((int) $attribute->getId()))
        ->toBe([$websiteId => ['is_visible' => 1, 'is_required' => 1]]);
});
