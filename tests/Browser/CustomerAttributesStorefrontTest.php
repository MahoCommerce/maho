<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Tests\Browser\MahoServer;
use Tests\MahoBrowserTestCase;

uses(MahoBrowserTestCase::class)->group('browser');

/**
 * The custom customer and address fields on the storefront: the guest checkout keeps them on the
 * order, and the registration, account and address book pages save them.
 *
 * Fixtures: a Business Type dropdown and a required Company Registration Number of the customer,
 * and a Delivery Hours field of the address. The registration number is in a second group of the
 * default set, so the forms show it in a titled section.
 */

const CUSTOMER_ATTRIBUTES_EMAIL = 'custom-fields@example.test';
const CUSTOMER_ATTRIBUTES_PASSWORD = 'Password123!';
const CUSTOMER_ATTRIBUTES_GROUP = 'Company Information';

beforeEach(function () {
    $suffix = substr(uniqid(), -6);
    $this->businessCode = 'pest_business_' . $suffix;
    $this->numberCode = 'pest_regnum_' . $suffix;
    $this->hoursCode = 'pest_hours_' . $suffix;
    $resource = Mage::getSingleton('core/resource');
    $this->connection = $resource->getConnection('core_write');
    $this->attributes = [];

    deleteCustomerAttributesCustomer();
    $config = Mage::getModel('core/config');
    $config->saveConfig('customer/create_account/confirm', '0');

    $customerType = Mage::getSingleton('eav/config')->getEntityType('customer');
    // A run that stopped early can leave its group behind
    Mage::getResourceModel('eav/entity_attribute_group_collection')
        ->setAttributeSetFilter((int) $customerType->getDefaultAttributeSetId())
        ->addFieldToFilter('attribute_group_name', CUSTOMER_ATTRIBUTES_GROUP)
        ->walk('delete');
    $this->group = Mage::getModel('eav/entity_attribute_group')
        ->setAttributeSetId((int) $customerType->getDefaultAttributeSetId())
        ->setAttributeGroupName(CUSTOMER_ATTRIBUTES_GROUP)
        ->setSortOrder(100);
    $this->group->save();

    $this->attributes[] = customerAttributesStorefrontAttribute('customer', [
        'attribute_code' => $this->businessCode,
        'backend_type' => 'int',
        'frontend_input' => 'select',
        'source_model' => 'eav/entity_attribute_source_table',
        'frontend_label' => 'Business Type',
        'used_in_forms' => ['adminhtml_customer', 'customer_account_create', 'customer_account_edit', 'checkout_register'],
        'option' => ['value' => ['o1' => ['Retailer'], 'o2' => ['Wholesaler']], 'order' => ['o1' => 1, 'o2' => 2]],
    ]);
    $this->attributes[] = customerAttributesStorefrontAttribute('customer', [
        'attribute_code' => $this->numberCode,
        'backend_type' => 'varchar',
        'frontend_input' => 'text',
        'frontend_label' => 'Company Registration Number',
        'is_required' => 1,
        'used_in_forms' => ['adminhtml_customer', 'customer_account_create', 'customer_account_edit'],
        'attribute_set_id' => (int) $customerType->getDefaultAttributeSetId(),
        'attribute_group_id' => (int) $this->group->getId(),
    ]);
    $this->attributes[] = customerAttributesStorefrontAttribute('customer_address', [
        'attribute_code' => $this->hoursCode,
        'backend_type' => 'varchar',
        'frontend_input' => 'text',
        'frontend_label' => 'Delivery Hours',
        'used_in_forms' => ['adminhtml_customer_address', 'customer_address_edit', 'customer_register_address'],
    ]);

    $options = Mage::getModel('customer/attribute')->loadByCode('customer', $this->businessCode)->getSource()->getAllOptions();
    $this->wholesaler = (string) array_column($options, 'value', 'label')['Wholesaler'];

    Mage::app()->getStore()->resetConfig();
    Mage::app()->cleanCache();
});

afterEach(function () {
    deleteCustomerAttributesCustomer();
    foreach (Mage::getResourceModel('sales/order_collection')->addFieldToFilter('customer_email', CUSTOMER_ATTRIBUTES_EMAIL) as $order) {
        Mage::register('isSecureArea', true);
        try {
            $order->delete();
        } finally {
            Mage::unregister('isSecureArea');
        }
    }
    foreach ($this->attributes as $attribute) {
        $attribute->delete();
    }
    $this->group->delete();

    // A deleted attribute keeps its sales columns, so the fixture drops them
    $resource = Mage::getSingleton('core/resource');
    $columns = [
        'sales/quote' => ['customer_' . $this->businessCode, 'customer_' . $this->numberCode],
        'sales/order' => ['customer_' . $this->businessCode, 'customer_' . $this->numberCode],
        'sales/quote_address' => [$this->hoursCode],
        'sales/order_address' => [$this->hoursCode],
    ];
    foreach ($columns as $table => $names) {
        $table = $resource->getTableName($table);
        foreach ($names as $name) {
            if ($this->connection->tableColumnExists($table, $name)) {
                $this->connection->dropColumn($table, $name);
            }
        }
        $this->connection->resetDdlCache($table);
    }

    Mage::getModel('core/config')->deleteConfig('customer/create_account/confirm');
    Mage::getModel('core/config')->deleteConfig('checkout/options/onestep_checkout_enabled');
    Mage::getSingleton('eav/config')->clear();
    Mage::app()->getStore()->resetConfig();
    Mage::app()->cleanCache();
});

/**
 * A user-defined attribute of $entityTypeCode. With no set in $data, it goes into the first group of the default set.
 */
function customerAttributesStorefrontAttribute(string $entityTypeCode, array $data): Mage_Customer_Model_Attribute
{
    $type = Mage::getSingleton('eav/config')->getEntityType($entityTypeCode);
    $attribute = Mage::getModel('customer/attribute')->setData($data + [
        'entity_type_id' => (int) $type->getId(),
        'is_user_defined' => 1,
        'is_visible' => 1,
        'is_system' => 0,
    ]);
    $attribute->save();
    Mage::getSingleton('eav/config')->clear();
    return $attribute;
}

function deleteCustomerAttributesCustomer(): void
{
    $customer = Mage::getModel('customer/customer')
        ->setWebsiteId((int) Mage::app()->getDefaultStoreView()->getWebsiteId())
        ->loadByEmail(CUSTOMER_ATTRIBUTES_EMAIL);
    if (!$customer->getId()) {
        return;
    }
    Mage::register('isSecureArea', true);
    try {
        $customer->delete();
    } finally {
        Mage::unregister('isSecureArea');
    }
}

/** A salable, priced, visible simple product page URL, with stock guaranteed for the run. */
function customerAttributesProductUrl(): string
{
    $product = Mage::getResourceModel('catalog/product_collection')
        ->addAttributeToFilter('type_id', 'simple')
        ->addAttributeToFilter('status', 1)
        ->addAttributeToFilter('visibility', ['in' => [3, 4]])
        ->addAttributeToFilter('price', ['gt' => 0])
        ->setPageSize(1)
        ->getFirstItem();

    Mage::getModel('cataloginventory/stock_item')->loadByProduct((int) $product->getId())
        ->setData('use_config_manage_stock', 0)
        ->setData('manage_stock', 0)
        ->setData('is_in_stock', 1)
        ->setData('qty', 1000)
        ->save();

    return '/catalog/product/view/id/' . (int) $product->getId() . '/';
}

/**
 * Call $read until it returns a value that is not null, because the page saves in another process.
 */
function waitForCustomerAttributesRecord(callable $read, int $timeoutSeconds = 20): mixed
{
    $deadline = time() + $timeoutSeconds;
    do {
        $value = $read();
        if ($value !== null) {
            return $value;
        }
        usleep(250_000);
    } while (time() < $deadline);

    return null;
}

function customerAttributesCustomer(): ?Mage_Customer_Model_Customer
{
    $customer = Mage::getModel('customer/customer')
        ->setWebsiteId((int) Mage::app()->getDefaultStoreView()->getWebsiteId())
        ->loadByEmail(CUSTOMER_ATTRIBUTES_EMAIL);
    return $customer->getId() ? $customer : null;
}

it('keeps the custom customer and address fields of a guest checkout on the order', function (bool $oneStep) {
    if (!$oneStep) {
        Mage::getModel('core/config')->saveConfig('checkout/options/onestep_checkout_enabled', '0');
        Mage::app()->getStore()->resetConfig();
        Mage::app()->cleanCache();
    }
    $page = visit(MahoServer::baseUrl() . customerAttributesProductUrl())
        ->click('Add to Cart')
        ->waitForText('Cart Subtotal')
        ->navigate(MahoServer::baseUrl() . '/checkout/onepage');
    waitForPageLoad($page, $oneStep ? '#onestep-checkout' : '#co-billing-form');

    $page->assertPresent("#billing\\:{$this->businessCode}")
        ->assertPresent("#billing\\:{$this->hoursCode}")
        // The registration number is not in the Checkout Registration form
        ->assertMissing("#billing\\:{$this->numberCode}")
        ->fill('#billing\\:firstname', 'Custom')
        ->fill('#billing\\:lastname', 'Fields')
        ->fill('#billing\\:email', CUSTOMER_ATTRIBUTES_EMAIL)
        ->fill('#billing\\:street1', '1 Infinite Loop')
        ->fill('#billing\\:city', 'Cupertino')
        ->select('#billing\\:region_id', 'California')
        ->fill('#billing\\:postcode', '95014')
        ->fill('#billing\\:telephone', '5551234567')
        ->select("#billing\\:{$this->businessCode}", 'Wholesaler')
        ->fill("#billing\\:{$this->hoursCode}", '9:00-17:00');

    if ($oneStep) {
        $page->assertDontSee('Waiting for address')
            ->assertDontSee('Waiting for shipping method')
            ->assertSee('Check / Money order')
            ->click('Check / Money order')
            ->click('Place Order');
    } else {
        // Each step opens when the server accepts the step before it
        $page->click('#billing-buttons-container button')
            ->click('#shipping-method-buttons-container button')
            ->click('Check / Money order')
            ->click('#payment-buttons-container button')
            ->click('Place Order');
    }

    $order = waitForCustomerAttributesRecord(function (): ?Mage_Sales_Model_Order {
        $order = Mage::getResourceModel('sales/order_collection')
            ->addFieldToFilter('customer_email', CUSTOMER_ATTRIBUTES_EMAIL)
            ->getFirstItem();
        return $order->getId() ? Mage::getModel('sales/order')->load($order->getId()) : null;
    }, 30);

    expect($order)->not->toBeNull()
        ->and((string) $order->getData('customer_' . $this->businessCode))->toBe($this->wholesaler)
        ->and($order->getCustomerAttributeLabels())->toBe([$this->businessCode => 'Wholesaler'])
        ->and($order->getBillingAddress()->getData($this->hoursCode))->toBe('9:00-17:00')
        ->and($order->getShippingAddress()->getData($this->hoursCode))->toBe('9:00-17:00');
})->with(['one-step checkout' => [true], 'accordion checkout' => [false]]);

it('saves the custom fields of the registration, the account page and the address book', function () {
    $page = visit(MahoServer::baseUrl() . '/customer/account/create');
    waitForPageLoad($page, '#email_address_register');

    // The first group joins the fields of the form, and a later group is a titled section
    $page->assertPresent("#register_{$this->businessCode}")
        ->assertSeeIn('.attribute-group .legend', CUSTOMER_ATTRIBUTES_GROUP)
        ->assertPresent(".attribute-group #register_{$this->numberCode}")
        ->fill('[name="firstname"]', 'Custom')
        ->fill('[name="lastname"]', 'Fields')
        ->fill('#email_address_register', CUSTOMER_ATTRIBUTES_EMAIL)
        ->fill('#password_register', CUSTOMER_ATTRIBUTES_PASSWORD)
        ->fill('#confirmation_register', CUSTOMER_ATTRIBUTES_PASSWORD)
        ->select("#register_{$this->businessCode}", 'Wholesaler')
        ->click('Register')
        // The required registration number stops the form in the browser
        ->assertSee('This is a required field.');
    expect(customerAttributesCustomer())->toBeNull();

    $page->fill("#register_{$this->numberCode}", 'IT-12345')
        ->click('Register');
    $customer = waitForCustomerAttributesRecord(customerAttributesCustomer(...));

    expect($customer)->not->toBeNull()
        ->and((string) $customer->getData($this->businessCode))->toBe($this->wholesaler)
        ->and($customer->getData($this->numberCode))->toBe('IT-12345');

    // The account page shows the saved values, and saves a change
    $page->navigate(MahoServer::baseUrl() . '/customer/account/edit');
    waitForPageLoad($page, '#current_password')
        ->assertSelected("#{$this->businessCode}", $this->wholesaler)
        ->assertValue("#{$this->numberCode}", 'IT-12345')
        ->assertSeeIn('.attribute-group .legend', CUSTOMER_ATTRIBUTES_GROUP)
        ->fill("#{$this->numberCode}", 'IT-67890')
        ->fill('#current_password', CUSTOMER_ATTRIBUTES_PASSWORD)
        ->click('Save');
    $changed = waitForCustomerAttributesRecord(function (): ?string {
        $value = customerAttributesCustomer()?->getData($this->numberCode);
        return $value === 'IT-67890' ? $value : null;
    });
    expect($changed)->toBe('IT-67890');

    // The address book saves the custom field of the address
    $page->navigate(MahoServer::baseUrl() . '/customer/address/new');
    waitForPageLoad($page, '#street_1')
        ->fill('#telephone', '5551234567')
        ->fill('#street_1', '1 Infinite Loop')
        ->fill('#city', 'Cupertino')
        ->select('#country', 'US')
        ->select('#region_id', 'California')
        ->fill('#zip', '95014')
        ->fill("#{$this->hoursCode}", '8:00-12:00')
        ->click('Save Address');
    $hours = waitForCustomerAttributesRecord(function (): ?string {
        $address = customerAttributesCustomer()?->getAddressesCollection()->getFirstItem();
        return $address?->getId() ? (string) $address->getData($this->hoursCode) : null;
    });
    expect($hours)->toBe('8:00-12:00');
});
