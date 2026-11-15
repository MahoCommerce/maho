<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * Mage_Checkout_Service_Cart::validateAddress() checks an address with the address form of the actor (#1348): staff
 * has the form of the admin, a customer has the form of the storefront.
 */

beforeEach(function () {
    $this->code = 'pest_dock_' . substr(uniqid(), -6);
    $type = Mage::getSingleton('eav/config')->getEntityType('customer_address');
    $this->attribute = Mage::getModel('customer/attribute')->setData([
        'entity_type_id' => (int) $type->getId(),
        'attribute_code' => $this->code,
        'backend_type' => 'varchar',
        'frontend_input' => 'text',
        'frontend_label' => 'Dock',
        'is_required' => 1,
        'is_user_defined' => 1,
        'is_visible' => 1,
        'is_system' => 0,
        'used_in_forms' => ['adminhtml_customer_address'],
    ]);
    $this->attribute->save();
    Mage::getSingleton('eav/config')->clear();

    $this->address = Mage::getModel('sales/quote_address')->setData([
        'firstname' => 'Form', 'lastname' => 'Actor', 'street' => '1 Main St', 'city' => 'Los Angeles',
        'region_id' => 12, 'postcode' => '90210', 'country_id' => 'US', 'telephone' => '5550100',
    ]);
});

afterEach(function () {
    $this->attribute->delete();
    $resource = Mage::getSingleton('core/resource');
    $connection = $resource->getConnection('core_write');
    foreach ([$resource->getTableName('sales/quote_address'), $resource->getTableName('sales/order_address')] as $table) {
        if ($connection->tableColumnExists($table, $this->code)) {
            $connection->dropColumn($table, $this->code);
        }
        $connection->resetDdlCache($table);
    }
    Mage::getSingleton('eav/config')->clear();
});

it('asks staff for a required field that only the admin address form has', function () {
    /** @var Mage_Checkout_Service_Cart $service */
    $service = Mage::getService('checkout/cart');
    $fields = [];
    try {
        $service->validateAddress($this->address, Mage_Checkout_Model_Actor::Staff);
    } catch (Mage_Core_Exception_Input $e) {
        $fields = array_column($e->getErrors(), 'field');
    }

    expect($fields)->toBe([$this->code]);

    $this->address->setData($this->code, 'North');
    $service->validateAddress($this->address, Mage_Checkout_Model_Actor::Staff);
});

it('does not ask a customer for a field that the storefront address form does not have', function () {
    Mage::getService('checkout/cart')->validateAddress($this->address);
    Mage::getService('checkout/cart')->validateAddress($this->address, Mage_Checkout_Model_Actor::Customer);

    expect(true)->toBeTrue();
});

it('gives staff the payment methods for internal use, and a customer the methods of the checkout', function () {
    /** @var Mage_Checkout_Service_Cart $service */
    $service = Mage::getService('checkout/cart');

    expect($service->getPaymentChecks(Mage_Checkout_Model_Actor::Staff))->toBe(Mage_Payment_Model_Method_Abstract::CHECKS_INTERNAL)
        ->and($service->getPaymentChecks(Mage_Checkout_Model_Actor::Customer))->toBe(Mage_Payment_Model_Method_Abstract::CHECKS_CHECKOUT);
});
