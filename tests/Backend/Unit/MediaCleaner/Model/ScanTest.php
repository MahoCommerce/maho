<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use League\Flysystem\Local\LocalFilesystemAdapter;
use Maho\Job\StepState;
use Maho\Storage\Mount;
use Maho\Storage\MountRegistry;

uses(Tests\MahoBackendTestCase::class);

describe('a Media Cleaner scan in a job dialog', function () {
    beforeEach(function (): void {
        $this->root = sys_get_temp_dir() . '/maho_mediacleaner_scan_' . uniqid();
        mkdir($this->root, 0777, true);
        $this->mount = new Mount('media', new LocalFilesystemAdapter($this->root), $this->root);
        MountRegistry::register($this->mount);

        $this->progress = Mage::getModel('mediacleaner/progress');
        $this->progress->init([['code' => 'category', 'name' => 'Scan Categories']]);

        $resource = Mage::getSingleton('core/resource');
        $this->db = $resource->getConnection('core_write');
        $this->table = $resource->getTableName('mediacleaner/image');
        $this->results = fn(): array => $this->db->fetchCol($this->db->select()->from($this->table, 'path')->where('type = ?', 'category'));
    });

    afterEach(function (): void {
        $this->db->delete($this->table, ['type = ?' => 'category']);
        @unlink($this->progress->getFilePath());
        MountRegistry::reset();
        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($this->root);
    });

    it('stores the unused files of a scan, and replaces them on the next scan', function (): void {
        $name = 'scan_test_' . uniqid() . '.jpg';
        $this->mount->write('catalog/category/' . $name, 'x');
        $scanner = Mage::getModel('mediacleaner/scanner');

        $first = $scanner->scan('category');
        $found = ($this->results)();
        $this->mount->delete('catalog/category/' . $name);
        $this->mount->write('catalog/category/keep/' . $name, 'x');
        $scanner->scan('category');

        expect($first)->toBe(1)
            ->and($found)->toBe([$name])
            ->and(($this->results)())->toBe(['keep/' . $name]);
    });

    it('refuses an unknown scan type', function (): void {
        expect(fn() => Mage::getModel('mediacleaner/scanner')->scan('unknown'))->toThrow(Mage_Core_Exception::class);
    });

    it('shows the folders read as a running step with a percent', function (): void {
        $this->progress->startStep('category');
        $this->progress->reportProgress('category', 1, 4, '1 of 4 folders read');
        $step = $this->progress->read()['steps'][0];

        expect($step['state'])->toBe(StepState::Running->value)
            ->and($step['progress'])->toBe(25)
            ->and($step['message'])->toBe('1 of 4 folders read');
    });

    it('marks a scan that stopped writing its record as interrupted', function (): void {
        $this->progress->startStep('category');
        $record = $this->progress->read();
        $stale = ['updated_at' => time() - Maho_MediaCleaner_Model_Progress::STALE_AFTER - 1] + $record;

        $interrupted = Maho_MediaCleaner_Model_Progress::detectInterruptedRun($stale);

        expect(Maho_MediaCleaner_Model_Progress::detectInterruptedRun($record))->toBe($record)
            ->and($interrupted['finished'])->toBeTrue()
            ->and($interrupted['steps'][0]['state'])->toBe(StepState::Error->value);
    });
});
