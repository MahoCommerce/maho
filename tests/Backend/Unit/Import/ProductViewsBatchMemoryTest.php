<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Maho\Db\Adapter\Pdo\Sqlite;
use Maho\Db\Select;
use Maho\Import\CsvFile;
use Maho\Import\Importer\ProductViews;
use Maho\Import\NullReporter;
use Maho\Import\Result;

class ProductViewsMemoryAdapter extends Sqlite
{
    public array $insertSizes = [];

    #[\Override]
    public function insertMultiple(string|array|Select $table, array $data): int
    {
        $this->insertSizes[] = count($data);

        return parent::insertMultiple($table, $data);
    }
}

class ProductViewsMemoryImporter extends ProductViews
{
    public function writeRows(CsvFile $file, array $rows): Result
    {
        return $this->write($file, $rows, [self::OPTION_SKIP_STATISTICS => true], new NullReporter());
    }
}

beforeEach(function () {
    $this->oldResource = Mage::registry('_singleton/core/resource');
    Mage::unregister('_singleton/core/resource');
    $this->adapter = new ProductViewsMemoryAdapter(['path' => ':memory:']);
    $this->adapter->query('CREATE TABLE memory_product_view_event (event_id INTEGER PRIMARY KEY AUTOINCREMENT, logged_at TEXT NOT NULL, event_type_id INTEGER NOT NULL, object_id INTEGER NOT NULL, subject_id INTEGER NOT NULL, subtype INTEGER NOT NULL, store_id INTEGER NOT NULL)');
    Mage::register('_singleton/core/resource', new readonly class ($this->adapter) {
        public function __construct(private Sqlite $adapter) {}

        public function getConnection(string $name): Sqlite
        {
            return $this->adapter;
        }

        public function getTableName(string $name): string
        {
            return 'memory_product_view_event';
        }
    });
    $this->csvPath = tempnam(sys_get_temp_dir(), 'product_view_memory_');
    file_put_contents($this->csvPath, "sku,store_code,views\n");
    $this->file = CsvFile::open($this->csvPath);
    $this->importer = new ProductViewsMemoryImporter();
});

afterEach(function () {
    Mage::unregister('_singleton/core/resource');
    if ($this->oldResource !== null) {
        Mage::register('_singleton/core/resource', $this->oldResource);
    }
    unlink($this->csvPath);
});

it('writes complete chunks and the remainder with unchanged event data', function (int $views, array $chunks) {
    $row = ['sku' => 'owned-product', 'store_code' => 'owned-store', 'product_id' => 41, 'store_id' => 7, 'views' => (string) $views, 'days' => '3'];
    $before = time();
    $result = $this->importer->writeRows($this->file, [$row]);
    $after = time();

    expect($this->adapter->insertSizes)->toBe($chunks);
    expect($result->created)->toBe(1)->and($result->updated)->toBe(0);
    $events = $this->adapter->fetchAll($this->adapter->select()->from('memory_product_view_event')->order('event_id ASC'));
    expect(count($events))->toBe($views);
    $seed = crc32('owned-product@owned-store');
    $invalidTimestamps = [];
    $invalidAttributes = [];
    foreach ($events as $index => $event) {
        $offset = ($seed + $index * 7919 * 97) % (3 * 86400);
        $timestamp = strtotime($event['logged_at'] . ' UTC');
        if ($timestamp < $before - $offset || $timestamp > $after - $offset) {
            $invalidTimestamps[] = $index;
        }
        $attributes = array_map(intval(...), array_intersect_key($event, array_flip(['event_type_id', 'object_id', 'subject_id', 'subtype', 'store_id'])));
        if ($attributes !== ['event_type_id' => Mage_Reports_Model_Event::EVENT_PRODUCT_VIEW, 'object_id' => 41, 'subject_id' => 0, 'subtype' => 1, 'store_id' => 7]) {
            $invalidAttributes[] = $index;
        }
    }
    expect($invalidTimestamps)->toBe([])->and($invalidAttributes)->toBe([]);

    $again = $this->importer->writeRows($this->file, [$row]);
    expect($again->created + $again->updated)->toBe(0);
    expect($this->adapter->insertSizes)->toBe($chunks);
})->with([
    'one full chunk' => [1000, [1000]],
    'full chunk and remainder' => [1001, [1000, 1]],
]);

it('adds only missing views and keeps each product and store separate', function () {
    $rows = [
        ['sku' => 'one', 'store_code' => 'first', 'product_id' => 1, 'store_id' => 1, 'views' => '2', 'days' => '3'],
        ['sku' => 'two', 'store_code' => 'first', 'product_id' => 2, 'store_id' => 1, 'views' => '3', 'days' => '3'],
        ['sku' => 'one', 'store_code' => 'second', 'product_id' => 1, 'store_id' => 2, 'views' => '4', 'days' => '3'],
    ];
    $result = $this->importer->writeRows($this->file, $rows);
    expect($result->created)->toBe(3)->and($result->updated)->toBe(0);
    $rows[0]['views'] = '1003';
    $rows[1]['views'] = '1';
    $result = $this->importer->writeRows($this->file, $rows);
    expect($result->created)->toBe(0)->and($result->updated)->toBe(1);
    expect($this->adapter->insertSizes)->toBe([2, 3, 4, 1000, 1]);
    expect((int) $this->adapter->fetchOne('SELECT COUNT(*) FROM memory_product_view_event'))->toBe(1010);
});
