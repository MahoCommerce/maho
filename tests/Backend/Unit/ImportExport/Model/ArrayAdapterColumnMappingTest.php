<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

describe('ImportExport array column mapping', function () {
    it('matches associative rows by their column names', function (array $row, array $expected) {
        $adapter = Mage_ImportExport_Model_Import_Adapter::createArrayAdapter([
            ['sku' => 'FIRST', 'name' => 'First product', 'price' => '10.00'],
            $row,
        ]);

        $adapter->next();

        expect($adapter->current())->toBe($expected);
    })->with([
        'reordered columns' => [
            ['price' => '20.00', 'sku' => 'SECOND', 'name' => 'Second product'],
            ['sku' => 'SECOND', 'name' => 'Second product', 'price' => '20.00'],
        ],
        'missing middle column' => [
            ['sku' => 'SECOND', 'price' => '20.00'],
            ['sku' => 'SECOND', 'name' => '', 'price' => '20.00'],
        ],
        'missing first column' => [
            ['name' => 'Second product', 'price' => '20.00'],
            ['sku' => '', 'name' => 'Second product', 'price' => '20.00'],
        ],
        'extra column' => [
            ['description' => 'Unused', 'sku' => 'SECOND', 'name' => 'Second product', 'price' => '20.00'],
            ['sku' => 'SECOND', 'name' => 'Second product', 'price' => '20.00'],
        ],
        'null and empty values' => [
            ['price' => null, 'sku' => 'SECOND', 'name' => ''],
            ['sku' => 'SECOND', 'name' => '', 'price' => null],
        ],
    ]);

    it('does not stop iteration at a row with no values', function () {
        $adapter = Mage_ImportExport_Model_Import_Adapter::createArrayAdapter([
            ['sku' => 'FIRST', 'name' => 'First product'],
            [],
            ['name' => 'Third product', 'sku' => 'THIRD'],
        ]);

        $adapter->next();
        expect($adapter->valid())->toBeTrue()
            ->and($adapter->current())->toBe(['sku' => '', 'name' => '']);

        $adapter->next();
        expect($adapter->current())->toBe(['sku' => 'THIRD', 'name' => 'Third product']);
    });

    it('keeps indexed rows positional and pads missing values', function () {
        $adapter = Mage_ImportExport_Model_Import_Adapter::createArrayAdapter([
            ['sku', 'name', 'price'],
            ['FIRST', 'First product', '10.00'],
            ['SECOND', 'Second product'],
        ]);

        expect($adapter->current())->toBe(['sku' => 'FIRST', 'name' => 'First product', 'price' => '10.00']);

        $adapter->seek(1);

        expect($adapter->current())->toBe(['sku' => 'SECOND', 'name' => 'Second product', 'price' => '']);
    });

    it('keeps an indexed row positional when its internal pointer is advanced', function () {
        $row = ['SECOND', 'Second product'];
        end($row);
        next($row);

        $adapter = Mage_ImportExport_Model_Import_Adapter::createArrayAdapter([
            ['sku', 'name'],
            $row,
        ]);

        expect($adapter->current())->toBe(['sku' => 'SECOND', 'name' => 'Second product']);
    });

    it('uses the same mapping when seeking and rewinding', function () {
        $adapter = Mage_ImportExport_Model_Import_Adapter::createArrayAdapter([
            ['sku' => 'FIRST', 'name' => 'First product'],
            ['name' => 'Second product', 'sku' => 'SECOND'],
        ]);

        $adapter->seek(1);
        expect($adapter->current())->toBe(['sku' => 'SECOND', 'name' => 'Second product']);

        $adapter->rewind();
        expect($adapter->current())->toBe(['sku' => 'FIRST', 'name' => 'First product']);

        $adapter->next();
        expect($adapter->current())->toBe(['sku' => 'SECOND', 'name' => 'Second product']);
    });
});
