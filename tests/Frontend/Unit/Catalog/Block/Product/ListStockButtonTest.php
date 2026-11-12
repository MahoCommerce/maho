<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoFrontendTestCase::class);

$configPath = 'catalog/frontend/enable_addtocart_in_product_listings';
$originalConfig = null;

beforeEach(function () use ($configPath, &$originalConfig): void {
    $store = Mage::app()->getStore();
    $originalConfig = $store->getConfig($configPath);
    $store->setConfig($configPath, '1');
});

afterEach(function () use ($configPath, &$originalConfig): void {
    Mage::app()->getStore()->setConfig($configPath, $originalConfig);
});

$render = static function (string $mode, bool $isSalable, string $template): string {
    $stockItem = Mage::getModel('cataloginventory/stock_item')->setIsInStock(true);
    $product = Mage::getModel('catalog/product')
        ->setId(1)
        ->setTypeId('bundle')
        ->setStoreId(Mage::app()->getStore()->getId())
        ->setName('Bundle')
        ->setUrlKey('bundle')
        ->setPrice(10)
        ->setStockItem($stockItem)
        ->setIsSalable($isSalable);
    $collection = new Maho\Data\Collection();
    $collection->addItem($product);

    $layout = Mage::app()->getLayout();
    $toolbar = $layout->createBlock('catalog/product_list_toolbar')->setCurrentMode($mode);
    $block = $layout->createBlock('catalog/product_list')
        ->setTemplate($template)
        ->setCollection($collection);
    $block->setChild('toolbar', $toolbar);

    return $block->toHtml();
};

it('shows Out of stock for a composite product that the stock index marks as not salable', function (string $mode) use ($render) {
    $html = $render($mode, false, 'catalog/product/list.phtml');

    expect($html)->toContain('out-of-stock')
        ->and($html)->not->toContain('View Details');
})->with(['grid', 'list']);

it('shows View Details for a composite product that is salable', function (string $mode) use ($render) {
    $html = $render($mode, true, 'catalog/product/list.phtml');

    expect($html)->toContain('View Details')
        ->and($html)->not->toContain('out-of-stock');
})->with(['grid', 'list']);
