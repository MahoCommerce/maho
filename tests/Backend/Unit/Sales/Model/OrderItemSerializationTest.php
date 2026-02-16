<?php

/**
 * Maho
 *
 * @package    Mage_Sales
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * Tests that Mage_Sales_Model_Order_Item correctly reads legacy PHP-serialized
 * product_options from the sales_flat_order_item table and converts to JSON.
 *
 * product_options is the highest-traffic serialized column — every order item has one.
 */
describe('Order item reads legacy serialized product_options', function () {
    it('reads simple product options', function () {
        $original = [
            'info_buyRequest' => [
                'uenc' => 'aHR0cDovL2xvY2FsaG9zdC8=',
                'product' => '42',
                'qty' => 1,
            ],
            'attributes_info' => [
                ['label' => 'Color', 'value' => 'Red'],
            ],
            'simple_name' => 'Test Product - Red',
        ];

        $item = Mage::getModel('sales/order_item');
        $item->setData('product_options', serialize($original));

        $options = $item->getProductOptions();
        expect($options['info_buyRequest']['product'])->toBe('42')
            ->and($options['info_buyRequest']['qty'])->toBe(1)
            ->and($options['attributes_info'][0]['label'])->toBe('Color')
            ->and($options['simple_name'])->toBe('Test Product - Red');
    });

    it('reads configurable product options with super_attribute integer keys', function () {
        $original = [
            'info_buyRequest' => [
                'qty' => 2,
                'super_attribute' => [143 => '25', 92 => '17'],
            ],
            'attributes_info' => [
                ['label' => 'Color', 'value' => 'Blue'],
                ['label' => 'Size', 'value' => 'M'],
            ],
        ];

        $item = Mage::getModel('sales/order_item');
        $item->setData('product_options', serialize($original));

        $options = $item->getProductOptions();
        $superAttr = $options['info_buyRequest']['super_attribute'];
        expect($superAttr)->toHaveKey(143)
            ->and($superAttr[143])->toBe('25')
            ->and($superAttr)->toHaveKey(92)
            ->and($superAttr[92])->toBe('17');
    });

    it('reads product options with additional_options for custom fields', function () {
        $original = [
            'info_buyRequest' => ['qty' => 1],
            'additional_options' => [
                ['label' => 'Gift Message', 'value' => 'Happy B-Day'],
            ],
        ];

        $item = Mage::getModel('sales/order_item');
        $item->setData('product_options', serialize($original));

        $options = $item->getProductOptions();
        expect($options['additional_options'][0]['label'])->toBe('Gift Message')
            ->and($options['additional_options'][0]['value'])->toBe('Happy B-Day');
    });
});

describe('Order item full migration round-trip', function () {
    it('legacy serialized → getProductOptions → setProductOptions → JSON → getProductOptions', function () {
        $original = [
            'info_buyRequest' => [
                'product' => '42',
                'qty' => 1,
            ],
            'attributes_info' => [
                ['label' => 'Color', 'value' => 'Red'],
            ],
            'simple_name' => 'Test Product - Red',
        ];

        // Step 1: Read legacy
        $item = Mage::getModel('sales/order_item');
        $item->setData('product_options', serialize($original));
        $options = $item->getProductOptions();
        expect($options)->toBe($original);

        // Step 2: Write as JSON
        $item->setProductOptions($options);
        $json = $item->getData('product_options');
        expect(json_validate($json))->toBeTrue()
            ->and($json)->not->toStartWith('a:');

        // Step 3: Read the JSON version
        $item2 = Mage::getModel('sales/order_item');
        $item2->setData('product_options', $json);
        expect($item2->getProductOptions())->toBe($original);
    });

    it('preserves configurable super_attribute integer keys through full round-trip', function () {
        $original = [
            'info_buyRequest' => [
                'super_attribute' => [143 => '25', 92 => '17'],
            ],
        ];

        $item = Mage::getModel('sales/order_item');
        $item->setData('product_options', serialize($original));
        $item->setProductOptions($item->getProductOptions());

        $item2 = Mage::getModel('sales/order_item');
        $item2->setData('product_options', $item->getData('product_options'));

        expect($item2->getProductOptions()['info_buyRequest']['super_attribute'])
            ->toBe([143 => '25', 92 => '17']);
    });

    it('getProductOptionByCode works identically with legacy and JSON data', function () {
        $original = [
            'info_buyRequest' => ['qty' => 3],
            'simple_name' => 'Test Product',
        ];

        // Legacy version
        $item1 = Mage::getModel('sales/order_item');
        $item1->setData('product_options', serialize($original));

        // JSON version
        $item2 = Mage::getModel('sales/order_item');
        $item2->setData('product_options', Mage::helper('core')->jsonEncode($original));

        expect($item1->getProductOptionByCode('info_buyRequest'))
            ->toBe($item2->getProductOptionByCode('info_buyRequest'));
        expect($item1->getProductOptionByCode('simple_name'))
            ->toBe($item2->getProductOptionByCode('simple_name'));
        expect($item1->getProductOptionByCode('nonexistent'))
            ->toBe($item2->getProductOptionByCode('nonexistent'));
    });
});

describe('Order item weee_tax_applied migration', function () {
    it('reads legacy serialized weee_tax_applied', function () {
        $original = [[
            'title' => 'ECO TAX',
            'base_amount' => '5.00',
            'amount' => '5.00',
            'row_amount' => '10.00',
            'base_row_amount' => '10.00',
        ]];

        $item = Mage::getModel('sales/order_item');
        $item->setData('weee_tax_applied', serialize($original));

        $amounts = Mage::helper('core/string')->unserialize($item->getWeeeTaxApplied());
        expect($amounts[0]['title'])->toBe('ECO TAX')
            ->and($amounts[0]['base_amount'])->toBe('5.00');
    });

    it('round-trips weee_tax_applied through JSON conversion', function () {
        $original = [
            ['title' => 'ECO TAX', 'amount' => '5.00'],
            ['title' => 'RECYCLING', 'amount' => '2.50'],
        ];

        // Read legacy
        $helper = Mage::helper('core/string');
        $result = $helper->unserialize(serialize($original));
        expect($result)->toBe($original);

        // Write as JSON (what setApplied now does)
        $json = Mage::helper('core')->jsonEncode($result);

        // Read JSON (what getApplied now does)
        expect($helper->unserialize($json))->toBe($original);
    });
});
