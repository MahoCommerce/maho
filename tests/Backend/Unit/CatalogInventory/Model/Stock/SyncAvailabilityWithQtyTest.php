<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_CatalogInventory
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

uses(Tests\MahoBackendTestCase::class);

const SYNC_AVAIL_PATH = 'cataloginventory/options/sync_stock_availability_with_qty';

/**
 * @param array<string, mixed> $overrides
 */
function makeStockItemForSyncTest(array $overrides = []): Mage_CatalogInventory_Model_Stock_Item
{
    $item = Mage::getModel('cataloginventory/stock_item');
    $item->addData([
        'type_id'                => 'simple',
        'qty'                    => 5,
        'min_qty'                => 0,
        'use_config_min_qty'     => 0,
        'backorders'             => Mage_CatalogInventory_Model_Stock::BACKORDERS_NO,
        'use_config_backorders'  => 0,
        'manage_stock'           => 1,
        'use_config_manage_stock' => 0,
        'is_in_stock'            => 1,
    ]);
    $item->addData($overrides);
    return $item;
}

function invokeStockItemBeforeSave(Mage_CatalogInventory_Model_Stock_Item $item): void
{
    $method = new ReflectionMethod($item, '_beforeSave');
    $method->setAccessible(true);
    $method->invoke($item);
}

describe('Stock Item _beforeSave: existing qty → out-of-stock behavior is unchanged', function () {
    it('flips to out of stock when qty drops to 0 (flag irrelevant)', function () {
        Mage::app()->getStore()->setConfig(SYNC_AVAIL_PATH, 0);

        $item = makeStockItemForSyncTest(['qty' => 0, 'is_in_stock' => 1]);
        invokeStockItemBeforeSave($item);

        expect((int) $item->getData('is_in_stock'))->toBe(0)
            ->and((int) $item->getStockStatusChangedAutomatically())->toBe(1);
    });

    it('flips to out of stock when qty drops at or below min_qty', function () {
        $item = makeStockItemForSyncTest(['qty' => 2, 'min_qty' => 5, 'is_in_stock' => 1]);
        invokeStockItemBeforeSave($item);

        expect((int) $item->getData('is_in_stock'))->toBe(0);
    });

    it('does not flip to out of stock when backorders are enabled even with qty 0', function () {
        $item = makeStockItemForSyncTest([
            'qty'        => 0,
            'is_in_stock' => 1,
            'backorders' => Mage_CatalogInventory_Model_Stock::BACKORDERS_YES_NONOTIFY,
        ]);
        invokeStockItemBeforeSave($item);

        expect((int) $item->getData('is_in_stock'))->toBe(1);
    });
});

describe('Stock Item _beforeSave: qty → in-stock sync (gated by config)', function () {
    it('flips back to in stock when flag is on and qty rises above min_qty', function () {
        Mage::app()->getStore()->setConfig(SYNC_AVAIL_PATH, 1);

        $item = makeStockItemForSyncTest([
            'qty'                         => 10,
            'is_in_stock'                 => 0,
            'stock_status_changed_auto'   => 1,
        ]);
        invokeStockItemBeforeSave($item);

        expect((int) $item->getData('is_in_stock'))->toBe(1)
            ->and((int) $item->getStockStatusChangedAutomatically())->toBe(0);
    });

    it('does NOT flip back when flag is off even if qty is positive', function () {
        Mage::app()->getStore()->setConfig(SYNC_AVAIL_PATH, 0);

        $item = makeStockItemForSyncTest(['qty' => 10, 'is_in_stock' => 0]);
        invokeStockItemBeforeSave($item);

        expect((int) $item->getData('is_in_stock'))->toBe(0);
    });

    it('does not flip back when qty is still at or below min_qty', function () {
        Mage::app()->getStore()->setConfig(SYNC_AVAIL_PATH, 1);

        $item = makeStockItemForSyncTest(['qty' => 3, 'min_qty' => 5, 'is_in_stock' => 0]);
        invokeStockItemBeforeSave($item);

        expect((int) $item->getData('is_in_stock'))->toBe(0);
    });

    it('leaves already-in-stock items alone with flag on', function () {
        Mage::app()->getStore()->setConfig(SYNC_AVAIL_PATH, 1);

        $item = makeStockItemForSyncTest(['qty' => 10, 'is_in_stock' => 1]);
        invokeStockItemBeforeSave($item);

        expect((int) $item->getData('is_in_stock'))->toBe(1);
    });

    it('with flag on, qty goes 5 → 0 → 10 round-trips is_in_stock', function () {
        Mage::app()->getStore()->setConfig(SYNC_AVAIL_PATH, 1);

        // Step 1: qty drops to 0, auto-flip out
        $item = makeStockItemForSyncTest(['qty' => 0, 'is_in_stock' => 1]);
        invokeStockItemBeforeSave($item);
        expect((int) $item->getData('is_in_stock'))->toBe(0)
            ->and((int) $item->getStockStatusChangedAutomatically())->toBe(1);

        // Step 2: qty rises to 10, auto-flip back in
        $item->setQty(10);
        // clear transient flag so the next save re-evaluates cleanly
        $item->unsetData('stock_status_changed_automatically_flag');
        invokeStockItemBeforeSave($item);
        expect((int) $item->getData('is_in_stock'))->toBe(1)
            ->and((int) $item->getStockStatusChangedAutomatically())->toBe(0);
    });
});

describe('Stock Item _beforeSave: non-qty product types are untouched', function () {
    it('does not auto-flip for grouped products even when flag is on', function () {
        Mage::app()->getStore()->setConfig(SYNC_AVAIL_PATH, 1);

        $item = makeStockItemForSyncTest([
            'type_id'     => 'grouped',
            'qty'         => 10,
            'is_in_stock' => 0,
        ]);
        invokeStockItemBeforeSave($item);

        expect((int) $item->getData('is_in_stock'))->toBe(0);
    });
});
