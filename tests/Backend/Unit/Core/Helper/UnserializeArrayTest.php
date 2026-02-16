<?php

/**
 * Maho
 *
 * @package    Mage_Core
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * Tests that Mage_Core_Helper_UnserializeArray correctly converts legacy
 * PHP-serialized data to arrays. This helper is used by config backends
 * and EAV attributes — it throws on failure instead of returning false.
 */
describe('Legacy serialized data conversion via core/unserializeArray helper', function () {
    beforeEach(function () {
        $this->helper = Mage::helper('core/unserializeArray');
    });

    it('converts serialized min sale qty config', function () {
        $original = [0 => '1', 1 => '5', 2 => '10'];
        $legacy = serialize($original);

        expect($this->helper->unserialize($legacy))->toBe($original);
    });

    it('converts serialized EAV validate_rules', function () {
        $original = ['max_text_length' => 255, 'min_text_length' => 1];
        $legacy = serialize($original);

        expect($this->helper->unserialize($legacy))->toBe($original);
    });

    it('converts serialized EAV validate_rules with input_validation', function () {
        $original = ['max_text_length' => 64, 'min_text_length' => 0, 'input_validation' => 'email'];
        $legacy = serialize($original);

        $result = $this->helper->unserialize($legacy);
        expect($result['input_validation'])->toBe('email')
            ->and($result['max_text_length'])->toBe(64);
    });

    it('reads the same data after JSON conversion', function () {
        $original = ['max_text_length' => 255, 'min_text_length' => 1];
        $json = Mage::helper('core')->jsonEncode($original);

        expect($this->helper->unserialize($json))->toBe($original);
    });

    it('throws on corrupt legacy serialized data', function () {
        expect(fn () => $this->helper->unserialize('a:2:{s:3:"key";s:100:"truncated'))
            ->toThrow(Exception::class);
    });

    it('throws on null or empty input', function () {
        expect(fn () => $this->helper->unserialize(null))->toThrow(Exception::class);
        expect(fn () => $this->helper->unserialize(''))->toThrow(Exception::class);
    });
});

/**
 * Edge cases: json_validate() returns true for JSON scalars like "true",
 * "null", "42", '"hello"'. The UnserializeArray helper promises to return
 * arrays (@return array), but jsonDecode on these returns non-array types.
 * These tests document and verify the actual behavior.
 */
describe('UnserializeArray helper with JSON scalar edge cases', function () {
    beforeEach(function () {
        $this->helper = Mage::helper('core/unserializeArray');
    });

    it('returns int when given a bare JSON number', function () {
        // "5" is valid JSON. json_validate("5") = true.
        // jsonDecode("5") returns int 5, not an array.
        // This violates the @return array contract.
        $result = $this->helper->unserialize('5');
        expect($result)->toBeInt()->toBe(5);
    });

    it('returns bool when given JSON "true"', function () {
        $result = $this->helper->unserialize('true');
        expect($result)->toBeBool()->toBe(true);
    });

    it('returns null when given JSON "null"', function () {
        $result = $this->helper->unserialize('null');
        expect($result)->toBeNull();
    });

    it('returns string when given a JSON-encoded string', function () {
        // '"hello"' is valid JSON — a quoted string
        $result = $this->helper->unserialize('"hello"');
        expect($result)->toBeString()->toBe('hello');
    });
});

/**
 * Tests that the Serialized config backend handles the same JSON scalar
 * edge cases safely, since it uses UnserializeArray under the hood.
 */
describe('Serialized config backend with JSON scalar values in DB', function () {
    it('does not crash when DB contains bare JSON number "5"', function () {
        // A numeric string like "5" is valid JSON. If it ends up in
        // core_config_data, afterLoad should handle it gracefully.
        $backend = Mage::getModel('adminhtml/system_config_backend_serialized');
        $backend->setValue('5');
        $backend->afterLoad();

        // UnserializeArray returns int 5, which is not an array,
        // so afterLoad sets it as-is (no crash)
        expect($backend->getValue())->toBe(5);
    });

    it('does not crash when DB contains JSON "null"', function () {
        $backend = Mage::getModel('adminhtml/system_config_backend_serialized');
        $backend->setValue('null');
        $backend->afterLoad();

        expect($backend->getValue())->toBeNull();
    });
});
