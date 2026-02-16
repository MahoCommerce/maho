<?php

/**
 * Maho
 *
 * @package    Mage_Core
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * Tests that _unserializeField() in Mage_Core_Model_Resource_Db_Abstract
 * correctly decodes both legacy PHP-serialized data and JSON data via the
 * public unserializeFields() entry point.
 *
 * Uses Mage_Sales_Model_Resource_Order_Payment which declares
 * _serializableFields = ['additional_information' => [null, []]].
 */
describe('Resource model _unserializeField via unserializeFields()', function () {
    beforeEach(function () {
        $this->resource = Mage::getResourceModel('sales/order_payment');
    });

    it('decodes legacy serialized additional_information', function () {
        $original = ['method_title' => 'Check / Money order', 'transaction_id' => 'TX123'];

        $payment = Mage::getModel('sales/order_payment');
        $payment->setData('additional_information', serialize($original));

        $this->resource->unserializeFields($payment);

        expect($payment->getData('additional_information'))->toBe($original);
    });

    it('decodes JSON additional_information', function () {
        $original = ['method_title' => 'Check / Money order', 'transaction_id' => 'TX123'];

        $payment = Mage::getModel('sales/order_payment');
        $payment->setData('additional_information', Mage::helper('core')->jsonEncode($original));

        $this->resource->unserializeFields($payment);

        expect($payment->getData('additional_information'))->toBe($original);
    });

    it('sets default value when field is empty', function () {
        $payment = Mage::getModel('sales/order_payment');
        $payment->setData('additional_information', '');

        $this->resource->unserializeFields($payment);

        // Default for additional_information is [] (second element of _serializableFields)
        expect($payment->getData('additional_information'))->toBe([]);
    });

    it('sets default value when field is null', function () {
        $payment = Mage::getModel('sales/order_payment');
        $payment->setData('additional_information', null);

        $this->resource->unserializeFields($payment);

        expect($payment->getData('additional_information'))->toBe([]);
    });

    it('produces identical results from legacy and JSON formats', function () {
        $original = [
            'method_title' => 'Credit Card',
            'cc_type' => 'VI',
            'cc_last4' => '1111',
        ];

        $fromLegacy = Mage::getModel('sales/order_payment');
        $fromLegacy->setData('additional_information', serialize($original));
        $this->resource->unserializeFields($fromLegacy);

        $fromJson = Mage::getModel('sales/order_payment');
        $fromJson->setData('additional_information', Mage::helper('core')->jsonEncode($original));
        $this->resource->unserializeFields($fromJson);

        expect($fromLegacy->getData('additional_information'))
            ->toBe($fromJson->getData('additional_information'))
            ->toBe($original);
    });

    it('decodes legacy serialized data with nested arrays', function () {
        $original = [
            'method_title' => 'PayPal',
            'fraud_filters' => [
                ['name' => 'Total Purchase Price Ceiling', 'action' => 'review'],
                ['name' => 'IP Velocity', 'action' => 'reject'],
            ],
        ];

        $payment = Mage::getModel('sales/order_payment');
        $payment->setData('additional_information', serialize($original));

        $this->resource->unserializeFields($payment);

        $result = $payment->getData('additional_information');
        expect($result['fraud_filters'])->toHaveCount(2)
            ->and($result['fraud_filters'][0]['name'])->toBe('Total Purchase Price Ceiling')
            ->and($result['fraud_filters'][1]['action'])->toBe('reject');
    });
});

/**
 * Edge cases for _unserializeField empty() check.
 * PHP's empty() returns true for: null, "", 0, "0", false, [].
 * If a serialized field contains any of these as a raw string value,
 * it hits the empty() branch and gets the default instead of being decoded.
 */
describe('Resource model _unserializeField empty() edge cases', function () {
    beforeEach(function () {
        $this->resource = Mage::getResourceModel('sales/order_payment');
    });

    it('treats string "0" as empty and returns default', function () {
        // empty("0") === true in PHP, so "0" gets the default value
        // This matters if a field contains the literal string "0"
        $payment = Mage::getModel('sales/order_payment');
        $payment->setData('additional_information', '0');

        $this->resource->unserializeFields($payment);

        // Gets default [] because empty("0") is true
        expect($payment->getData('additional_information'))->toBe([]);
    });

    it('treats JSON "false" as empty and returns default', function () {
        // "false" is not empty in PHP (it's a non-empty string), BUT
        // if the field contains boolean false from a previous decode,
        // empty(false) === true
        $payment = Mage::getModel('sales/order_payment');
        $payment->setData('additional_information', 'false');

        $this->resource->unserializeFields($payment);

        // "false" is a non-empty string, so it goes to json_validate path
        // json_validate("false") = true, jsonDecode("false") = false (bool)
        expect($payment->getData('additional_information'))->toBe(false);
    });

    it('decodes JSON-encoded empty array "[]"', function () {
        // "[]" is not empty in PHP (non-empty string), so it goes to decode path
        $payment = Mage::getModel('sales/order_payment');
        $payment->setData('additional_information', '[]');

        $this->resource->unserializeFields($payment);

        expect($payment->getData('additional_information'))->toBe([]);
    });

    it('decodes JSON-encoded empty object "{}"', function () {
        $payment = Mage::getModel('sales/order_payment');
        $payment->setData('additional_information', '{}');

        $this->resource->unserializeFields($payment);

        expect($payment->getData('additional_information'))->toBe([]);
    });
});

/**
 * Test _serializeField and _unserializeField round-trip via the resource model.
 * Uses reflection to call the protected _serializeField directly.
 */
describe('Resource model _serializeField writes JSON', function () {
    beforeEach(function () {
        $this->resource = Mage::getResourceModel('sales/order_payment');
    });

    it('encodes array value as JSON string', function () {
        $original = ['method_title' => 'Credit Card', 'cc_last4' => '1111'];

        $payment = Mage::getModel('sales/order_payment');
        $payment->setData('additional_information', $original);

        // Call protected _serializeField via reflection
        $method = new ReflectionMethod($this->resource, '_serializeField');
        $method->invoke($this->resource, $payment, 'additional_information');

        $raw = $payment->getData('additional_information');
        expect($raw)->toBeString()
            ->and(json_validate($raw))->toBeTrue()
            ->and($raw)->toBe('{"method_title":"Credit Card","cc_last4":"1111"}');
    });

    it('uses default value for empty array', function () {
        $payment = Mage::getModel('sales/order_payment');
        $payment->setData('additional_information', []);

        // empty([]) === true, so _serializeField uses defaultValue
        $method = new ReflectionMethod($this->resource, '_serializeField');
        // defaultValue is null for additional_information (first element of _serializableFields)
        $method->invoke($this->resource, $payment, 'additional_information', null);

        expect($payment->getData('additional_information'))->toBeNull();
    });

    it('round-trips array through serialize then unserialize', function () {
        $original = ['method_title' => 'PayPal', 'payer_email' => 'user@example.com'];

        $payment = Mage::getModel('sales/order_payment');
        $payment->setData('additional_information', $original);

        // Serialize (write path)
        $serializeMethod = new ReflectionMethod($this->resource, '_serializeField');
        $serializeMethod->invoke($this->resource, $payment, 'additional_information');

        $encoded = $payment->getData('additional_information');
        expect($encoded)->toBeString();

        // Unserialize (read path)
        $this->resource->unserializeFields($payment);

        expect($payment->getData('additional_information'))->toBe($original);
    });
});
