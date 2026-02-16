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
 * Tests that Mage_Core_Model_Flag correctly reads legacy PHP-serialized
 * flag_data from the core_flag table and writes new data as JSON.
 */
describe('Flag model handles null and empty flag_data', function () {
    it('returns null when flag_data is not set', function () {
        $flag = Mage::getModel('core/flag');
        expect($flag->getFlagData())->toBeNull();
    });

    it('returns null when flag_data is null', function () {
        $flag = Mage::getModel('core/flag');
        $flag->setData('flag_data', null);
        expect($flag->getFlagData())->toBeNull();
    });

    it('returns false when flag_data is empty string', function () {
        // Empty string passes hasFlagData() but unserialize('') returns false
        $flag = Mage::getModel('core/flag');
        $flag->setData('flag_data', '');
        expect($flag->getFlagData())->toBeFalse();
    });
});

describe('Flag model reads legacy serialized flag_data', function () {
    it('reads indexer status flag', function () {
        $original = ['is_built' => true, 'store_id' => 1];

        $flag = Mage::getModel('core/flag');
        $flag->setData('flag_data', serialize($original));

        expect($flag->getFlagData())->toBe($original);
    });

    it('reads catalog price rules flag with product_ids array', function () {
        $original = ['product_ids' => [42, 99, 150]];

        $flag = Mage::getModel('core/flag');
        $flag->setData('flag_data', serialize($original));

        expect($flag->getFlagData()['product_ids'])->toBe([42, 99, 150]);
    });

    it('reads deeply nested flag data', function () {
        $original = [
            'stats' => ['products' => 500, 'categories' => 50],
            'state' => ['running' => false],
        ];

        $flag = Mage::getModel('core/flag');
        $flag->setData('flag_data', serialize($original));

        $result = $flag->getFlagData();
        expect($result['stats']['products'])->toBe(500)
            ->and($result['state']['running'])->toBe(false);
    });
});

describe('Flag model migration round-trip', function () {
    it('legacy serialized → getFlagData → setFlagData → JSON → getFlagData', function () {
        $original = ['is_built' => true, 'store_id' => 1];

        // Step 1: Read legacy serialized data
        $flag = Mage::getModel('core/flag');
        $flag->setData('flag_data', serialize($original));
        $data = $flag->getFlagData();
        expect($data)->toBe($original);

        // Step 2: Write it back (now encodes as JSON)
        $flag->setFlagData($data);
        $raw = $flag->getData('flag_data');
        expect(json_validate($raw))->toBeTrue()
            ->and($raw)->not->toStartWith('a:');

        // Step 3: Read the JSON version
        $flag2 = Mage::getModel('core/flag');
        $flag2->setData('flag_data', $raw);
        expect($flag2->getFlagData())->toBe($original);
    });

    it('preserves complex data with mixed types through conversion', function () {
        $original = [
            'string' => 'hello',
            'int' => 42,
            'float' => 3.14,
            'bool' => true,
            'null_val' => null,
            'nested' => ['a' => 1, 'b' => [2, 3]],
        ];

        // Legacy → read → write as JSON → read again
        $flag = Mage::getModel('core/flag');
        $flag->setData('flag_data', serialize($original));
        $flag->setFlagData($flag->getFlagData());

        $flag2 = Mage::getModel('core/flag');
        $flag2->setData('flag_data', $flag->getData('flag_data'));
        expect($flag2->getFlagData())->toBe($original);
    });
});
