<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

beforeEach(function () {
    $this->code = 'pest_checkout_' . substr(uniqid(), -6);
    $type = Mage::getSingleton('eav/config')->getEntityType('customer');
    $this->attribute = Mage::getModel('customer/attribute')->setData([
        'entity_type_id' => (int) $type->getId(),
        'attribute_code' => $this->code,
        'backend_type' => 'varchar',
        'frontend_input' => 'text',
        'frontend_label' => 'Pest Checkout Field',
        'is_user_defined' => 1,
        'is_visible' => 1,
        'is_required' => 1,
        'is_system' => 0,
        'used_in_forms' => ['checkout_register'],
    ]);
    $this->attribute->save();
    Mage::getSingleton('eav/config')->clear();
});

afterEach(function () {
    $this->attribute->delete();
    $resource = Mage::getSingleton('core/resource');
    $connection = $resource->getConnection('core_write');
    foreach (['sales/quote', 'sales/order'] as $table) {
        $table = $resource->getTableName($table);
        if ($connection->tableColumnExists($table, 'customer_' . $this->code)) {
            $connection->dropColumn($table, 'customer_' . $this->code);
        }
        $connection->resetDdlCache($table);
    }
    Mage::getSingleton('eav/config')->clear();
});

/**
 * Run the customer check of the billing step on $quote with the posted $data.
 */
function onepageCustomerDataCheck(Mage_Sales_Model_Quote $quote, array $data): true|array
{
    $onepage = Mage::getModel('checkout/type_onepage');
    $onepage->setQuote($quote);
    return (fn(array $posted) => $this->_validateCustomerData($posted))->call($onepage, $data);
}

it('checks the required custom field of a guest and copies its value to the quote', function () {
    $quote = Mage::getModel('sales/quote')->setStoreId((int) Mage::app()->getDefaultStoreView()->getId());
    $guest = ['firstname' => 'Pest', 'lastname' => 'Guest', 'email' => 'pest-guest@example.test'];

    expect(onepageCustomerDataCheck($quote, $guest))->toBeArray()
        ->and(onepageCustomerDataCheck($quote, $guest + [$this->code => 'X-1']))->toBeTrue()
        ->and($quote->getData('customer_' . $this->code))->toBe('X-1');
});

it('does not check the custom fields of a logged-in customer, whom the billing form shows none', function () {
    $customer = Mage::getModel('customer/customer')->setId(999999)->setFirstname('Pest')->setLastname('Customer');
    $quote = Mage::getModel('sales/quote')->setStoreId((int) Mage::app()->getDefaultStoreView()->getId());
    $quote->setData('customer_id', 999999);
    (fn() => $this->_customer = $customer)->call($quote);

    expect(onepageCustomerDataCheck($quote, []))->toBeTrue();
});
