<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use League\Flysystem\Local\LocalFilesystemAdapter;
use Maho\Storage\Mount;
use Maho\Storage\MountRegistry;

uses(Tests\MahoBackendTestCase::class);

beforeEach(function (): void {
    $this->db = Mage::getSingleton('core/resource')->getConnection('core_write');
    $this->db->beginTransaction();
    $this->table = Mage::getSingleton('core/resource')->getTableName('mediacleaner/image');
    $this->mediaRoot = Mage::getBaseDir('var') . '/media_scan_persistence_' . bin2hex(random_bytes(8));
    mkdir($this->mediaRoot, 0777, true);
    MountRegistry::register(new Mount('media', new LocalFilesystemAdapter($this->mediaRoot), $this->mediaRoot));
    $this->db->delete($this->table);
    $this->db->insert($this->table, ['type' => 'product', 'path' => 'other-type.jpg']);
    $this->db->insert($this->table, ['type' => 'category', 'path' => 'stale.jpg']);
    $this->paths = fn(): array => $this->db->fetchCol($this->db->select()
        ->from($this->table, 'path')->where('type = ?', 'category')->order('image_id'));
});

afterEach(function (): void {
    $this->db->rollBack();
    MountRegistry::reset();
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($this->mediaRoot, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($files as $file) {
        $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    }
    rmdir($this->mediaRoot);
});

it('persists complete native scan results with bounded insert statements', function (int $unusedCount): void {
    $paths = [];
    for ($i = 0; $i < $unusedCount; $i++) {
        $paths[] = sprintf('folder%d/unused_%03d.jpg', $i % 4, $i);
    }
    $usedPath = 'folder0/used.jpg';
    foreach ([...$paths, $usedPath] as $path) {
        $filename = $this->mediaRoot . '/catalog/category/' . $path;
        if (!is_dir(dirname($filename))) {
            mkdir(dirname($filename), 0777, true);
        }
        file_put_contents($filename, 'synthetic scan fixture');
    }
    sort($paths, SORT_STRING);

    $resource = Mage::getSingleton('core/resource');
    $type = Mage::getSingleton('eav/config')->getEntityType('catalog_category');
    $categoryTable = $resource->getTableName('catalog/category');
    $categoryId = 1 + (int) $this->db->fetchOne($this->db->select()
        ->from($categoryTable, new Maho\Db\Expr('MAX(entity_id)')));
    $this->db->insert($categoryTable, [
        'entity_id' => $categoryId, 'entity_type_id' => $type->getId(),
        'attribute_set_id' => $type->getDefaultAttributeSetId(), 'parent_id' => 1,
        'path' => '1/' . $categoryId, 'level' => 1, 'position' => 1, 'children_count' => 0,
    ]);
    $attribute = Mage::getSingleton('eav/config')->getAttribute('catalog_category', 'image');
    $this->db->insert($resource->getTableName('catalog_category_entity_varchar'), [
        'entity_id' => $categoryId, 'entity_type_id' => $type->getId(),
        'attribute_id' => $attribute->getId(), 'store_id' => 0, 'value' => $usedPath,
    ]);

    $counter = new class ($this->table) {
        public int $inserts = 0;
        public mixed $previous = null;

        public function __construct(private readonly string $table) {}

        public function record(string $sql, array $bind): void
        {
            if ($this->previous) {
                $this->previous['object']->{$this->previous['method']}($sql, $bind);
            }
            if (str_starts_with($sql, 'INSERT') && str_contains($sql, $this->table)) {
                $this->inserts++;
            }
        }
    };
    $counter->previous = $this->db->setQueryHook(['object' => $counter, 'method' => 'record']);
    $progress = [];
    try {
        $count = Mage::getModel('mediacleaner/scanner')->scan(
            'category',
            static function (int $done, int $total) use (&$progress): void {
                $progress[] = [$done, $total];
            },
        );
    } finally {
        $this->db->setQueryHook($counter->previous);
    }
    $folders = min(4, max(1, $unusedCount));
    $expectedProgress = array_map(static fn(int $done): array => [$done, $folders], range(1, $folders));
    expect($count)->toBe($unusedCount)
        ->and(($this->paths)())->toBe($paths)
        ->and($progress)->toBe($expectedProgress)
        ->and($this->db->fetchCol($this->db->select()->from($this->table, 'path')->where('type = ?', 'product')))
        ->toBe(['other-type.jpg'])
        ->and($counter->inserts)->toBeLessThanOrEqual((int) ceil($unusedCount / 100));

    foreach ($paths as $path) {
        unlink($this->mediaRoot . '/catalog/category/' . $path);
    }
    expect(Mage::getModel('mediacleaner/scanner')->scan('category'))->toBe(0)
        ->and(($this->paths)())->toBe([]);
})->with([0, 1, 99, 100, 101, 201]);

it('ignores repeated paths within and across batches without changing their order', function (): void {
    $paths = [];
    for ($i = 0; $i < 99; $i++) {
        $paths[] = sprintf("quoted_'é_%03d.jpg", $i);
    }
    $scanner = new class extends Maho_MediaCleaner_Model_Scanner {
        public function persist(array $paths): void
        {
            $this->storeUnusedImages('category', $paths);
        }
    };
    $scanner->persist([...$paths, $paths[0], ...$paths, $paths[0], 'last.jpg']);
    expect(($this->paths)())->toBe([...$paths, 'last.jpg']);
});
