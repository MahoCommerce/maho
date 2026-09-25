<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho
 */

declare(strict_types=1);

use Maho\Import\Importer\ProductViews;
use Maho\Import\RowException;

uses(Tests\MahoBackendTestCase::class);

/**
 * @param list<list<string>> $rows
 */
function productViewsCsv(array $rows): string
{
    $path = tempnam(sys_get_temp_dir(), 'product_views') . '.csv';
    $handle = fopen($path, 'w');
    foreach ([['sku', 'store_code', 'views', 'days'], ...$rows] as $row) {
        fputcsv($handle, $row, escape: '\\');
    }
    fclose($handle);
    return $path;
}

/**
 * @return list<array{event_id: string, logged_at: string}>
 */
function productViewEvents(int $productId, int $storeId): array
{
    $adapter = Mage::getSingleton('core/resource')->getConnection('core_read');
    return $adapter->fetchAll($adapter->select()
        ->from(Mage::getSingleton('core/resource')->getTableName('reports/event'), ['event_id', 'logged_at'])
        ->where('event_type_id = ?', Mage_Reports_Model_Event::EVENT_PRODUCT_VIEW)
        ->where('object_id = ?', $productId)
        ->where('store_id = ?', $storeId));
}

it('adds the missing views of a product over the last days, and none on rerun', function (): void {
    $product = loadSimplePricedProduct();
    $before = productViewEvents((int) $product->getId(), 1);
    $path = productViewsCsv([[$product->getSku(), Mage::app()->getStore(1)->getCode(), (string) (count($before) + 4), '3']]);
    $adapter = Mage::getSingleton('core/resource')->getConnection('core_write');
    $table = Mage::getSingleton('core/resource')->getTableName('reports/event');
    $lastId = (int) $adapter->fetchOne($adapter->select()->from($table, [new Maho\Db\Expr('MAX(event_id)')]));

    try {
        $result = (new ProductViews())->import($path, [ProductViews::OPTION_SKIP_STATISTICS => true]);
        expect($result->created + $result->updated)->toBe(1);

        $after = productViewEvents((int) $product->getId(), 1);
        expect(count($after))->toBe(count($before) + 4);
        foreach (array_slice($after, count($before)) as $event) {
            $age = time() - strtotime($event['logged_at'] . ' UTC');
            expect($age)->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual(3 * 86400);
        }

        $again = (new ProductViews())->import($path, [ProductViews::OPTION_SKIP_STATISTICS => true]);
        expect($again->created + $again->updated)->toBe(0);
        expect(count(productViewEvents((int) $product->getId(), 1)))->toBe(count($before) + 4);
    } finally {
        $adapter->delete($table, ['event_id > ?' => $lastId]);
        unlink($path);
    }
});

it('rejects an unknown sku and a view count that is not a number', function (): void {
    $store = Mage::app()->getStore(1)->getCode();
    $importer = new ProductViews();
    expect(fn() => $importer->validate(productViewsCsv([['NO-SUCH-SKU', $store, '4', '3']])))
        ->toThrow(RowException::class, "line 2: unknown sku 'NO-SUCH-SKU'");
    expect(fn() => $importer->validate(productViewsCsv([[loadSimplePricedProduct()->getSku(), $store, 'lots', '3']])))
        ->toThrow(RowException::class, 'views must be a whole number');
});
