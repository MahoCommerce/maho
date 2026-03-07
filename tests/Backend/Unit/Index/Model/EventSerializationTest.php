<?php

/**
 * Maho
 *
 * @package    Mage_Index
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * Tests that Mage_Index_Model_Event correctly handles new_data serialization
 * through getNewData(), addNewData(), and mergePreviousData().
 *
 * The Event model is interesting because it has a mixed pattern:
 * - setNewData() can receive either a string (JSON) or an array
 * - getNewData() checks is_string() before calling unserialize
 * - addNewData() calls getNewData() then setNewData() with an array
 */
describe('Index Event getNewData reads both formats', function () {
    it('reads legacy serialized new_data', function () {
        $original = [
            'Mage_Catalog_Model_Indexer_Url' => [
                'reindex_all' => true,
            ],
        ];

        $event = Mage::getModel('index/event');
        $event->setData('new_data', serialize($original));

        expect($event->getNewData(false))->toBe($original);
    });

    it('reads JSON new_data', function () {
        $original = [
            'Mage_Catalog_Model_Indexer_Url' => [
                'reindex_all' => true,
            ],
        ];

        $event = Mage::getModel('index/event');
        $event->setData('new_data', Mage::helper('core')->jsonEncode($original));

        expect($event->getNewData(false))->toBe($original);
    });

    it('returns empty array when new_data is null', function () {
        $event = Mage::getModel('index/event');
        expect($event->getNewData(false))->toBe([]);
    });

    it('returns empty array when new_data is empty string', function () {
        $event = Mage::getModel('index/event');
        $event->setData('new_data', '');
        expect($event->getNewData(false))->toBe([]);
    });
});

describe('Index Event addNewData round-trip', function () {
    it('accumulates data across multiple addNewData calls', function () {
        $event = Mage::getModel('index/event');

        $event->addNewData('product_ids', [42, 99]);
        $event->addNewData('reindex_all', true);

        $data = $event->getNewData(false);
        expect($data['product_ids'])->toBe([42, 99])
            ->and($data['reindex_all'])->toBe(true);
    });

    it('accumulates data with namespace', function () {
        $event = Mage::getModel('index/event');
        $event->setDataNamespace('Mage_Catalog_Model_Indexer_Url');

        $event->addNewData('product_ids', [42]);
        $event->addNewData('reindex_all', true);

        // With namespace
        $namespaced = $event->getNewData(true);
        expect($namespaced['product_ids'])->toBe([42])
            ->and($namespaced['reindex_all'])->toBe(true);

        // Without namespace
        $full = $event->getNewData(false);
        expect($full)->toHaveKey('Mage_Catalog_Model_Indexer_Url');
    });

    it('reads data set as array by addNewData without encoding issues', function () {
        // addNewData calls setNewData with an array (not JSON string).
        // Then getNewData checks is_string() — if setNewData stores an array,
        // getNewData should return it directly.
        $event = Mage::getModel('index/event');
        $event->addNewData(['key1' => 'val1', 'key2' => 'val2']);

        $data = $event->getNewData(false);
        expect($data['key1'])->toBe('val1')
            ->and($data['key2'])->toBe('val2');
    });
});

describe('Index Event mergePreviousData handles both formats', function () {
    it('merges legacy serialized previous data with current data', function () {
        $previousData = [
            'Mage_Catalog_Model_Indexer_Url' => [
                'product_ids' => [42],
            ],
        ];

        $event = Mage::getModel('index/event');
        $event->addNewData('Mage_Catalog_Model_Indexer_Url', [
            'product_ids' => [99],
        ]);

        $event->mergePreviousData([
            'new_data' => serialize($previousData),
        ]);

        $data = $event->getNewData(false);
        $productIds = $data['Mage_Catalog_Model_Indexer_Url']['product_ids'];
        expect($productIds)->toContain(42)
            ->and($productIds)->toContain(99);
    });

    it('merges JSON previous data with current data', function () {
        $previousData = [
            'Mage_Catalog_Model_Indexer_Url' => [
                'product_ids' => [42],
            ],
        ];

        $event = Mage::getModel('index/event');
        $event->addNewData('Mage_Catalog_Model_Indexer_Url', [
            'product_ids' => [99],
        ]);

        $event->mergePreviousData([
            'new_data' => Mage::helper('core')->jsonEncode($previousData),
        ]);

        $data = $event->getNewData(false);
        $productIds = $data['Mage_Catalog_Model_Indexer_Url']['product_ids'];
        expect($productIds)->toContain(42)
            ->and($productIds)->toContain(99);
    });
});

describe('Index Event resetData', function () {
    it('clears all data when no namespace is set', function () {
        $event = Mage::getModel('index/event');
        $event->addNewData('product_ids', [42]);
        $event->resetData();

        expect($event->getNewData(false))->toBe([]);
    });

    it('clears only namespace data when namespace is set', function () {
        $event = Mage::getModel('index/event');

        // Add data under a namespace
        $event->setDataNamespace('Mage_Catalog_Model_Indexer_Url');
        $event->addNewData('product_ids', [42]);

        // Add data under another key directly
        $full = $event->getNewData(false);
        $full['other_key'] = 'should_survive';
        $event->setNewData($full);

        // Reset only clears the namespace
        $event->resetData();

        $data = $event->getNewData(false);
        expect($data)->toHaveKey('other_key')
            ->and($data['other_key'])->toBe('should_survive')
            ->and($data['Mage_Catalog_Model_Indexer_Url'])->toBeNull();
    });
});
