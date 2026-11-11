<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * Mage_Sales_Service_Order::validateQuote() checks the customer fields of a Customer order of a cart with no customer:
 * a valid email, and each field of the Checkout Registration form (#1348). So the storefront and the API have one
 * rule. Staff has no such check.
 */

/**
 * @return list<string> the fields of the errors of validateQuote() for $quote
 */
function guestOrderCustomerDataFields(Mage_Sales_Model_Quote $quote, Mage_Checkout_Model_Actor $actor): array
{
    try {
        Mage::getService('sales/order')->validateQuote($quote, $actor);
    } catch (Mage_Core_Exception_Input $e) {
        return array_column($e->getErrors(), 'field');
    }
    return [];
}

beforeEach(function () {
    $this->quote = createPlaceableQuote(loadSimplePricedProduct(), 1);
    $this->quote->setCustomerIsGuest(true)->setCustomerFirstname('Pest')->setCustomerLastname('Guest');
    $this->attribute = null;
});

afterEach(function () {
    $this->quote->delete();
    if ($this->attribute) {
        $code = $this->attribute->getAttributeCode();
        $this->attribute->delete();
        $resource = Mage::getSingleton('core/resource');
        $connection = $resource->getConnection('core_write');
        foreach (['sales/quote', 'sales/order'] as $table) {
            $table = $resource->getTableName($table);
            if ($connection->tableColumnExists($table, 'customer_' . $code)) {
                $connection->dropColumn($table, 'customer_' . $code);
            }
            $connection->resetDdlCache($table);
        }
        Mage::getSingleton('eav/config')->clear();
    }
});

it('refuses the guest order of a customer actor without an email', function () {
    $this->quote->setCustomerEmail(null);

    expect(guestOrderCustomerDataFields($this->quote, Mage_Checkout_Model_Actor::Customer))->toBe(['customer_email']);
});

it('refuses the guest order of a customer actor with an email that is not valid', function () {
    $this->quote->setCustomerEmail('not-an-email');

    expect(guestOrderCustomerDataFields($this->quote, Mage_Checkout_Model_Actor::Customer))->toBe(['customer_email']);
});

it('accepts the guest order of a customer actor with a valid email, and the guest order of staff without an email', function () {
    $this->quote->setCustomerEmail('pest-guest@example.test');
    $withEmail = guestOrderCustomerDataFields($this->quote, Mage_Checkout_Model_Actor::Customer);
    $this->quote->setCustomerEmail(null);

    expect($withEmail)->toBe([])
        ->and(guestOrderCustomerDataFields($this->quote, Mage_Checkout_Model_Actor::Staff))->toBe([]);
});

it('refuses a guest order that misses a required field of the Checkout Registration form', function () {
    $code = 'pest_guest_' . substr(uniqid(), -6);
    $this->attribute = Mage::getModel('customer/attribute')->setData([
        'entity_type_id' => (int) Mage::getSingleton('eav/config')->getEntityType('customer')->getId(),
        'attribute_code' => $code,
        'backend_type' => 'varchar',
        'frontend_input' => 'text',
        'frontend_label' => 'Pest Guest Field',
        'is_user_defined' => 1,
        'is_visible' => 1,
        'is_required' => 1,
        'is_system' => 0,
        'used_in_forms' => ['checkout_register'],
    ]);
    $this->attribute->save();
    Mage::getSingleton('eav/config')->clear();
    $this->quote->setCustomerEmail('pest-guest@example.test');

    $missing = guestOrderCustomerDataFields($this->quote, Mage_Checkout_Model_Actor::Customer);
    $this->quote->setData('customer_' . $code, 'X-1');

    expect($missing)->toBe(['customer_' . $code])
        ->and(guestOrderCustomerDataFields($this->quote, Mage_Checkout_Model_Actor::Customer))->toBe([])
        ->and(guestOrderCustomerDataFields($this->quote->setData('customer_' . $code, null), Mage_Checkout_Model_Actor::Staff))->toBe([]);
});
