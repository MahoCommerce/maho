<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

function customerCopyTestAttribute(array $data): Mage_Customer_Model_Attribute
{
    $type = Mage::getSingleton('eav/config')->getEntityType('customer');
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

function customerCopyTestObserver(): Mage_Sales_Model_Observer_CustomerAttributes
{
    // The observer keeps its list of attributes, so each test reads them again
    Mage::unregister('_singleton/sales/observer_customerAttributes');
    return Mage::getSingleton('sales/observer_customerAttributes');
}

beforeEach(function () {
    $suffix = substr(uniqid(), -6);
    $this->selectCode = 'pest_btype_' . $suffix;
    $this->textCode = 'pest_internal_' . $suffix;
    $this->attributes = [];
    $resource = Mage::getSingleton('core/resource');
    $this->connection = $resource->getConnection('core_write');
    $this->tables = [$resource->getTableName('sales/quote'), $resource->getTableName('sales/order')];

    $this->attributes[] = customerCopyTestAttribute([
        'attribute_code' => $this->selectCode,
        'backend_type' => 'int',
        'frontend_input' => 'select',
        'source_model' => 'eav/entity_attribute_source_table',
        'frontend_label' => 'Business Type',
        'used_in_forms' => ['adminhtml_customer', 'customer_account_edit'],
        'option' => ['value' => ['o1' => ['Retailer'], 'o2' => ['Wholesaler']], 'order' => ['o1' => 1, 'o2' => 2]],
    ]);
    $this->attributes[] = customerCopyTestAttribute([
        'attribute_code' => $this->textCode,
        'backend_type' => 'varchar',
        'frontend_input' => 'text',
        'frontend_label' => 'Internal Code',
        'used_in_forms' => ['adminhtml_customer'],
    ]);
    $options = Mage::getModel('customer/attribute')->loadByCode('customer', $this->selectCode)->getSource()->getAllOptions();
    $this->wholesaler = (string) array_column($options, 'value', 'label')['Wholesaler'];
    customerCopyTestObserver();
});

afterEach(function () {
    foreach ($this->attributes as $attribute) {
        if ($attribute->getId()) {
            $attribute->delete();
        }
    }
    foreach ($this->tables as $table) {
        foreach ([$this->selectCode, $this->textCode] as $code) {
            if ($this->connection->tableColumnExists($table, 'customer_' . $code)) {
                $this->connection->dropColumn($table, 'customer_' . $code);
            }
        }
        $this->connection->resetDdlCache($table);
    }
    Mage::getSingleton('eav/config')->clear();
    Mage::unregister('_singleton/sales/observer_customerAttributes');
});

it('refuses a customer attribute whose column is a column of the orders', function () {
    expect(fn() => customerCopyTestAttribute([
        'attribute_code' => 'note',
        'backend_type' => 'varchar',
        'frontend_input' => 'text',
        'frontend_label' => 'Note',
    ]))->toThrow(Mage_Core_Exception::class);
});

it('adds the quote and order columns of a new customer attribute', function () {
    foreach ($this->tables as $table) {
        $this->connection->resetDdlCache($table);
        expect($this->connection->tableColumnExists($table, 'customer_' . $this->selectCode))->toBeTrue()
            ->and($this->connection->tableColumnExists($table, 'customer_' . $this->textCode))->toBeTrue();
    }
});

it('copies the values to the quote, to a customer who registers at checkout, to the order with a label snapshot, and back on reorder', function () {
    $customer = Mage::getModel('customer/customer')
        ->setData($this->selectCode, $this->wholesaler)
        ->setData($this->textCode, 'X-42');
    $quote = Mage::getModel('sales/quote')->setStoreId((int) Mage::app()->getDefaultStoreView()->getId());
    $quote->setCustomer($customer);

    expect($quote->getData('customer_' . $this->selectCode))->toBe($this->wholesaler)
        ->and($quote->getData('customer_' . $this->textCode))->toBe('X-42');

    $newCustomer = Mage::getModel('customer/customer');
    Mage::helper('core')->copyFieldset('checkout_onepage_quote', 'to_customer', $quote, $newCustomer);
    expect($newCustomer->getData($this->selectCode))->toBe($this->wholesaler)
        ->and($newCustomer->getData($this->textCode))->toBe('X-42');

    $order = Mage::getModel('sales/order');
    Mage::helper('core')->copyFieldset('sales_convert_quote', 'to_order', $quote, $order);
    expect($order->getData('customer_' . $this->selectCode))->toBe($this->wholesaler)
        ->and($order->getCustomerAttributeLabels())->toBe([$this->selectCode => 'Wholesaler']);

    $reorder = Mage::getModel('sales/quote');
    Mage::helper('core')->copyFieldset('sales_copy_order', 'to_edit', $order, $reorder);
    expect($reorder->getData('customer_' . $this->selectCode))->toBe($this->wholesaler);
});

it('shows every attribute in the admin and only the storefront ones in the documents', function () {
    $order = Mage::getModel('sales/order')
        ->setStoreId((int) Mage::app()->getDefaultStoreView()->getId())
        ->setData('customer_' . $this->selectCode, $this->wholesaler)
        ->setData('customer_' . $this->textCode, 'X-42')
        ->setCustomerAttributeLabels([$this->selectCode => 'Wholesale (old label)']);
    $observer = customerCopyTestObserver();

    $admin = array_column($observer->getDisplayValues($order, false), 'value', 'label');
    $documents = array_column($observer->getDisplayValues($order, true), 'value', 'label');

    expect($admin)->toBe(['Business Type' => 'Wholesale (old label)', 'Internal Code' => 'X-42'])
        ->and($documents)->toBe(['Business Type' => 'Wholesale (old label)']);
});

it('reads the customer values of an order in the shape of the API', function () {
    $order = Mage::getModel('sales/order')
        ->setData('customer_' . $this->selectCode, $this->wholesaler)
        ->setData('customer_' . $this->textCode, 'X-42');

    expect(\Mage\Customer\Api\CustomAttributes::readOrderCustomer($order))
        ->toMatchArray([$this->selectCode => (int) $this->wholesaler, $this->textCode => 'X-42']);
});
