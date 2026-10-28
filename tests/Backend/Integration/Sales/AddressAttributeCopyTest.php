<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

function addressCopyTestAttribute(array $data): Mage_Customer_Model_Attribute
{
    $type = Mage::getSingleton('eav/config')->getEntityType('customer_address');
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

beforeEach(function () {
    $suffix = substr(uniqid(), -6);
    $this->textCode = 'pest_hours_' . $suffix;
    $this->selectCode = 'pest_dock_' . $suffix;
    $this->attributes = [];
    $resource = Mage::getSingleton('core/resource');
    $this->connection = $resource->getConnection('core_write');
    $this->tables = [$resource->getTableName('sales/quote_address'), $resource->getTableName('sales/order_address')];
});

afterEach(function () {
    foreach ($this->attributes as $attribute) {
        if ($attribute->getId()) {
            $attribute->delete();
        }
    }
    foreach ($this->tables as $table) {
        foreach ([$this->textCode, $this->selectCode] as $code) {
            if ($this->connection->tableColumnExists($table, $code)) {
                $this->connection->dropColumn($table, $code);
            }
        }
        $this->connection->resetDdlCache($table);
    }
    Mage::getSingleton('eav/config')->clear();
});

it('refuses an address attribute whose code is a column of the order addresses', function () {
    expect(fn() => addressCopyTestAttribute([
        'attribute_code' => 'subtotal',
        'backend_type' => 'varchar',
        'frontend_input' => 'text',
        'frontend_label' => 'Subtotal',
    ]))->toThrow(Mage_Core_Exception::class);
});

it('adds the sales columns of a new address attribute, keeps them after a delete, and refuses the code again', function () {
    $data = [
        'attribute_code' => $this->textCode,
        'backend_type' => 'varchar',
        'frontend_input' => 'text',
        'frontend_label' => 'Delivery Hours',
    ];
    $attribute = addressCopyTestAttribute($data);

    foreach ($this->tables as $table) {
        expect($this->connection->tableColumnExists($table, $this->textCode))->toBeTrue();
    }

    $attribute->delete();
    foreach ($this->tables as $table) {
        $this->connection->resetDdlCache($table);
        expect($this->connection->tableColumnExists($table, $this->textCode))->toBeTrue();
    }
    expect(fn() => addressCopyTestAttribute(['frontend_input' => 'select', 'backend_type' => 'int'] + $data))
        ->toThrow(Mage_Core_Exception::class);
});

it('copies address attribute values to the quote, to the order with labels, and back on reorder', function () {
    $this->attributes[] = addressCopyTestAttribute([
        'attribute_code' => $this->textCode,
        'backend_type' => 'varchar',
        'frontend_input' => 'text',
        'frontend_label' => 'Delivery Hours',
    ]);
    $this->attributes[] = addressCopyTestAttribute([
        'attribute_code' => $this->selectCode,
        'backend_type' => 'int',
        'frontend_input' => 'select',
        'source_model' => 'eav/entity_attribute_source_table',
        'frontend_label' => 'Dock',
        'option' => ['value' => ['o1' => ['North Dock'], 'o2' => ['South Dock']], 'order' => ['o1' => 1, 'o2' => 2]],
    ]);

    $select = Mage::getModel('customer/attribute')->loadByCode('customer_address', $this->selectCode);
    $southId = null;
    foreach ($select->getSource()->getAllOptions(false) as $option) {
        if ($option['label'] === 'South Dock') {
            $southId = (string) $option['value'];
        }
    }

    $customerAddress = Mage::getModel('customer/address')->setData([
        'firstname' => 'Pest',
        'lastname' => 'Copy',
        $this->textCode => '9-17',
        $this->selectCode => $southId,
    ]);
    $quoteAddress = Mage::getModel('sales/quote_address')->importCustomerAddress($customerAddress);
    $orderAddress = Mage::getModel('sales/convert_quote')->addressToOrderAddress($quoteAddress);
    $reorderAddress = Mage::getModel('sales/convert_order')->addressToQuoteAddress($orderAddress);
    $exported = $quoteAddress->exportCustomerAddress();

    expect($quoteAddress->getData($this->textCode))->toBe('9-17')
        ->and((string) $quoteAddress->getData($this->selectCode))->toBe($southId)
        ->and($orderAddress->getData($this->textCode))->toBe('9-17')
        ->and($orderAddress->getData($this->selectCode))->toBe('South Dock')
        ->and((string) $reorderAddress->getData($this->selectCode))->toBe($southId)
        ->and((string) $exported->getData($this->selectCode))->toBe($southId)
        ->and($exported->getData($this->textCode))->toBe('9-17');
});

it('shows the label of a dropdown in the address template of an order', function () {
    $this->attributes[] = addressCopyTestAttribute([
        'attribute_code' => $this->selectCode,
        'backend_type' => 'int',
        'frontend_input' => 'select',
        'source_model' => 'eav/entity_attribute_source_table',
        'frontend_label' => 'Dock',
        'option' => ['value' => ['o1' => ['North Dock']], 'order' => ['o1' => 1]],
    ]);

    $orderAddress = Mage::getModel('sales/order_address')->setData($this->selectCode, 'North Dock');
    $renderer = Mage::getModel('customer/address_config')->getFormatByCode('text')->getRenderer();

    expect($renderer->render($orderAddress, '{{var ' . $this->selectCode . '}}'))->toBe('North Dock');
});
