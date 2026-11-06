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

it('copies address attribute values to the quote, to the order with a label snapshot, and back on reorder', function () {
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
        'option' => ['value' => ['o1' => ['North Dock'], 'o2' => ['South Dock, Gate 2']], 'order' => ['o1' => 1, 'o2' => 2]],
    ]);

    $select = Mage::getModel('customer/attribute')->loadByCode('customer_address', $this->selectCode);
    $southId = null;
    foreach ($select->getSource()->getAllOptions(false) as $option) {
        if ($option['label'] === 'South Dock, Gate 2') {
            $southId = (string) $option['value'];
        }
    }

    $customerAddress = Mage::getModel('customer/address')->setData([
        'firstname' => 'Pest',
        'lastname' => 'Copy',
        $this->textCode => '9-17',
        $this->selectCode => $southId,
    ]);
    $quote = Mage::getModel('sales/quote')->setStoreId((int) Mage::app()->getDefaultStoreView()->getId());
    $quoteAddress = Mage::getModel('sales/quote_address')->setQuote($quote)->importCustomerAddress($customerAddress);
    $orderAddress = Mage::getModel('sales/convert_quote')->addressToOrderAddress($quoteAddress);

    // A rename of the option after the order changes neither the snapshot nor the reorder
    $connection = Mage::getSingleton('core/resource')->getConnection('core_write');
    $connection->update(
        Mage::getSingleton('core/resource')->getTableName('eav/attribute_option_value'),
        ['value' => 'South Dock, Gate 3'],
        ['option_id = ?' => (int) $southId],
    );

    $reorderAddress = Mage::getModel('sales/convert_order')->addressToQuoteAddress($orderAddress);
    $exported = $quoteAddress->exportCustomerAddress();

    expect($quoteAddress->getData($this->textCode))->toBe('9-17')
        ->and((string) $quoteAddress->getData($this->selectCode))->toBe($southId)
        ->and($orderAddress->getData($this->textCode))->toBe('9-17')
        ->and((string) $orderAddress->getData($this->selectCode))->toBe($southId)
        ->and($orderAddress->getCustomAttributeLabels())->toBe([$this->selectCode => 'South Dock, Gate 2'])
        ->and((string) $reorderAddress->getData($this->selectCode))->toBe($southId)
        ->and((string) $exported->getData($this->selectCode))->toBe($southId)
        ->and($exported->getData($this->textCode))->toBe('9-17');
});

it('shows the label snapshot of a dropdown in the address template of an order', function () {
    $this->attributes[] = addressCopyTestAttribute([
        'attribute_code' => $this->selectCode,
        'backend_type' => 'int',
        'frontend_input' => 'select',
        'source_model' => 'eav/entity_attribute_source_table',
        'frontend_label' => 'Dock',
        'option' => ['value' => ['o1' => ['North Dock']], 'order' => ['o1' => 1]],
    ]);

    $orderAddress = Mage::getModel('sales/order_address')
        ->setData($this->selectCode, '999999')
        ->setCustomAttributeLabels([$this->selectCode => 'North Dock']);
    $renderer = Mage::getModel('customer/address_config')->getFormatByCode('text')->getRenderer();

    expect($renderer->render($orderAddress, '{{var ' . $this->selectCode . '}}'))->toBe('North Dock');
});

it('refreshes the label snapshot of an order address only for a dropdown that the admin changed', function () {
    $this->attributes[] = addressCopyTestAttribute([
        'attribute_code' => $this->selectCode,
        'backend_type' => 'int',
        'frontend_input' => 'select',
        'source_model' => 'eav/entity_attribute_source_table',
        'frontend_label' => 'Dock',
        'option' => ['value' => ['o1' => ['North Dock'], 'o2' => ['South Dock']], 'order' => ['o1' => 1, 'o2' => 2]],
    ]);
    $ids = [];
    $attribute = Mage::getModel('customer/attribute')->loadByCode('customer_address', $this->selectCode);
    foreach ($attribute->getSource()->getAllOptions(false) as $option) {
        $ids[$option['label']] = (string) $option['value'];
    }
    $order = Mage::getModel('sales/order')->setStoreId((int) Mage::app()->getDefaultStoreView()->getId());
    $address = fn(array $data) => Mage::getModel('sales/order_address')->setOrder($order)->setData($data)->setOrigData();
    $observer = Mage::getModel('sales/observer_addressAttributes');
    $stored = [$this->selectCode => 'Old North Dock'];

    $unchanged = $address([$this->selectCode => $ids['North Dock']])->setCustomAttributeLabels($stored);
    $observer->refreshOptionLabels($unchanged);

    $changed = $address([$this->selectCode => $ids['North Dock']])->setCustomAttributeLabels($stored);
    $changed->setData($this->selectCode, $ids['South Dock']);
    $observer->refreshOptionLabels($changed);

    $cleared = $address([$this->selectCode => $ids['North Dock']])->setCustomAttributeLabels($stored);
    $cleared->setData($this->selectCode, '');
    $observer->refreshOptionLabels($cleared);

    expect($unchanged->getCustomAttributeLabels())->toBe($stored)
        ->and($changed->getCustomAttributeLabels())->toBe([$this->selectCode => 'South Dock'])
        ->and($cleared->getCustomAttributeLabels())->toBe([]);
});

it('joins the posted ids of a multiselect when it reads the labels of an order address', function () {
    $this->attributes[] = addressCopyTestAttribute([
        'attribute_code' => $this->selectCode,
        'backend_type' => 'varchar',
        'frontend_input' => 'multiselect',
        'source_model' => 'eav/entity_attribute_source_table',
        'frontend_label' => 'Docks',
        'option' => ['value' => ['o1' => ['North Dock'], 'o2' => ['South Dock']], 'order' => ['o1' => 1, 'o2' => 2]],
    ]);
    $attribute = Mage::getModel('customer/attribute')->loadByCode('customer_address', $this->selectCode);
    $ids = array_map(fn(array $option) => (string) $option['value'], $attribute->getSource()->getAllOptions(false));
    $order = Mage::getModel('sales/order')->setStoreId((int) Mage::app()->getDefaultStoreView()->getId());
    $address = Mage::getModel('sales/order_address')->setOrder($order)->setData([$this->selectCode => $ids])->setOrigData();

    $address->setData($this->selectCode, array_reverse($ids));
    Mage::getModel('sales/observer_addressAttributes')->refreshOptionLabels($address);

    expect($address->getCustomAttributeLabels())->toBe([$this->selectCode => ['South Dock', 'North Dock']]);
});
