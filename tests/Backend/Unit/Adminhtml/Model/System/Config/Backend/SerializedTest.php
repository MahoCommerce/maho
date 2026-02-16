<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * Tests that Serialized config backends correctly read legacy PHP-serialized
 * values from core_config_data on afterLoad().
 *
 * Note: _beforeSave() is protected and called internally by save().
 * We test serialization output by directly verifying jsonEncode behavior
 * and round-tripping through afterLoad().
 */
describe('Serialized config backend reads legacy data on afterLoad', function () {
    it('reads legacy serialized shipping rates config', function () {
        $original = ['US_CA_*' => '5.00', 'US_NY_*' => '7.50', 'US_TX_*' => '6.00'];

        $backend = Mage::getModel('adminhtml/system_config_backend_serialized');
        $backend->setValue(serialize($original));
        $backend->afterLoad();

        expect($backend->getValue())->toBe($original);
    });

    it('reads legacy serialized currency symbols', function () {
        $original = ['USD' => '$', 'EUR' => '€'];

        $backend = Mage::getModel('adminhtml/system_config_backend_serialized');
        $backend->setValue(serialize($original));
        $backend->afterLoad();

        expect($backend->getValue())->toBe($original);
    });

    it('reads already-converted JSON values', function () {
        $original = ['US_CA_*' => '5.00', 'US_NY_*' => '7.50'];

        $backend = Mage::getModel('adminhtml/system_config_backend_serialized');
        $backend->setValue(Mage::helper('core')->jsonEncode($original));
        $backend->afterLoad();

        expect($backend->getValue())->toBe($original);
    });

    it('handles invalid data gracefully without crashing', function () {
        $backend = Mage::getModel('adminhtml/system_config_backend_serialized');
        $backend->setValue('corrupted-data-from-bad-migration');
        $backend->afterLoad();

        // Invalid data results in false (exception is caught and logged)
        expect($backend->getValue())->toBeFalse();
    });
});

describe('Serialized config backend full migration round-trip', function () {
    it('legacy serialized → afterLoad → jsonEncode → afterLoad', function () {
        $original = ['US_CA_*' => '5.00', 'US_NY_*' => '7.50'];

        // Step 1: Read legacy
        $backend = Mage::getModel('adminhtml/system_config_backend_serialized');
        $backend->setValue(serialize($original));
        $backend->afterLoad();
        expect($backend->getValue())->toBe($original);

        // Step 2: Encode as JSON (what _beforeSave now does)
        $json = Mage::helper('core')->jsonEncode($backend->getValue());
        expect(json_validate($json))->toBeTrue();

        // Step 3: Read the JSON version
        $backend2 = Mage::getModel('adminhtml/system_config_backend_serialized');
        $backend2->setValue($json);
        $backend2->afterLoad();
        expect($backend2->getValue())->toBe($original);
    });

    it('preserves nested config arrays through conversion', function () {
        $original = [
            'rates' => [
                ['country' => 'US', 'rate' => '5.00'],
                ['country' => 'CA', 'rate' => '7.00'],
            ],
        ];

        $backend = Mage::getModel('adminhtml/system_config_backend_serialized');
        $backend->setValue(serialize($original));
        $backend->afterLoad();

        $json = Mage::helper('core')->jsonEncode($backend->getValue());

        $backend2 = Mage::getModel('adminhtml/system_config_backend_serialized');
        $backend2->setValue($json);
        $backend2->afterLoad();

        expect($backend2->getValue())->toBe($original);
    });
});

describe('Serialized_Array config backend migration', function () {
    it('reads legacy serialized min sale qty table', function () {
        $original = [
            '_12345' => ['customer_group_id' => '0', 'min_sale_qty' => '1'],
            '_12346' => ['customer_group_id' => '1', 'min_sale_qty' => '5'],
            '_12347' => ['customer_group_id' => '2', 'min_sale_qty' => '10'],
        ];

        $backend = Mage::getModel('adminhtml/system_config_backend_serialized_array');
        $backend->setValue(serialize($original));
        $backend->afterLoad();

        $value = $backend->getValue();
        expect($value['_12345']['customer_group_id'])->toBe('0')
            ->and($value['_12346']['min_sale_qty'])->toBe('5')
            ->and($value['_12347']['min_sale_qty'])->toBe('10');
    });

    it('round-trips min sale qty table through JSON conversion', function () {
        $original = [
            '_12345' => ['customer_group_id' => '0', 'min_sale_qty' => '1'],
            '_12346' => ['customer_group_id' => '1', 'min_sale_qty' => '5'],
        ];

        // Read legacy
        $backend = Mage::getModel('adminhtml/system_config_backend_serialized_array');
        $backend->setValue(serialize($original));
        $backend->afterLoad();
        expect($backend->getValue())->toBe($original);

        // Convert to JSON
        $json = Mage::helper('core')->jsonEncode($backend->getValue());

        // Read JSON
        $backend2 = Mage::getModel('adminhtml/system_config_backend_serialized_array');
        $backend2->setValue($json);
        $backend2->afterLoad();
        expect($backend2->getValue())->toBe($original);
    });
});
