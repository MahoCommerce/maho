<?php

/**
 * Maho
 *
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('Giftcard Product Type Constants', function () {
    test('has correct type code constant', function () {
        expect(Maho_Giftcard_Model_Product_Type_Giftcard::TYPE_CODE)->toBe('giftcard');
    });
});

describe('Giftcard Product Type Instantiation', function () {
    test('extends Virtual product type', function () {
        $type = Mage::getSingleton('catalog/product_type')->factory(
            Mage::getModel('catalog/product')->setTypeId('giftcard'),
        );
        expect($type)->toBeInstanceOf(Maho_Giftcard_Model_Product_Type_Giftcard::class);
        expect($type)->toBeInstanceOf(Mage_Catalog_Model_Product_Type_Virtual::class);
    });
});

describe('Giftcard Product Type Properties', function () {
    beforeEach(function () {
        $this->product = Mage::getModel('catalog/product');
        $this->product->setTypeId('giftcard');
        $this->typeInstance = Mage::getSingleton('catalog/product_type')->factory($this->product);
    });

    test('isVirtual always returns true', function () {
        expect($this->typeInstance->isVirtual())->toBeTrue();
        expect($this->typeInstance->isVirtual($this->product))->toBeTrue();
    });

    test('isSalable always returns true', function () {
        expect($this->typeInstance->isSalable())->toBeTrue();
        expect($this->typeInstance->isSalable($this->product))->toBeTrue();
    });

    test('canConfigure returns true', function () {
        expect($this->typeInstance->canConfigure())->toBeTrue();
        expect($this->typeInstance->canConfigure($this->product))->toBeTrue();
    });

    test('hasRequiredOptions returns true', function () {
        expect($this->typeInstance->hasRequiredOptions())->toBeTrue();
        expect($this->typeInstance->hasRequiredOptions($this->product))->toBeTrue();
    });

    test('hasOptions returns true', function () {
        expect($this->typeInstance->hasOptions())->toBeTrue();
        expect($this->typeInstance->hasOptions($this->product))->toBeTrue();
    });
});

describe('Giftcard Product Type Price Calculation', function () {
    beforeEach(function () {
        $this->product = Mage::getModel('catalog/product');
        $this->product->setTypeId('giftcard');
        $this->product->setStoreId(1);
        $this->typeInstance = Mage::getSingleton('catalog/product_type')->factory($this->product);
    });

    test('getMinimumPrice returns lowest fixed amount', function () {
        $this->product->setData('giftcard_amounts', '25, 50, 100');
        $this->product->setData('giftcard_type', 'fixed');

        $minPrice = $this->typeInstance->getMinimumPrice($this->product);
        expect($minPrice)->toBe(25.0);
    });

    test('getMinimumPrice returns min_amount for custom type', function () {
        $this->product->setData('giftcard_type', 'custom');
        $this->product->setData('giftcard_min_amount', 10.00);
        $this->product->setData('giftcard_max_amount', 500.00);

        $minPrice = $this->typeInstance->getMinimumPrice($this->product);
        expect($minPrice)->toBe(10.0);
    });

    test('getMinimumPrice returns zero when no amounts configured', function () {
        $this->product->setData('giftcard_type', 'custom');
        // No min amount set

        $minPrice = $this->typeInstance->getMinimumPrice($this->product);
        expect($minPrice)->toBe(0.0);
    });

    test('getPrice returns custom price when set', function () {
        $this->product->setCustomPrice(75.00);

        $price = $this->typeInstance->getPrice($this->product);
        expect($price)->toBe(75.0);
    });
});

describe('Giftcard Product Type processBuyRequest', function () {
    beforeEach(function () {
        $this->product = Mage::getModel('catalog/product');
        $this->product->setTypeId('giftcard');
        $this->typeInstance = Mage::getSingleton('catalog/product_type')->factory($this->product);
    });

    test('extracts all gift card fields from buy request', function () {
        $buyRequest = new Maho\DataObject([
            'giftcard_amount' => 50.00,
            'giftcard_recipient_name' => 'John Doe',
            'giftcard_recipient_email' => 'john@example.com',
            'giftcard_sender_name' => 'Jane Doe',
            'giftcard_sender_email' => 'jane@example.com',
            'giftcard_message' => 'Happy Birthday!',
            'giftcard_delivery_date' => '2025-12-25',
        ]);

        $result = $this->typeInstance->processBuyRequest($this->product, $buyRequest);

        expect($result)->toBeArray();
        expect($result['giftcard_amount'])->toBe(50.00);
        expect($result['giftcard_recipient_name'])->toBe('John Doe');
        expect($result['giftcard_recipient_email'])->toBe('john@example.com');
        expect($result['giftcard_sender_name'])->toBe('Jane Doe');
        expect($result['giftcard_sender_email'])->toBe('jane@example.com');
        expect($result['giftcard_message'])->toBe('Happy Birthday!');
        expect($result['giftcard_delivery_date'])->toBe('2025-12-25');
    });

    test('handles missing fields gracefully', function () {
        $buyRequest = new Maho\DataObject([
            'giftcard_amount' => 100.00,
        ]);

        $result = $this->typeInstance->processBuyRequest($this->product, $buyRequest);

        expect($result)->toBeArray();
        expect($result['giftcard_amount'])->toBe(100.00);
        expect($result['giftcard_recipient_name'])->toBeNull();
        expect($result['giftcard_message'])->toBeNull();
    });
});
