<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Tests\MahoBackendTestCase;

uses(MahoBackendTestCase::class);

/*
 * invalidateType() evicts nothing, it only flags a type so the admin grid asks for a manual
 * refresh. Saves that know which tags went stale should clean them instead.
 */

function cacheTypeIsFlaggedInvalid(string $type): bool
{
    $flags = Mage::app()->getCache()->load(Mage_Core_Model_Cache::INVALIDATED_TYPES);
    return is_array($flags) && isset($flags[$type]);
}

function callProtected(object $object, string $method, array $args = []): mixed
{
    return (new ReflectionMethod($object, $method))->invokeArgs($object, $args);
}

describe('CMS page save', function () {
    beforeEach(function () {
        Mage::app()->getCache()->cleanType('layout');
        Mage::app()->getCache()->cleanType('block_html');

        $this->page = Mage::getModel('cms/page')
            ->setTitle('Cache cleaning test page')
            ->setIdentifier('cache-cleaning-test-' . uniqid())
            ->setStores([0])
            ->setIsActive(1)
            ->setContent('<p>before</p>')
            ->setRootTemplate('one_column')
            ->save();
    });

    afterEach(function () {
        Mage::register('isSecureArea', true, true);
        $this->page->delete();
        Mage::unregister('isSecureArea');
    });

    it('does not flag the layout type', function () {
        $this->page->setContent('<p>after</p>')->save();

        expect(cacheTypeIsFlaggedInvalid('layout'))->toBeFalse();
    });

    it('cleans the layout cache entries built from the cms_page handle', function () {
        $cache = Mage::app()->getCache();
        $cache->save('cached', 'test_layout_cms_page_handle', ['cms_page', Mage_Core_Model_Layout_Update::LAYOUT_GENERAL_CACHE_TAG]);

        $this->page->setContent('<p>after</p>')->save();

        expect($cache->load('test_layout_cms_page_handle'))->toBeFalse();
    });

    it('cleans the block html cached for this page', function () {
        $cache = Mage::app()->getCache();
        $cache->save('cached', 'test_block_html_cms_page', [Mage_Cms_Model_Page::CACHE_TAG . '_' . $this->page->getId()]);

        $this->page->setContent('<p>after</p>')->save();

        expect($cache->load('test_block_html_cms_page'))->toBeFalse();
    });
});

describe('currency symbol save', function () {
    beforeEach(function () {
        foreach (['config', 'block_html', 'layout'] as $type) {
            Mage::app()->getCache()->cleanType($type);
        }
    });

    it('does not flag the types it clears', function () {
        Mage::getSingleton('currencysymbol/system_currencysymbol')->clearCache();

        expect(cacheTypeIsFlaggedInvalid('config'))->toBeFalse();
        expect(cacheTypeIsFlaggedInvalid('block_html'))->toBeFalse();
        expect(cacheTypeIsFlaggedInvalid('layout'))->toBeFalse();
    });

    it('cleans the cached prices rendered with the old symbol', function () {
        $cache = Mage::app()->getCache();
        $cache->save('cached', 'test_currency_block_html', [Mage_Core_Block_Abstract::CACHE_GROUP]);
        $cache->save('cached', 'test_currency_config', [Mage_Core_Model_Config::CACHE_TAG]);

        Mage::getSingleton('currencysymbol/system_currencysymbol')->clearCache();

        expect($cache->load('test_currency_block_html'))->toBeFalse();
        expect($cache->load('test_currency_config'))->toBeFalse();
    });
});

describe('catalog price rule', function () {
    beforeEach(function () {
        Mage::app()->getCache()->cleanType('block_html');
        $this->rule = Mage::getModel('catalogrule/rule');
    });

    it('cleans its related cache types instead of flagging them', function () {
        $cache = Mage::app()->getCache();
        $cache->save('cached', 'test_rule_block_html', [Mage_Core_Block_Abstract::CACHE_GROUP]);

        callProtected($this->rule, '_cleanCache');

        expect($cache->load('test_rule_block_html'))->toBeFalse();
        expect(cacheTypeIsFlaggedInvalid(Mage_Core_Block_Abstract::CACHE_GROUP))->toBeFalse();
    });

    it('cleans only the product tag when rules are applied to a single product', function () {
        // Runs on every admin product save, so it must not flush the whole block html cache.
        $cache = Mage::app()->getCache();
        $product = Mage::getModel('catalog/product')->setId(1);
        $cache->save('cached', 'test_rule_product_block', [Mage_Catalog_Model_Product::CACHE_TAG . '_1']);
        $cache->save('cached', 'test_rule_unrelated_block', [Mage_Core_Block_Abstract::CACHE_GROUP]);

        callProtected($this->rule, '_cleanProductCache', [$product]);

        expect($cache->load('test_rule_product_block'))->toBeFalse();
        expect($cache->load('test_rule_unrelated_block'))->toBe('cached');
    });
});

describe('widget instance', function () {
    beforeEach(function () {
        Mage::app()->getCache()->cleanType('block_html');
        Mage::app()->getCache()->cleanType('layout');
        $this->instance = Mage::getModel('widget/widget_instance');
    });

    it('cleans its related cache types instead of flagging them', function () {
        $cache = Mage::app()->getCache();
        $cache->save('cached', 'test_widget_block_html', [Mage_Core_Block_Abstract::CACHE_GROUP]);
        $cache->save('cached', 'test_widget_layout', [Mage_Core_Model_Layout_Update::LAYOUT_GENERAL_CACHE_TAG]);

        callProtected($this->instance, '_cleanCache');

        expect($cache->load('test_widget_block_html'))->toBeFalse();
        expect($cache->load('test_widget_layout'))->toBeFalse();
        expect(cacheTypeIsFlaggedInvalid(Mage_Core_Block_Abstract::CACHE_GROUP))->toBeFalse();
        expect(cacheTypeIsFlaggedInvalid('layout'))->toBeFalse();
    });
});

describe('RSS links config save', function () {
    beforeEach(function () {
        Mage::app()->getCache()->cleanType('block_html');
    });

    it('cleans block html instead of flagging it', function () {
        $cache = Mage::app()->getCache();
        $cache->save('cached', 'test_rss_block_html', [Mage_Core_Block_Abstract::CACHE_GROUP]);

        Mage::getModel('rss/system_config_backend_links')
            ->setPath('rss/config/active')
            ->setValue('1')
            ->setOldValue('0')
            ->save();

        expect($cache->load('test_rss_block_html'))->toBeFalse();
        expect(cacheTypeIsFlaggedInvalid(Mage_Core_Block_Abstract::CACHE_GROUP))->toBeFalse();
    });
});
