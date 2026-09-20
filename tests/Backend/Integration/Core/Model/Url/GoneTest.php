<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * Issue #1305: the URLs of a deleted product must return 410 Gone. The product delete cascade
 * removes its core_url_rewrite rows, so the paths are copied into core_url_gone first.
 */
function urlGoneTable(): string
{
    return Mage::getSingleton('core/resource')->getTableName('core/url_gone');
}

function urlGoneWrite(): \Maho\Db\Adapter\AdapterInterface
{
    return Mage::getSingleton('core/resource')->getConnection('core_write');
}

function urlGoneRows(string $requestPath, ?int $storeId = null): array
{
    $select = urlGoneWrite()->select()->from(urlGoneTable())->where('request_path = ?', $requestPath);
    if ($storeId !== null) {
        $select->where('store_id = ?', $storeId);
    }
    return urlGoneWrite()->fetchAll($select);
}

function urlGoneInsert(string $requestPath, int $storeId, string $deletedAt): void
{
    urlGoneWrite()->insert(urlGoneTable(), [
        'store_id' => $storeId,
        'request_path' => $requestPath,
        'entity_type' => Mage_Core_Model_Url_Gone::ENTITY_TYPE_PRODUCT,
        'deleted_at' => $deletedAt,
    ]);
}

function urlGoneCreateProduct(string $urlKey): Mage_Catalog_Model_Product
{
    $product = Mage::getModel('catalog/product');
    $product->setStoreId(Mage_Catalog_Model_Abstract::DEFAULT_STORE_ID);
    $product->setName($urlKey);
    $product->setSku($urlKey);
    $product->setUrlKey($urlKey);
    $product->setPrice(10.00);
    $product->setStatus(Mage_Catalog_Model_Product_Status::STATUS_ENABLED);
    $product->setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH);
    $product->setTypeId(Mage_Catalog_Model_Product_Type::TYPE_SIMPLE);
    $product->setAttributeSetId(4);
    $product->setWebsiteIds([1]);
    $product->save();
    Mage::getSingleton('catalog/url')->refreshProductRewrite((int) $product->getId());
    return $product;
}

function urlGoneRewriteRows(int $productId): array
{
    $table = Mage::getSingleton('core/resource')->getTableName('core/url_rewrite');
    $select = urlGoneWrite()->select()->from($table, ['request_path', 'store_id'])->where('product_id = ?', $productId);
    return urlGoneWrite()->fetchAll($select);
}

describe('Gone URL registry', function () {
    beforeEach(function () {
        Mage::app()->setCurrentStore(Mage::app()->getDefaultStoreView());
        $this->storeId = (int) Mage::app()->getStore()->getId();
        $this->paths = [];
        $this->products = [];
    });

    afterEach(function () {
        foreach ($this->products as $product) {
            $product->delete();
        }
        foreach ($this->paths as $path) {
            urlGoneWrite()->delete(urlGoneTable(), ['request_path = ?' => $path]);
        }
    });

    it('records every rewrite path of a product when the product is deleted', function () {
        $urlKey = 'gone-product-' . uniqid();
        $product = urlGoneCreateProduct($urlKey);

        $rewrites = urlGoneRewriteRows((int) $product->getId());
        expect($rewrites)->not->toBeEmpty();
        foreach ($rewrites as $rewrite) {
            $this->paths[] = $rewrite['request_path'];
        }

        $product->delete();

        expect(urlGoneRewriteRows((int) $product->getId()))->toBe([]);
        foreach ($rewrites as $rewrite) {
            $rows = urlGoneRows($rewrite['request_path'], (int) $rewrite['store_id']);
            expect($rows)->toHaveCount(1);
            expect((int) $rows[0]['store_id'])->toBe((int) $rewrite['store_id']);
            expect($rows[0]['entity_type'])->toBe(Mage_Core_Model_Url_Gone::ENTITY_TYPE_PRODUCT);
            expect($rows[0]['deleted_at'])->not->toBeEmpty();
        }
    });

    it('is no longer gone when a new product claims the same url key', function () {
        $urlKey = 'reused-product-' . uniqid();
        $first = urlGoneCreateProduct($urlKey);
        $rewrite = urlGoneRewriteRows((int) $first->getId())[0];
        $requestPath = $rewrite['request_path'];
        $storeId = (int) $rewrite['store_id'];
        $this->paths[] = $requestPath;
        $resource = Mage::getResourceSingleton('core/url_gone');

        $first->delete();
        expect(urlGoneRows($requestPath, $storeId))->toHaveCount(1);
        expect($resource->isGone([$requestPath], $storeId))->toBeTrue();

        $second = urlGoneCreateProduct($urlKey);
        $this->products[] = $second;

        expect(urlGoneRewriteRows((int) $second->getId()))->not->toBeEmpty();
        expect($resource->isGone([$requestPath], $storeId))->toBeFalse();
    });

    it('is no longer gone when a custom rewrite claims the path', function () {
        $requestPath = 'custom-claim-' . uniqid() . '.html';
        $this->paths[] = $requestPath;
        urlGoneInsert($requestPath, $this->storeId, '2026-01-01 00:00:00');

        $rewrite = Mage::getModel('core/url_rewrite');
        $rewrite->setStoreId($this->storeId)
            ->setIdPath('gone_test_' . uniqid())
            ->setRequestPath($requestPath)
            ->setTargetPath('cms/index/index')
            ->setIsSystem(0)
            ->save();

        $resource = Mage::getResourceSingleton('core/url_gone');
        try {
            expect($resource->isGone([$requestPath], $this->storeId))->toBeFalse();
        } finally {
            $rewrite->delete();
        }
        expect($resource->isGone([$requestPath], $this->storeId))->toBeTrue();
    });

    it('records paths for products deleted by raw SQL, as the import module does', function () {
        $urlKey = 'imported-product-' . uniqid();
        $product = urlGoneCreateProduct($urlKey);
        $this->products[] = $product;
        $rewrite = urlGoneRewriteRows((int) $product->getId())[0];
        $this->paths[] = $rewrite['request_path'];

        $count = Mage::getResourceSingleton('catalog/url')->markProductRewritesGone([(int) $product->getId()]);

        expect($count)->toBeGreaterThan(0);
        expect(urlGoneRows($rewrite['request_path'], (int) $rewrite['store_id']))->toHaveCount(1);
    });

    it('matches gone paths with either trailing slash state and ignoring case', function () {
        $requestPath = 'mixed-case-' . uniqid() . '.html';
        $this->paths[] = $requestPath;
        urlGoneInsert($requestPath, $this->storeId, '2026-01-01 00:00:00');

        $resource = Mage::getResourceSingleton('core/url_gone');
        $helper = Mage::helper('core/url');

        expect($resource->isGone($helper->getRequestPathCandidates('/' . $requestPath), $this->storeId))->toBeTrue();
        expect($resource->isGone($helper->getRequestPathCandidates('/' . $requestPath . '/'), $this->storeId))->toBeTrue();
        expect($resource->isGone($helper->getRequestPathCandidates('/' . strtoupper($requestPath)), $this->storeId))->toBeTrue();
        expect($resource->isGone(['no-such-path-' . uniqid() . '.html'], $this->storeId))->toBeFalse();
        expect($resource->isGone([], $this->storeId))->toBeFalse();
    });

    it('purges records older than the configured number of days', function () {
        $old = 'old-gone-' . uniqid() . '.html';
        $recent = 'recent-gone-' . uniqid() . '.html';
        $this->paths[] = $old;
        $this->paths[] = $recent;
        urlGoneInsert($old, $this->storeId, '2020-01-01 00:00:00');
        urlGoneInsert($recent, $this->storeId, Mage::app()->getLocale()->formatDateForDb('now'));

        Mage::app()->getStore()->setConfig(Mage_Core_Model_Url_Gone::XML_PATH_PURGE_AFTER_DAYS, '30');
        Mage::getModel('core/url_gone')->purgeOld();

        expect(urlGoneRows($old))->toBe([]);
        expect(urlGoneRows($recent))->toHaveCount(1);
    });

    it('purges records that a live rewrite claims, even when age purging is disabled', function () {
        $claimed = 'claimed-gone-' . uniqid() . '.html';
        $unclaimed = 'unclaimed-gone-' . uniqid() . '.html';
        $this->paths[] = $claimed;
        $this->paths[] = $unclaimed;
        urlGoneInsert($claimed, $this->storeId, Mage::app()->getLocale()->formatDateForDb('now'));
        urlGoneInsert($unclaimed, $this->storeId, Mage::app()->getLocale()->formatDateForDb('now'));

        $rewrite = Mage::getModel('core/url_rewrite');
        $rewrite->setStoreId($this->storeId)
            ->setIdPath('gone_purge_' . uniqid())
            ->setRequestPath($claimed)
            ->setTargetPath('cms/index/index')
            ->setIsSystem(0)
            ->save();

        try {
            Mage::app()->getStore()->setConfig(Mage_Core_Model_Url_Gone::XML_PATH_PURGE_AFTER_DAYS, '0');
            Mage::getModel('core/url_gone')->purgeOld();
        } finally {
            $rewrite->delete();
        }

        expect(urlGoneRows($claimed))->toBe([]);
        expect(urlGoneRows($unclaimed))->toHaveCount(1);
    });

    it('keeps every record when purging is disabled', function () {
        $old = 'kept-gone-' . uniqid() . '.html';
        $this->paths[] = $old;
        urlGoneInsert($old, $this->storeId, '2020-01-01 00:00:00');

        Mage::app()->getStore()->setConfig(Mage_Core_Model_Url_Gone::XML_PATH_PURGE_AFTER_DAYS, '0');
        Mage::getModel('core/url_gone')->purgeOld();

        expect(urlGoneRows($old))->toHaveCount(1);
    });
});
