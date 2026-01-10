<?php

/**
 * Maho
 *
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('FeedManager Feed Model', function () {
    beforeEach(function () {
        $this->feed = Mage::getModel('feedmanager/feed');
    });

    test('can create new feed instance', function () {
        expect($this->feed)->toBeInstanceOf(Maho_FeedManager_Model_Feed::class);
        expect($this->feed->getId())->toBeNull();
    });

    test('has correct configurable mode constants', function () {
        expect(Maho_FeedManager_Model_Feed::CONFIGURABLE_MODE_SIMPLE_ONLY)->toBe('simple_only');
        expect(Maho_FeedManager_Model_Feed::CONFIGURABLE_MODE_CHILDREN_ONLY)->toBe('children_only');
        expect(Maho_FeedManager_Model_Feed::CONFIGURABLE_MODE_BOTH)->toBe('both');
    });

    test('can set and get basic attributes', function () {
        $this->feed->setName('Test Feed');
        $this->feed->setFilename('test_feed');
        $this->feed->setPlatform('google');
        $this->feed->setFileFormat('xml');
        $this->feed->setStoreId(1);
        $this->feed->setIsEnabled(1);

        expect($this->feed->getName())->toBe('Test Feed');
        expect($this->feed->getFilename())->toBe('test_feed');
        expect($this->feed->getPlatform())->toBe('google');
        expect($this->feed->getFileFormat())->toBe('xml');
        expect($this->feed->getStoreId())->toBe(1);
        expect((int) $this->feed->getIsEnabled())->toBe(1);
    });

    test('can handle product filters as array', function () {
        $filters = [
            ['attribute' => 'status', 'operator' => 'eq', 'value' => '1'],
            ['attribute' => 'visibility', 'operator' => 'in', 'value' => '2,3,4'],
        ];

        $this->feed->setProductFiltersArray($filters);
        $retrieved = $this->feed->getProductFiltersArray();

        expect($retrieved)->toBeArray();
        expect(count($retrieved))->toBe(2);
        expect($retrieved[0]['attribute'])->toBe('status');
        expect($retrieved[1]['operator'])->toBe('in');
    });

    test('returns empty array for null product filters', function () {
        $this->feed->setProductFilters(null);
        expect($this->feed->getProductFiltersArray())->toBe([]);
    });

    test('can get configurable mode options', function () {
        $options = Maho_FeedManager_Model_Feed::getConfigurableModeOptions();

        expect($options)->toBeArray();
        expect($options)->toHaveKey('simple_only');
        expect($options)->toHaveKey('children_only');
        expect($options)->toHaveKey('both');
    });

    test('isEnabled returns correct boolean', function () {
        $this->feed->setIsEnabled(1);
        expect($this->feed->isEnabled())->toBeTrue();

        $this->feed->setIsEnabled(0);
        expect($this->feed->isEnabled())->toBeFalse();
    });

    test('can save and load feed', function () {
        $this->feed->setName('Test Persistence');
        $this->feed->setFilename('test_persistence');
        $this->feed->setPlatform('google');
        $this->feed->setFileFormat('xml');
        $this->feed->setStoreId(1);
        $this->feed->setIsEnabled(1);
        $this->feed->save();

        expect($this->feed->getId())->toBeGreaterThan(0);

        $loadedFeed = Mage::getModel('feedmanager/feed')->load($this->feed->getId());
        expect($loadedFeed->getName())->toBe('Test Persistence');
        expect($loadedFeed->getPlatform())->toBe('google');

        // Cleanup
        $loadedFeed->delete();
    });
});
