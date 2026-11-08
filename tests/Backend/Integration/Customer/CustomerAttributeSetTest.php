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
 * A user-defined text attribute in the first group of a set, or of the default set when $setId is null.
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
 * Post $params to the customer attribute save of the admin, as an admin who may edit customer attributes.
 */
function customerSetTestAdminSave(array $params): void
{
    $user = AdminRoleFixture::login(['admin/system/attributes/customer']);
    $request = new Mage_Core_Controller_Request_Http(
        SymfonyRequest::create('/index.php/admin/eav_attribute/save', 'POST', $params + ['entity_type' => 'customer']),
    );
    $request->setRouteName('adminhtml')->setControllerName('eav_attribute')->setActionName('save')->setDispatched(true);
    Mage::app()->setRequest($request);

    try {
        $controller = new Mage_Adminhtml_Eav_AttributeController($request, new Mage_Core_Controller_Response_Http());
        try {
            $controller->saveAction();
        } catch (Throwable) {
            // The trailing redirect builds an admin url that needs no assertion here
        }
    } finally {
        AdminRoleFixture::delete($user);
    }
    Mage::getSingleton('eav/config')->clear();
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

    expect(\Mage\Customer\Api\CustomAttributes::storefrontCodes('customer_address', (int) $group->getId()))->toContain($code);

    $b2bAddress = Mage::getModel('sales/quote_address')
        ->setQuote(Mage::getModel('sales/quote')->setCustomerGroupId((int) $group->getId()));
    $guestAddress = Mage::getModel('sales/quote_address')
        ->setQuote(Mage::getModel('sales/quote')->setCustomerGroupId(Mage_Customer_Model_Group::NOT_LOGGED_IN_ID));

    expect(customerSetTestFormCodes('customer_address_edit', $b2bAddress))->toContain($code)
        ->and(customerSetTestFormCodes('customer_address_edit', $guestAddress))->not->toContain($code)
        ->and(customerSetTestFormCodes('customer_address_edit', $guestAddress))->toContain('city');
});

it('puts a new attribute that names no set into the default set, which every group without a set uses', function () {
    $code = 'pest_default_' . $this->suffix;
    $attribute = $this->attributes[] = customerSetTestCreateAttribute('customer', $code, null, ['customer_account_edit']);
    $defaultSetId = (int) Mage::getSingleton('eav/config')->getEntityType('customer')->getDefaultAttributeSetId();

    $adapter = Mage::getSingleton('core/resource')->getConnection('core_read');
    $setIds = array_map(intval(...), $adapter->fetchCol($adapter->select()
        ->from(Mage::getSingleton('core/resource')->getTableName('eav/entity_attribute'), ['attribute_set_id'])
        ->where('attribute_id = ?', (int) $attribute->getId())));

    expect($setIds)->toBe([$defaultSetId])
        ->and(customerSetTestFormCodes('customer_account_edit', Mage::getModel('customer/customer')->setGroupId(1)))->toContain($code);
});

it('hides an attribute that is in no set from every group', function () {
    $code = 'pest_loose_' . $this->suffix;
    $this->attributes[] = customerSetTestCreateAttribute('customer', $code, null, ['customer_account_edit']);
    Mage::getSingleton('core/resource')->getConnection('core_write')->delete(
        Mage::getSingleton('core/resource')->getTableName('eav/entity_attribute'),
        ['attribute_id = ?' => (int) $this->attributes[0]->getId()],
    );

    $customer = Mage::getModel('customer/customer')->setGroupId(1);

    expect(customerSetTestFormCodes('customer_account_edit', $customer))->not->toContain($code)
        ->and(customerSetTestFormCodes('customer_account_edit', $customer))->toContain('email');
});

it('puts each form attribute that is in no set into the default set during the upgrade', function () {
    $code = 'pest_upgrade_' . $this->suffix;
    $attribute = $this->attributes[] = customerSetTestCreateAttribute('customer_address', $code, null, ['customer_address_edit']);
    $resource = Mage::getSingleton('core/resource');
    $table = $resource->getTableName('eav/entity_attribute');
    $resource->getConnection('core_write')->delete($table, ['attribute_id = ?' => (int) $attribute->getId()]);

    $setup = new Mage_Customer_Model_Resource_Setup('customer_setup');
    (function (): void {
        include Mage::getModuleDir('data', 'Mage_Customer') . '/customer_setup/data-upgrade-2.0.4-2.0.5.php';
    })->call($setup);

    $adapter = $resource->getConnection('core_read');
    $setIds = array_map(intval(...), $adapter->fetchCol($adapter->select()
        ->from($table, ['attribute_set_id'])
        ->where('attribute_id = ?', (int) $attribute->getId())));

    expect($setIds)->toBe([(int) Mage::getSingleton('eav/config')->getEntityType('customer_address')->getDefaultAttributeSetId()]);
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
    $code = 'pest_admin_' . $this->suffix;
    $websiteId = (int) Mage::app()->getWebsite(true)->getId();
    customerSetTestAdminSave([
        'attribute_code' => $code,
        'frontend_input' => 'text',
        'frontend_label' => ['Pest Admin'],
        'used_in_forms' => ['customer_account_create', 'not_a_form'],
        'max_text_length' => '20',
        'input_validation' => 'alphanumeric',
        'default_mode' => 'hidden',
        'website_values' => [$websiteId => 'required'],
    ]);

    /** @var Mage_Customer_Model_Attribute $attribute */
    $attribute = Mage::getModel('customer/attribute')->loadByCode('customer', $code);
    if ($attribute->getId()) {
        $this->attributes[] = $attribute;
    }

    expect($attribute->getId())->not->toBeNull()
        ->and($attribute->getUsedInForms())->toBe(['customer_account_create'])
        ->and($attribute->getValidateRules())->toBe(['max_text_length' => 20, 'input_validation' => 'alphanumeric'])
        ->and((int) $attribute->getIsVisible())->toBe(0)
        ->and((int) $attribute->getIsRequired())->toBe(0)
        ->and($attribute->getResource()->getWebsiteValues((int) $attribute->getId()))
        ->toBe([$websiteId => ['is_visible' => 1, 'is_required' => 1]]);
});

it('keeps a filter and a rule that the admin form does not offer', function () {
    $before = Mage::getModel('customer/attribute')->loadByCode('customer', 'dob');
    $filter = $before->getInputFilter();
    $rules = $before->getValidateRules();
    expect($filter)->toBe('date')
        ->and($rules)->toHaveKey('input_validation', 'date');
    $resource = Mage::getSingleton('core/resource');
    $adapter = $resource->getConnection('core_write');
    $table = $resource->getTableName('customer/eav_attribute');
    $row = $adapter->fetchRow($adapter->select()
        ->from($table, ['input_filter', 'validate_rules'])
        ->where('attribute_id = ?', (int) $before->getId()));

    try {
        customerSetTestAdminSave([
            'attribute_id' => $before->getId(),
            'frontend_label' => [$before->getFrontendLabel()],
            'used_in_forms' => $before->getUsedInForms(),
            'input_filter' => '',
            'input_validation' => '',
        ]);
        $after = Mage::getModel('customer/attribute')->loadByCode('customer', 'dob');
        expect($after->getInputFilter())->toBe($filter)
            ->and($after->getValidateRules())->toBe($rules);
    } finally {
        $adapter->update($table, $row, ['attribute_id = ?' => (int) $before->getId()]);
        Mage::getSingleton('eav/config')->clear();
    }
});

it('lists the system attributes of customers in the grid and not in the set editor', function () {
    foreach (['customer' => 'firstname', 'customer_address' => 'city'] as $entityTypeCode => $systemCode) {
        $type = Mage::getSingleton('eav/config')->getEntityType($entityTypeCode);

        $grid = Mage::helper('eav')->getManagedAttributeCollection($type)->getColumnValues('attribute_code');
        $sets = Mage::helper('eav')->getManagedAttributeCollection($type, forSets: true)->getColumnValues('attribute_code');

        expect($grid)->toContain($systemCode)
            ->and($sets)->not->toContain($systemCode);
    }
});

it('turns the Default field into is_visible and is_required, and keeps is_visible of a platform attribute', function () {
    $new = Mage::getModel('customer/attribute')
        ->setEntityTypeId((int) Mage::getSingleton('eav/config')->getEntityType('customer')->getId());
    $dob = Mage::getModel('customer/attribute')->loadByCode('customer', 'dob');

    $required = $new->prepareAdminData(['default_mode' => 'required']);
    $platform = $dob->prepareAdminData(['default_mode' => 'required']);

    expect($required)->toMatchArray(['is_visible' => 1, 'is_required' => 1])
        ->and($required)->not->toHaveKey('default_mode')
        ->and($new->prepareAdminData(['default_mode' => 'optional']))->toMatchArray(['is_visible' => 1, 'is_required' => 0])
        ->and($platform)->not->toHaveKey('is_visible')
        ->and($platform['is_required'])->toBe(1);
});

it('names the default mode in the website options of a customer attribute', function () {
    if (count(Mage::app()->getWebsites()) < 2) {
        $this->markTestSkipped('The store has one website, so the form shows no website options');
    }
    $attribute = $this->attributes[] = customerSetTestCreateAttribute('customer', 'pest_web_' . $this->suffix, null, ['customer_account_edit']);
    $attribute->setIsRequired(true)->save();
    $block = Mage::app()->getLayout()->createBlock('adminhtml/customer_attribute_edit_tab_main');
    $prepare = new ReflectionMethod($block, '_prepareWebsiteFields');

    $labels = function () use ($block, $prepare, $attribute): array {
        $form = new \Maho\Data\Form();
        $prepare->invoke($block, $form, Mage::getModel('customer/attribute')->load($attribute->getId()));
        $websiteId = (int) array_key_first(Mage::app()->getWebsites());
        return array_column($form->getElement("website_{$websiteId}")->getValues(), 'label', 'value');
    };

    expect($labels()[''])->toBe('Use Default (Required)');

    $attribute->setIsVisible(false)->save();
    expect($labels()[''])->toBe('Use Default (Hidden)');
});

it('puts the attributes of a later group of the set in a fieldset with the label of the store view', function () {
    $storeId = (int) Mage::app()->getDefaultStoreView()->getId();
    $set = $this->sets[] = customerSetTestCreateSet('customer', 'Pest Company ' . $this->suffix);
    $group = Mage::getModel('eav/entity_attribute_group')
        ->setAttributeSetId((int) $set->getId())
        ->setAttributeGroupName('Company')
        ->setSortOrder(900)
        ->setStoreLabels([$storeId => 'Azienda']);
    $group->save();
    $inFirst = $this->attributes[] = customerSetTestCreateAttribute('customer', 'pest_first_' . $this->suffix, (int) $set->getId(), ['customer_account_edit']);
    $inCompany = $this->attributes[] = customerSetTestCreateAttribute('customer', 'pest_company_' . $this->suffix, (int) $set->getId(), ['customer_account_edit']);
    $inCompany->setAttributeSetId((int) $set->getId())->setAttributeGroupId((int) $group->getId())->save();
    Mage::getSingleton('eav/config')->clear();

    $customerGroup = $this->groups[] = Mage::getModel('customer/group')
        ->setCode('Pest C ' . $this->suffix)
        ->setTaxClassId(customerSetTestTaxClassId())
        ->setCustomerAttributeSetId((int) $set->getId());
    $customerGroup->save();
    $customer = Mage::getModel('customer/customer')->setGroupId((int) $customerGroup->getId());

    $previousStoreId = (int) Mage::app()->getStore()->getId();
    Mage::app()->setCurrentStore($storeId);
    try {
        $groups = Mage::app()->getLayout()->createBlock('customer/form_attributes')
            ->setCustomerForm('customer_account_edit', $customer)
            ->getAttributeGroups();
    } finally {
        Mage::app()->setCurrentStore($previousStoreId);
    }
    $summary = array_map(fn(array $part) => [
        $part['label'],
        array_map(fn($attribute) => $attribute->getAttributeCode(), $part['attributes']),
    ], $groups);

    expect($summary)->toBe([
        [null, [$inFirst->getAttributeCode()]],
        ['Azienda', [$inCompany->getAttributeCode()]],
    ]);
});

it('names the customer groups of a set on its edit page, and keeps the values when the set goes', function () {
    $set = $this->sets[] = customerSetTestCreateSet('customer', 'Pest Note ' . $this->suffix);
    $group = $this->groups[] = Mage::getModel('customer/group')
        ->setCode('Pest Note Group ' . $this->suffix)
        ->setTaxClassId(customerSetTestTaxClassId())
        ->setCustomerAttributeSetId((int) $set->getId());
    $group->save();
    $helper = Mage::helper('eav');

    $defaultSet = Mage::getModel('eav/entity_attribute_set')
        ->load((int) Mage::getSingleton('eav/config')->getEntityType('customer')->getDefaultAttributeSetId());

    expect($helper->getSetNote($set))->toContain('Pest Note Group ' . $this->suffix)
        ->and($helper->getSetNote($defaultSet))->toContain('General')
        ->and($helper->getSetNote($defaultSet))->not->toContain('Pest Note Group ' . $this->suffix)
        ->and($helper->getSetNote($set))->toContain('The fields of the first group, General, join the other fields of the form with no title.')
        ->and($helper->getSetDeleteQuestion($set))->toContain('The values of the customers stay');
});
