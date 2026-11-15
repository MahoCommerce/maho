<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('Feed Collection', function () {
    beforeEach(function () {
        $this->createFeed = fn(string $name, string $platform, int $enabled, int $storeId): Maho_FeedManager_Model_Feed => Mage::getModel('feedmanager/feed')
            ->setName($name)
            ->setPlatform($platform)
            ->setStoreId($storeId)
            ->setIsEnabled($enabled)
            ->setFilename('collection-test-' . uniqid())
            ->setFileFormat('xml')
            ->save();

        $this->enabledFeed = ($this->createFeed)('Collection Enabled Feed', 'google', 1, 1);
        $this->disabledFeed = ($this->createFeed)('Collection Disabled Feed', 'facebook', 0, 0);
        $this->testCollection = fn(): Maho_FeedManager_Model_Resource_Feed_Collection => Mage::getResourceModel('feedmanager/feed_collection')
            ->addFieldToFilter('feed_id', ['in' => [(int) $this->enabledFeed->getId(), (int) $this->disabledFeed->getId()]]);
        $this->ids = fn(Maho_FeedManager_Model_Resource_Feed_Collection $collection): array => array_map(intval(...), $collection->getAllIds());
    });

    afterEach(function () {
        $this->enabledFeed->delete();
        $this->disabledFeed->delete();
    });

    test('the enabled filter keeps only the enabled feeds', function () {
        expect(($this->ids)(($this->testCollection)()->addEnabledFilter()))
            ->toBe([(int) $this->enabledFeed->getId()]);
    });

    test('the platform filter keeps only the feeds of the platform', function () {
        expect(($this->ids)(($this->testCollection)()->addPlatformFilter('facebook')))
            ->toBe([(int) $this->disabledFeed->getId()]);
    });

    test('the store filter keeps only the feeds of the store', function () {
        expect(($this->ids)(($this->testCollection)()->addStoreFilter(1)))
            ->toBe([(int) $this->enabledFeed->getId()]);
    });

    test('the option array gives the feed name for each feed ID', function () {
        $labels = array_column(($this->testCollection)()->toOptionArray(), 'label', 'value');

        expect($labels)->toHaveCount(2)
            ->and($labels[(int) $this->enabledFeed->getId()])->toBe('Collection Enabled Feed')
            ->and($labels[(int) $this->disabledFeed->getId()])->toBe('Collection Disabled Feed');
    });
});

describe('Destination Collection', function () {
    test('returns correct collection class', function () {
        $collection = Mage::getResourceModel('feedmanager/destination_collection');
        expect($collection)->toBeInstanceOf(Maho_FeedManager_Model_Resource_Destination_Collection::class);
    });
});

describe('DynamicRule Collection', function () {
    test('returns correct collection class', function () {
        $collection = Mage::getResourceModel('feedmanager/dynamicRule_collection');
        expect($collection)->toBeInstanceOf(Maho_FeedManager_Model_Resource_DynamicRule_Collection::class);
    });
});
