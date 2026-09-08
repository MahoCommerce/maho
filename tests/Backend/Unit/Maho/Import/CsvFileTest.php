<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho
 */

declare(strict_types=1);

use Maho\Import\CsvFile;
use Maho\Import\RowException;

uses(Tests\MahoBackendTestCase::class);

function csvFileFixture(string $content): string
{
    $path = tempnam(sys_get_temp_dir(), 'csvfile') . '.csv';
    file_put_contents($path, $content);
    return $path;
}

it('reads rows keyed by header with physical line numbers and skips blank lines', function (): void {
    $path = csvFileFixture("\xEF\xBB\xBFcode, name ,flag\na,Alpha,1\n\nb,\"Beta, inc\"\n");
    $file = CsvFile::open($path, ['code']);

    expect($file->getColumns())->toBe(['code', 'name', 'flag']);
    expect($file->toArray())->toBe([
        2 => ['code' => 'a', 'name' => 'Alpha', 'flag' => '1'],
        4 => ['code' => 'b', 'name' => 'Beta, inc', 'flag' => ''],
    ]);
    unlink($path);
});

it('rejects a missing required column with the file name and line 1', function (): void {
    $path = csvFileFixture("code,name\na,Alpha\n");
    expect(fn() => CsvFile::open($path, ['code', 'store_code']))
        ->toThrow(RowException::class, basename($path) . ' line 1: missing required column(s) store_code');
    unlink($path);
});

it('rejects a duplicate column and a row with too many values', function (): void {
    $path = csvFileFixture("code,code\na,b\n");
    expect(fn() => CsvFile::open($path))->toThrow(RowException::class, 'duplicate column code');
    unlink($path);

    $path = csvFileFixture("code,name\na,b,c\n");
    expect(fn() => CsvFile::open($path)->toArray())->toThrow(RowException::class, 'line 2: 3 values for 2 columns');
    unlink($path);
});

it('parses pipe lists and booleans', function (): void {
    expect(CsvFile::list(' a | b ||c '))->toBe(['a', 'b', 'c']);
    expect(CsvFile::list(''))->toBe([]);
    expect(CsvFile::bool('', true))->toBeTrue();
    expect(CsvFile::bool('no', true))->toBeFalse();
    expect(CsvFile::bool('1', false))->toBeTrue();
    expect(fn() => CsvFile::bool('maybe', false))->toThrow(InvalidArgumentException::class);
});
