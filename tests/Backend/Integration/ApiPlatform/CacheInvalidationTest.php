<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Tests\MahoBackendTestCase;

uses(MahoBackendTestCase::class);

/*
 * Maho_ApiPlatform_Model_Observer hooks nine everyday events (product, category,
 * stock, catalog rule, review, config and store saves). Marking the api_data type
 * invalidated from there means placing a single order re-raises the admin's
 * "cache types are invalidated" notice, which can then never stay cleared.
 * Cleaning the tags is enough: every api_data consumer caches under one of them.
 */

function apiDataIsFlaggedInvalid(): bool
{
    $flags = Mage::app()->getCache()->load(Mage_Core_Model_Cache::INVALIDATED_TYPES);
    return is_array($flags) && isset($flags['api_data']);
}

function apiPlatformEventObserver(string $key, mixed $object): Maho\Event\Observer
{
    $event = new Maho\Event();
    $event->setData($key, $object);

    $eventObserver = new Maho\Event\Observer();
    $eventObserver->setEvent($event);

    return $eventObserver;
}

describe('API platform cache invalidation', function () {
    beforeEach(function () {
        Mage::app()->getCache()->cleanType('api_data');
        $this->observer = new Maho_ApiPlatform_Model_Observer();
    });

    it('does not flag the api_data type when a stock item save is dispatched', function () {
        // Placing an order full-saves any item that goes out of stock or drops
        // below notify_stock_qty, so this event fires on ordinary storefront traffic.
        Mage::dispatchEvent('cataloginventory_stock_item_save_after', [
            'item' => Mage::getModel('cataloginventory/stock_item'),
        ]);

        expect(apiDataIsFlaggedInvalid())->toBeFalse();
    });

    it('does not flag the api_data type on a product save', function () {
        $product = Mage::getModel('catalog/product')->setId(1);

        $this->observer->invalidateProductCache(apiPlatformEventObserver('product', $product));

        expect(apiDataIsFlaggedInvalid())->toBeFalse();
    });

    it('does not flag the api_data type on a config save', function () {
        $this->observer->invalidateStoreConfigCache(apiPlatformEventObserver('data_object', null));

        expect(apiDataIsFlaggedInvalid())->toBeFalse();
    });

    it('still cleans the API cache tags it is responsible for', function () {
        $cache = Mage::app()->getCache();
        $cache->save('cached', 'test_api_products_entry', ['API_PRODUCTS']);
        $cache->save('cached', 'test_api_store_config_entry', ['API_STORE_CONFIG']);

        $this->observer->invalidateStockCache(apiPlatformEventObserver('item', null));
        $this->observer->invalidateStoreConfigCache(apiPlatformEventObserver('data_object', null));

        expect($cache->load('test_api_products_entry'))->toBeFalse();
        expect($cache->load('test_api_store_config_entry'))->toBeFalse();
    });
});
