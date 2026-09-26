<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho
 */

declare(strict_types=1);

use Maho\Import\SampleData\Installer;
use Maho\Import\SampleData\Package;
use Tests\Helpers\ReportFixture;

uses(Tests\MahoBackendTestCase::class);

function fixturePackDir(): string
{
    return dirname(__DIR__, 3) . '/fixtures/sample-pack';
}

function fixtureCleanup(): void
{
    $orders = Mage::getResourceModel('sales/order_collection')->addFieldToFilter('ext_order_id', ['like' => 'fixture-%']);
    $quoteIds = array_filter(array_map(intval(...), $orders->getColumnValues('quote_id')));
    ReportFixture::deleteOrders(array_map(intval(...), $orders->getColumnValues('entity_id')));
    foreach ($quoteIds as $quoteId) {
        Mage::getModel('sales/quote')->loadByIdWithoutStore($quoteId)->delete();
    }
    foreach (Mage::getResourceModel('catalogsearch/query_collection')->addFieldToFilter('query_text', 'fixture chair') as $query) {
        $query->delete();
    }
    $productIds = Mage::getResourceModel('catalog/product_collection')->addFieldToFilter('sku', ['like' => 'FIX-%'])->getAllIds();
    if ($productIds !== []) {
        Mage::getSingleton('core/resource')->getConnection('core_write')->delete(
            Mage::getSingleton('core/resource')->getTableName('reports/event'),
            ['object_id IN (?)' => $productIds, 'event_type_id = ?' => Mage_Reports_Model_Event::EVENT_PRODUCT_VIEW],
        );
    }
    foreach (Mage::getResourceModel('catalog/product_collection')->addFieldToFilter('sku', ['like' => 'FIX-%']) as $product) {
        Mage::getModel('catalog/product')->load($product->getId())->delete();
    }
    foreach (Mage::getResourceModel('customer/customer_collection')->addFieldToFilter('email', 'fixture.alpha@example.com') as $customer) {
        $customer->delete();
    }
    if (Mage::helper('core')->isModuleEnabled('Maho_Blog')) {
        foreach (Mage::getModel('blog/post')->getCollection()->addAttributeToFilter('url_key', 'fixture-post') as $post) {
            $post->delete();
        }
    }
    foreach (Mage::getModel('cms/block')->getCollection()->addFieldToFilter('identifier', 'fixture-landing') as $block) {
        $block->delete();
    }
    $config = Mage::getModel('core/config');
    foreach (['fixalpha', 'fixbeta'] as $code) {
        $website = Mage::getModel('core/website')->load($code, 'code');
        if ($website->getId()) {
            foreach (Mage::getModel('cms/page')->getCollection()->addStoreFilter((int) Mage::app()->getStore($code)->getId(), false) as $page) {
                $page->delete();
            }
            $config->deleteConfig('general/store_information/name', 'websites', (int) $website->getId());
            $config->deleteConfig('web/default/cms_home_page', 'stores', (int) Mage::app()->getStore($code)->getId());
        }
        deletePriceWebsite($code);
    }
    foreach (['Fixture Alpha', 'Fixture Beta'] as $name) {
        $root = Mage::getResourceModel('catalog/category_collection')->addAttributeToFilter('level', 1)->addAttributeToFilter('name', $name)->getFirstItem();
        if ($root->getId()) {
            Mage::getModel('catalog/category')->load($root->getId())->delete();
        }
    }
    $config->deleteConfig('configswatches/general/enabled', 'default', 0);
    $attribute = Mage::getModel('catalog/resource_eav_attribute')->loadByCode('catalog_product', 'fix_finish');
    if ($attribute->getId()) {
        $attribute->delete();
    }
    $entityTypeId = (int) Mage::getSingleton('eav/config')->getEntityType('catalog_product')->getId();
    $set = Mage::getResourceModel('eav/entity_attribute_set_collection')->setEntityTypeFilter($entityTypeId)->addFieldToFilter('attribute_set_name', 'Fixture Alpha')->getFirstItem();
    if ($set->getId()) {
        Mage::getModel('eav/entity_attribute_set')->load($set->getId())->delete();
    }
    foreach (['catalog/product/c/h/chair.webp', 'catalog/category/chairs.webp', 'wysiwyg/fixture/hero.webp', 'blog/fixture/post.webp'] as $file) {
        @unlink(Mage::getBaseDir('media') . '/' . $file);
    }
    Mage::app()->getCache()->cleanType('config');
    Mage::app()->reinitStores();
}

function fixtureViews(int $productId, int $storeId): int
{
    $adapter = Mage::getSingleton('core/resource')->getConnection('core_read');
    return (int) $adapter->fetchOne($adapter->select()
        ->from(Mage::getSingleton('core/resource')->getTableName('reports/event'), [new Maho\Db\Expr('COUNT(*)')])
        ->where('event_type_id = ?', Mage_Reports_Model_Event::EVENT_PRODUCT_VIEW)
        ->where('object_id = ?', $productId)
        ->where('store_id = ?', $storeId));
}

beforeEach(fn() => fixtureCleanup());
afterEach(fn() => fixtureCleanup());

it('installs the fixture package end to end and reruns without duplicates', function (): void {
    $package = Package::fromPath(fixturePackDir());
    expect($package->packs())->toBe(['alpha', 'beta']);

    $result = (new Installer())->install($package, null, false);
    expect($result->created)->toBeGreaterThan(10);

    $alpha = Mage::app()->getStore('fixalpha');
    expect($alpha->getWebsite()->getName())->toBe('Fixture Alpha');
    expect(Mage::getStoreConfig('general/store_information/name', $alpha))->toBe('Fixture Alpha');
    expect(Mage::getStoreConfig('web/default/cms_home_page', $alpha))->toBe('home');
    expect(Mage::getStoreConfig('web/default/cms_home_page', Mage::app()->getStore('fixbeta')))->toBe('home');

    $root = Mage::getModel('catalog/category')->load($alpha->getRootCategoryId());
    expect($root->getName())->toBe('Fixture Alpha');
    $chairs = Mage::getResourceModel('catalog/category_collection')->setStoreId(0)->addAttributeToSelect(['name', 'landing_page', 'image'])
        ->addAttributeToFilter('url_key', 'chairs')->addFieldToFilter('parent_id', $root->getId())->getFirstItem();
    expect($chairs->getName())->toBe('Chairs');
    expect((int) $chairs->getLandingPage())->toBe((int) Mage::getModel('cms/block')->load('fixture-landing', 'identifier')->getId());
    expect(is_file(Mage::getBaseDir('media') . '/catalog/category/chairs.webp'))->toBeTrue();

    $chair = Mage::getModel('catalog/product')->load(Mage::getModel('catalog/product')->getIdBySku('FIX-CHAIR'));
    expect($chair->getTypeId())->toBe('configurable');
    expect(array_map('intval', $chair->getWebsiteIds()))->toBe([(int) $alpha->getWebsiteId()]);
    expect($chair->getAttributeSetId())->toBe(Mage::getModel('eav/entity_attribute_set')->load('Fixture Alpha', 'attribute_set_name')->getId());
    $children = $chair->getTypeInstance(true)->getUsedProductIds($chair);
    expect(count($children))->toBe(2);
    expect($chair->getImage())->toBe('/c/h/chair.webp');
    $finish = Mage::getModel('catalog/resource_eav_attribute')->loadByCode('catalog_product', 'fix_finish');
    expect(array_column($finish->getSource()->getAllOptions(false), 'label'))->toBe(['Matte', 'Gloss']);
    expect(Mage::getModel('catalog/product')->load(Mage::getModel('catalog/product')->getIdBySku('FIX-CHAIR-MATTE'))->getAttributeText('fix_finish'))->toBe('Matte');

    expect(Mage::getModel('review/review')->getCollection()->addFieldToFilter('nickname', 'Fixture Fan')->count())->toBe(1);
    expect(Mage::getModel('cms/page')->setStoreId((int) $alpha->getId())->load('about', 'identifier')->getContent())->toContain('About page body');
    expect(Mage::getModel('customer/customer')->setWebsiteId($alpha->getWebsiteId())->loadByEmail('fixture.alpha@example.com')->getFirstname())->toBe('Fixture');
    expect(is_file(Mage::getBaseDir('media') . '/wysiwyg/fixture/hero.webp'))->toBeTrue();
    if (Mage::helper('core')->isModuleEnabled('Maho_Blog')) {
        expect(Mage::getModel('blog/post')->getPostIdByUrlKey('fixture-post', (int) $alpha->getId()))->not->toBeNull();
    }

    $order = Mage::getModel('sales/order')->load(
        Mage::getResourceModel('sales/order_collection')->addFieldToFilter('ext_order_id', 'fixture-0001')->getFirstItem()->getId(),
    );
    expect($order->getState())->toBe(Mage_Sales_Model_Order::STATE_COMPLETE);
    expect($order->getCustomerEmail())->toBe('fixture.alpha@example.com');
    expect((int) $order->getCustomerId())->toBeGreaterThan(0);
    expect(array_map(fn($item) => $item->getProductType(), $order->getAllVisibleItems()))->toContain('configurable');
    expect(fixtureViews((int) $chair->getId(), (int) $alpha->getId()))->toBe(5);
    expect((int) Mage::getResourceModel('catalogsearch/query_collection')->addFieldToFilter('query_text', 'fixture chair')->getFirstItem()->getPopularity())->toBe(4);

    $again = (new Installer())->install($package, null, false);
    expect($again->created)->toBeLessThan($result->created);
    expect(Mage::getResourceModel('catalog/product_collection')->addFieldToFilter('sku', ['like' => 'FIX-%'])->count())->toBe(4);
    expect(Mage::getResourceModel('core/website_collection')->addFieldToFilter('code', ['in' => ['fixalpha', 'fixbeta']])->count())->toBe(2);
    expect(Mage::getResourceModel('catalog/category_collection')->addAttributeToFilter('level', 1)->addAttributeToFilter('name', 'Fixture Alpha')->count())->toBe(1);
    expect(Mage::getModel('review/review')->getCollection()->addFieldToFilter('nickname', 'Fixture Fan')->count())->toBe(1);
    expect(Mage::getModel('cms/page')->getCollection()->addStoreFilter((int) $alpha->getId(), false)->addFieldToFilter('identifier', 'about')->count())->toBe(1);
    expect(count(Mage::getModel('catalog/product')->load($chair->getId())->getMediaGalleryImages()))->toBe(1);
    expect(Mage::getResourceModel('sales/order_collection')->addFieldToFilter('ext_order_id', ['like' => 'fixture-%'])->getSize())->toBe(2);
    expect(fixtureViews((int) $chair->getId(), (int) $alpha->getId()))->toBe(5);
    expect(Mage::getResourceModel('catalogsearch/query_collection')->addFieldToFilter('query_text', 'fixture chair')->count())->toBe(1);
});

it('refuses a folder without packs and an unknown pack name', function (): void {
    expect(fn() => Package::fromPath(sys_get_temp_dir()))->toThrow(\Maho\Exception::class, 'no packs/ folder');
    $package = Package::fromPath(fixturePackDir());
    expect(fn() => (new Installer())->install($package, ['gamma'], false))->toThrow(\Maho\Exception::class, "unknown pack 'gamma'");
});
