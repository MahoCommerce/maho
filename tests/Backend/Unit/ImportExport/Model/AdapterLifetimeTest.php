<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('ImportExport CSV adapter lifetime', function () {
    it('closes the export file when the adapter is freed', function () {
        $file = tempnam(sys_get_temp_dir(), 'maho_export_');
        $before = count(get_resources('stream'));
        $adapter = new Mage_ImportExport_Model_Export_Adapter_Csv($file);
        expect(count(get_resources('stream')))->toBe($before + 1);

        unset($adapter);

        expect(count(get_resources('stream')))->toBe($before);
        unlink($file);
    });

    it('closes the import file when the adapter is freed', function () {
        $file = tempnam(sys_get_temp_dir(), 'maho_import_');
        file_put_contents($file, "sku,name\nA,B\n");
        $before = count(get_resources('stream'));
        $adapter = new Mage_ImportExport_Model_Import_Adapter_Csv($file);
        expect(count(get_resources('stream')))->toBe($before + 1);

        unset($adapter);

        expect(count(get_resources('stream')))->toBe($before);
        unlink($file);
    });
});
