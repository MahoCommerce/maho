<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('Giftcard Helper buildAdditionalOptions()', function () {
    beforeEach(function () {
        $this->helper = Mage::helper('giftcard');
    });

    test('returns empty array when no gift card data in buy request', function () {
        $buyRequest = new Maho\DataObject([
            'qty' => 1,
            'product' => 123,
        ]);

        $options = $this->helper->buildAdditionalOptions($buyRequest);
        expect($options)->toBeArray();
        expect($options)->toBeEmpty();
    });

    test('builds options from buy request data', function () {
        $buyRequest = new Maho\DataObject([
            'giftcard_recipient_name' => 'John Doe',
            'giftcard_recipient_email' => 'john@example.com',
            'giftcard_sender_name' => 'Jane Doe',
            'giftcard_message' => 'Happy Birthday!',
        ]);

        $options = $this->helper->buildAdditionalOptions($buyRequest);

        expect($options)->toBeArray();
        expect($options)->toHaveCount(4);

        // Check structure
        expect($options[0])->toHaveKey('label');
        expect($options[0])->toHaveKey('value');
        expect($options[0]['value'])->toBe('John Doe');
    });

    test('skips empty values', function () {
        $buyRequest = new Maho\DataObject([
            'giftcard_recipient_name' => 'John Doe',
            'giftcard_recipient_email' => '', // Empty
            'giftcard_sender_name' => null, // Null
            'giftcard_message' => 'Hello',
        ]);

        $options = $this->helper->buildAdditionalOptions($buyRequest);

        expect($options)->toHaveCount(2);
    });
});

describe('Giftcard Helper Code Generation', function () {
    beforeEach(function () {
        $this->helper = Mage::helper('giftcard');
    });

    test('generateCode produces unique codes', function () {
        $codes = [];
        for ($i = 0; $i < 10; $i++) {
            $codes[] = $this->helper->generateCode();
        }

        $uniqueCodes = array_unique($codes);
        expect($uniqueCodes)->toHaveCount(10);
    });

    test('generateCode uses only valid characters (no I, O for clarity)', function () {
        // Generate multiple codes and check characters
        for ($i = 0; $i < 5; $i++) {
            $code = $this->helper->generateCode();
            // Remove any prefix/separators for character check
            $cleanCode = preg_replace('/[^A-Z0-9]/', '', $code);
            expect($cleanCode)->not->toContain('I');
            expect($cleanCode)->not->toContain('O');
        }
    });

    test('formatCode returns uppercase', function () {
        $formatted = $this->helper->formatCode('abc-123-xyz');
        expect($formatted)->toBe('ABC-123-XYZ');
    });
});

describe('Giftcard Helper Configuration Methods', function () {
    beforeEach(function () {
        $this->helper = Mage::helper('giftcard');
    });

    test('getProductLifetime uses product attribute when set', function () {
        $product = Mage::getModel('catalog/product');
        $product->setData('giftcard_lifetime', 90);

        $result = $this->helper->getProductLifetime($product);
        expect($result)->toBe(90);
    });

    test('getProductLifetime falls back to config when product attribute not set', function () {
        $product = Mage::getModel('catalog/product');
        // No giftcard_lifetime set

        $result = $this->helper->getProductLifetime($product);
        expect($result)->toBe($this->helper->getLifetime());
    });

    test('getProductAllowMessage uses product attribute when set', function () {
        $product = Mage::getModel('catalog/product');
        $product->setData('giftcard_allow_message', 1);

        $result = $this->helper->getProductAllowMessage($product);
        expect($result)->toBeTrue();

        $product->setData('giftcard_allow_message', 0);
        $result = $this->helper->getProductAllowMessage($product);
        expect($result)->toBeFalse();
    });
});

describe('Giftcard Helper Expiration Calculation', function () {
    beforeEach(function () {
        $this->helper = Mage::helper('giftcard');
    });

    test('calculateExpirationDate returns null when lifetime is 0', function () {
        // This depends on config, but we test the null case
        $product = Mage::getModel('catalog/product');
        $product->setData('giftcard_lifetime', 0);

        $result = $this->helper->calculateProductExpirationDate($product);
        expect($result)->toBeNull();
    });

    test('calculateProductExpirationDate returns future date when lifetime is set', function () {
        $product = Mage::getModel('catalog/product');
        $product->setData('giftcard_lifetime', 365);

        $result = $this->helper->calculateProductExpirationDate($product);

        expect($result)->not->toBeNull();

        // Parse the result and verify it's in the future
        $expirationDate = new DateTime($result, new DateTimeZone('UTC'));
        $now = new DateTime('now', new DateTimeZone('UTC'));

        expect($expirationDate > $now)->toBeTrue();
    });
});

describe('Giftcard Helper Barcode/QR Methods', function () {
    beforeEach(function () {
        $this->helper = Mage::helper('giftcard');
    });

    test('getQrCodeDataUrl returns an SVG data URL only when the QR code is enabled', function () {
        Mage::app()->getStore()->setConfig('giftcard/general/show_qrcode', '1');
        expect($this->helper->getQrCodeDataUrl('TEST-CODE-123'))->toStartWith('data:image/svg+xml;base64,');

        Mage::app()->getStore()->setConfig('giftcard/general/show_qrcode', '0');
        expect($this->helper->getQrCodeDataUrl('TEST-CODE-123'))->toBe('');
    });

    test('getBarcodeDataUrl returns an SVG data URL only when the barcode is enabled and the package is installed', function () {
        Mage::app()->getStore()->setConfig('giftcard/general/show_barcode', '1');
        $result = $this->helper->getBarcodeDataUrl('TEST-CODE-123');
        if ($this->helper->isBarcodePackageInstalled()) {
            expect($result)->toStartWith('data:image/svg+xml;base64,');
        } else {
            expect($result)->toBe('');
        }

        Mage::app()->getStore()->setConfig('giftcard/general/show_barcode', '0');
        expect($this->helper->getBarcodeDataUrl('TEST-CODE-123'))->toBe('');
    });
});

describe('Giftcard Helper Currency Formatting', function () {
    beforeEach(function () {
        $this->helper = Mage::helper('giftcard');
    });

    test('formatAmount returns formatted string', function () {
        $result = $this->helper->formatAmount(99.99);
        expect($result)->toBeString();
        expect($result)->toContain('99');
    });

    test('formatAmount formats the amount in the currency that the caller gives', function () {
        expect($this->helper->formatAmount(100.00, 'USD'))->toContain('$')->toContain('100.00');
        expect($this->helper->formatAmount(100.00, 'EUR'))->toContain('€')->toContain('100.00');
    });
});
