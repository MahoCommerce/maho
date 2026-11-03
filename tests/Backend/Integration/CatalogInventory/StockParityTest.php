<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use ApiPlatform\Metadata\Put;
use Mage\CatalogInventory\Api\StockUpdateProcessor;
use Tests\Helpers\AdminApiParity;

uses(Tests\MahoBackendTestCase::class);

/**
 * The admin product page, the admin mass update and the stock API change the stock of a product through
 * Mage_CatalogInventory_Service_Stock and the stock item model (#1348). Each test gives the same input to the
 * transports and compares the outcome.
 */

function stockParityProduct(string $sku, float $qty = 10): Mage_Catalog_Model_Product
{
    /** @var Mage_Catalog_Service_Product $service */
    $service = Mage::getService('catalog/product');
    $product = $service->newProduct()
        ->setSku($sku)
        ->setName('Pest Stock Parity')
        ->setPrice(10)
        ->setStatus(Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
        ->setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH)
        ->setTaxClassId(0)
        ->setStockData(['qty' => $qty, 'is_in_stock' => 1]);
    $service->setWebsiteIds($product, [1]);
    return $service->save($product);
}

function stockParityItem(Mage_Catalog_Model_Product $product): Mage_CatalogInventory_Model_Stock_Item
{
    return Mage::getModel('cataloginventory/stock_item')->loadByProduct((int) $product->getId());
}

/**
 * Post the stock fields $stockData of $product to the save action of the admin product page.
 *
 * @param array<string, mixed> $stockData
 * @return array{success: list<string>, errors: list<string>}
 */
function stockViaAdmin(Mage_Catalog_Model_Product $product, array $stockData): array
{
    Mage::unregister('product');
    Mage::unregister('current_product');
    // The form posts the flag of manage stock when its checkbox is set
    $stockData += isset($stockData['manage_stock']) ? [] : ['use_config_manage_stock' => 1];
    return AdminApiParity::admin(Mage_Adminhtml_Catalog_ProductController::class, 'save', [
        'id' => (int) $product->getId(),
        'product' => ['name' => 'Pest Stock Parity', 'website_ids' => [1], 'stock_data' => $stockData],
    ]);
}

/**
 * @param array<string, mixed> $body the JSON body of PUT /inventory, or of PUT /inventory/bulk with $bulk
 * @return array{status: int, result: mixed, errors: list<string>, fields: list<string>}
 */
function stockViaApi(array $body, bool $bulk = false): array
{
    $request = AdminApiParity::jsonRequest('PUT', '/api/rest/v2/inventory' . ($bulk ? '/bulk' : ''), $body);
    return AdminApiParity::api($request, fn() => new StockUpdateProcessor(AdminApiParity::adminSecurity())
        ->process(null, new Put(name: $bulk ? 'bulk' : 'single'), [], ['request' => $request]));
}

function stockParityCleanup(): void
{
    Mage::register('isSecureArea', true, true);
    foreach (Mage::getModel('catalog/product')->getCollection()->addAttributeToFilter('sku', ['like' => 'PEST-STOCK-PARITY-%']) as $product) {
        Mage::getModel('catalog/product')->load($product->getId())->delete();
    }
    Mage::unregister('isSecureArea');
}

beforeEach(function () {
    stockParityCleanup();
});

afterEach(function () {
    stockParityCleanup();
    Mage::unregister('product');
    Mage::unregister('current_product');
});

it('turns off the configuration of manage stock when a value comes without its flag, on both transports', function () {
    $forAdmin = stockParityProduct('PEST-STOCK-PARITY-MANAGE-ADMIN');
    $forApi = stockParityProduct('PEST-STOCK-PARITY-MANAGE-API');

    $admin = stockViaAdmin($forAdmin, ['qty' => 10, 'is_in_stock' => 1, 'manage_stock' => 0]);
    $api = stockViaApi(['sku' => 'PEST-STOCK-PARITY-MANAGE-API', 'manageStock' => false]);

    expect($admin['errors'])->toBe([])
        ->and($api['status'])->toBe(200)
        ->and((int) stockParityItem($forAdmin)->getData('use_config_manage_stock'))->toBe(0)
        ->and((int) stockParityItem($forApi)->getData('use_config_manage_stock'))->toBe(0)
        ->and(stockParityItem($forAdmin)->getManageStock())->toBeFalse()
        ->and(stockParityItem($forApi)->getManageStock())->toBeFalse();
});

it('refuses a negative quantity on both transports', function () {
    $forAdmin = stockParityProduct('PEST-STOCK-PARITY-NEGATIVE-ADMIN');
    stockParityProduct('PEST-STOCK-PARITY-NEGATIVE-API');

    $admin = stockViaAdmin($forAdmin, ['qty' => -1, 'is_in_stock' => 1]);
    $api = stockViaApi(['sku' => 'PEST-STOCK-PARITY-NEGATIVE-API', 'qty' => -1]);

    expect($admin['errors'])->toBe(['Quantity cannot be negative'])
        ->and($api['status'])->toBe(422)
        ->and($api['fields'])->toBe(['qty'])
        ->and((float) stockParityItem($forAdmin)->getQty())->toBe(10.0);
});

it('saves a product whose stored quantity is negative after orders with backorders, on both transports', function () {
    $forAdmin = stockParityProduct('PEST-STOCK-PARITY-BACKORDER-ADMIN');
    $forApi = stockParityProduct('PEST-STOCK-PARITY-BACKORDER-API');
    // An order with backorders takes the stock below 0 without a save of the product
    $write = Mage::getSingleton('core/resource')->getConnection('core_write');
    foreach ([$forAdmin, $forApi] as $product) {
        $write->update(
            Mage::getSingleton('core/resource')->getTableName('cataloginventory/stock_item'),
            ['qty' => -3, 'backorders' => Mage_CatalogInventory_Model_Stock::BACKORDERS_YES_NONOTIFY, 'use_config_backorders' => 0],
            ['product_id = ?' => (int) $product->getId()],
        );
    }

    $admin = stockViaAdmin($forAdmin, ['qty' => -3, 'is_in_stock' => 1, 'backorders' => 1, 'use_config_backorders' => 0]);
    $api = stockViaApi(['sku' => 'PEST-STOCK-PARITY-BACKORDER-API', 'qty' => -3, 'notifyStockQty' => 2]);

    expect($admin['errors'])->toBe([])
        ->and($api['status'])->toBe(200)
        ->and((float) stockParityItem($forAdmin)->getQty())->toBe(-3.0)
        ->and((float) stockParityItem($forApi)->getQty())->toBe(-3.0);
});

it('refuses a quantity that the column cannot hold on both transports', function () {
    $forAdmin = stockParityProduct('PEST-STOCK-PARITY-MAX-ADMIN');
    stockParityProduct('PEST-STOCK-PARITY-MAX-API');

    $admin = stockViaAdmin($forAdmin, ['qty' => 1e9, 'is_in_stock' => 1]);
    $api = stockViaApi(['sku' => 'PEST-STOCK-PARITY-MAX-API', 'qty' => 1e9]);

    expect($api['status'])->toBe(422)
        ->and($api['fields'])->toBe(['qty'])
        ->and($admin['errors'])->toBe($api['errors']);
});

it('sets the low stock date when the quantity falls below the notify quantity, on both transports', function () {
    $forAdmin = stockParityProduct('PEST-STOCK-PARITY-LOW-ADMIN');
    $forApi = stockParityProduct('PEST-STOCK-PARITY-LOW-API');

    $admin = stockViaAdmin($forAdmin, ['qty' => 1, 'is_in_stock' => 1, 'notify_stock_qty' => 5]);
    $api = stockViaApi(['sku' => 'PEST-STOCK-PARITY-LOW-API', 'qty' => 1, 'notifyStockQty' => 5]);

    expect($admin['errors'])->toBe([])
        ->and($api['status'])->toBe(200)
        ->and(stockParityItem($forAdmin)->getLowStockDate())->not->toBeNull()
        ->and(stockParityItem($forApi)->getLowStockDate())->not->toBeNull()
        ->and((int) stockParityItem($forApi)->getData('use_config_notify_stock_qty'))->toBe(0);
});

it('puts a product out of stock at the minimum quantity on both transports', function () {
    $forAdmin = stockParityProduct('PEST-STOCK-PARITY-MIN-ADMIN');
    $forApi = stockParityProduct('PEST-STOCK-PARITY-MIN-API');

    $admin = stockViaAdmin($forAdmin, ['qty' => 2, 'is_in_stock' => 1, 'min_qty' => 3]);
    $api = stockViaApi(['sku' => 'PEST-STOCK-PARITY-MIN-API', 'qty' => 2, 'isInStock' => true, 'minQty' => 3]);

    expect($admin['errors'])->toBe([])
        ->and($api['status'])->toBe(200)
        ->and($api['result']->isInStock)->toBeFalse()
        ->and((bool) stockParityItem($forAdmin)->getData('is_in_stock'))->toBeFalse()
        ->and((bool) stockParityItem($forApi)->getData('is_in_stock'))->toBeFalse();
});

describe('the bulk stock API', function () {
    it('saves nothing when one item is invalid', function () {
        $first = stockParityProduct('PEST-STOCK-PARITY-BULK-1');
        stockParityProduct('PEST-STOCK-PARITY-BULK-2');

        $api = stockViaApi(['items' => [
            ['sku' => 'PEST-STOCK-PARITY-BULK-1', 'qty' => 3],
            ['sku' => 'PEST-STOCK-PARITY-BULK-2', 'backorders' => 7],
        ]], bulk: true);

        expect($api['status'])->toBe(422)
            ->and($api['fields'])->toBe(['backorders'])
            ->and((float) stockParityItem($first)->getQty())->toBe(10.0);
    });
});
