<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

function urlUnchangedCleanup(): void
{
    foreach (Mage::getResourceModel('catalog/product_collection')->addAttributeToFilter('sku', 'urlunchanged-product') as $product) {
        Mage::getModel('catalog/product')->load($product->getId())->delete();
    }
    $adapter = Mage::getSingleton('core/resource')->getConnection('core_write');
    $adapter->delete(Mage::getSingleton('core/resource')->getTableName('core/url_rewrite'), ['id_path = ?' => 'urlunchanged-older']);
}

/**
 * Run a full catalog_url reindex and return the INSERT and UPDATE statements on core_url_rewrite.
 * The statements come from the query log of the adapter, which the function sends to a Monolog test handler.
 *
 * @return list<string>
 */
function urlRewriteWritesOfFullReindex(): array
{
    $adapter = Mage::getSingleton('core/resource')->getConnection('core_write');
    $table = Mage::getSingleton('core/resource')->getTableName('core/url_rewrite');
    $logFile = 'url_rewrite_unchanged_test.log';
    $handler = new Monolog\Handler\TestHandler();

    $loggers = new ReflectionProperty(Mage_Core_Model_Logger::class, '_loggers');
    $loggers->setValue(null, [$logFile => new Monolog\Logger('test', [$handler])] + $loggers->getValue());
    $debugProperties = ['_debug' => true, '_logAllQueries' => true, '_debugFile' => $logFile];
    $previous = [];
    foreach ($debugProperties as $name => $value) {
        $property = new ReflectionProperty(Maho\Db\Adapter\AbstractPdoAdapter::class, $name);
        $previous[$name] = $property->getValue($adapter);
        $property->setValue($adapter, $value);
    }
    Mage::app()->getStore()->setConfig('dev/log/active', '1');
    Mage::app()->getStore()->setConfig('dev/log/min_level', '7');

    try {
        Mage::getModel('catalog/indexer_url')->reindexAll();
    } finally {
        foreach ($previous as $name => $value) {
            (new ReflectionProperty(Maho\Db\Adapter\AbstractPdoAdapter::class, $name))->setValue($adapter, $value);
        }
        $current = $loggers->getValue();
        unset($current[$logFile]);
        $loggers->setValue(null, $current);
    }

    $writes = [];
    foreach ($handler->getRecords() as $record) {
        $lines = explode("\n", trim($record->message));
        if (!str_contains($lines[0], '] QUERY (')) {
            continue;
        }
        $sql = trim($lines[1] ?? '');
        if (preg_match('/^(INSERT|UPDATE)\b/i', $sql) && str_contains($sql, $table)) {
            $writes[] = $sql;
        }
    }
    return $writes;
}

beforeEach(fn() => urlUnchangedCleanup());
afterEach(fn() => urlUnchangedCleanup());

it('writes no rewrite when a second full reindex finds nothing to change', function (): void {
    $adapter = Mage::getSingleton('core/resource')->getConnection('core_write');
    $table = Mage::getSingleton('core/resource')->getTableName('core/url_rewrite');
    $snapshot = fn(): array => $adapter->fetchAll($adapter->select()->from($table)->order('url_rewrite_id'));

    Mage::getModel('catalog/indexer_url')->reindexAll();
    $before = $snapshot();

    expect(urlRewriteWritesOfFullReindex())->toBe([])
        ->and($snapshot())->toBe($before);
});

it('updates the product rewrite and the redirects to it when the url key changes', function (): void {
    $storeId = (int) Mage::app()->getDefaultStoreView()->getId();
    $suffix = Mage::helper('catalog/product')->getProductUrlSuffix($storeId);
    $product = Mage::getModel('catalog/product')->setStoreId(0)->setName('Urlunchanged Product')
        ->setSku('urlunchanged-product')->setUrlKey('urlunchanged-product')->setPrice(10.00)
        ->setStatus(Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
        ->setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH)
        ->setTypeId(Mage_Catalog_Model_Product_Type::TYPE_SIMPLE)
        ->setAttributeSetId(4)->setWebsiteIds([1]);
    $product->save();
    $productId = (int) $product->getId();
    Mage::getSingleton('catalog/url')->setShouldSaveRewritesHistory(true)->refreshProductRewrite($productId);

    $rewrite = fn(): Mage_Core_Model_Url_Rewrite => Mage::getModel('core/url_rewrite')->setStoreId($storeId)->loadByIdPath("product/{$productId}");
    $oldPath = $rewrite()->getRequestPath();
    expect($oldPath)->toBe('urlunchanged-product' . $suffix);

    $adapter = Mage::getSingleton('core/resource')->getConnection('core_write');
    $adapter->insert(Mage::getSingleton('core/resource')->getTableName('core/url_rewrite'), [
        'store_id' => $storeId,
        'product_id' => $productId,
        'id_path' => 'urlunchanged-older',
        'request_path' => 'urlunchanged-older' . $suffix,
        'target_path' => $oldPath,
        'is_system' => 0,
        'options' => 'RP',
    ]);

    Mage::getResourceSingleton('catalog/url')->saveProductAttribute(
        new Maho\DataObject(['id' => $productId, 'store_id' => 0, 'url_key' => 'urlunchanged-product-new']),
        'url_key',
    );
    Mage::getSingleton('catalog/url')->setShouldSaveRewritesHistory(true);
    Mage::getModel('catalog/indexer_url')->reindexAll();

    $newPath = 'urlunchanged-product-new' . $suffix;
    $loadByRequestPath = fn(string $path): Mage_Core_Model_Url_Rewrite => Mage::getModel('core/url_rewrite')->setStoreId($storeId)->loadByRequestPath($path);
    expect($rewrite()->getRequestPath())->toBe($newPath)
        ->and($loadByRequestPath($oldPath)->getTargetPath())->toBe($newPath)
        ->and($loadByRequestPath($oldPath)->getOptions())->toBe('RP')
        ->and($loadByRequestPath('urlunchanged-older' . $suffix)->getTargetPath())->toBe($newPath);
});
