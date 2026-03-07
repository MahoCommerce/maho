<?php

/**
 * Maho
 *
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('Feed Collection', function () {
    test('returns correct collection class', function () {
        $collection = Mage::getResourceModel('feedmanager/feed_collection');
        expect($collection)->toBeInstanceOf(Maho_FeedManager_Model_Resource_Feed_Collection::class);
    });

    test('can apply enabled filter', function () {
        $collection = Mage::getResourceModel('feedmanager/feed_collection');
        $result = $collection->addEnabledFilter();
        expect($result)->toBeInstanceOf(Maho_FeedManager_Model_Resource_Feed_Collection::class);
    });

    test('can apply platform filter', function () {
        $collection = Mage::getResourceModel('feedmanager/feed_collection');
        $result = $collection->addPlatformFilter('google');
        expect($result)->toBeInstanceOf(Maho_FeedManager_Model_Resource_Feed_Collection::class);
    });

    test('can apply store filter', function () {
        $collection = Mage::getResourceModel('feedmanager/feed_collection');
        $result = $collection->addStoreFilter(1);
        expect($result)->toBeInstanceOf(Maho_FeedManager_Model_Resource_Feed_Collection::class);
    });

    test('can convert to option array', function () {
        $collection = Mage::getResourceModel('feedmanager/feed_collection');
        $options = $collection->toOptionArray();
        expect($options)->toBeArray();
    });
});

describe('Log Collection', function () {
    test('returns correct collection class', function () {
        $collection = Mage::getResourceModel('feedmanager/log_collection');
        expect($collection)->toBeInstanceOf(Maho_FeedManager_Model_Resource_Log_Collection::class);
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

describe('AttributeMapping Collection', function () {
    test('returns correct collection class', function () {
        $collection = Mage::getResourceModel('feedmanager/attributeMapping_collection');
        expect($collection)->toBeInstanceOf(Maho_FeedManager_Model_Resource_AttributeMapping_Collection::class);
    });
});

describe('CategoryMapping Collection', function () {
    test('returns correct collection class', function () {
        $collection = Mage::getResourceModel('feedmanager/categoryMapping_collection');
        expect($collection)->toBeInstanceOf(Maho_FeedManager_Model_Resource_CategoryMapping_Collection::class);
    });
});
