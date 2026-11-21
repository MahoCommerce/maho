<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Dataflow
 */

declare(strict_types=1);

use League\Flysystem\Local\LocalFilesystemAdapter;
use Maho\Storage\Mount;
use Maho\Storage\MountRegistry;

uses(Tests\MahoBackendTestCase::class);

it('selects the profile upload parser from the accepted file extension', function (): void {
    $profile = new class extends Mage_Dataflow_Model_Profile {
        public function isCsvUpload(string $uploadFile): bool
        {
            return $this->_isCsvUpload($uploadFile);
        }
    };
    $previous = $_FILES['file_1'] ?? null;
    $_FILES['file_1'] = ['type' => 'application/octet-stream'];

    try {
        expect($profile->isCsvUpload('import-1_products.csv'))->toBeTrue()
            ->and($profile->isCsvUpload('import-1_products.CSV'))->toBeTrue()
            ->and($profile->isCsvUpload('import-1_products.xml'))->toBeFalse();
    } finally {
        if ($previous === null) {
            unset($_FILES['file_1']);
        } else {
            $_FILES['file_1'] = $previous;
        }
    }
});

it('parses the same valid CSV with a CSV or generic client MIME type', function (string $mime): void {
    $root = sys_get_temp_dir() . '/maho_dataflow_upload_' . uniqid();
    mkdir($root, 0777, true);
    MountRegistry::register(new Mount('imports', new LocalFilesystemAdapter($root), $root));
    $upload = (string) tempnam(sys_get_temp_dir(), 'maho_dataflow_csv_');
    $contents = "sku,name\nSKU-1,Product\n";
    file_put_contents($upload, $contents);
    $_FILES['file_1'] = [
        'name' => 'products.csv',
        'type' => $mime,
        'tmp_name' => $upload,
        'error' => UPLOAD_ERR_OK,
        'size' => strlen($contents),
    ];
    $profile = new class extends Mage_Dataflow_Model_Profile {
        protected function _createUploader(string $fieldName): Mage_Core_Model_File_Uploader
        {
            return new class ($fieldName) extends Mage_Core_Model_File_Uploader {
                protected function _moveFile($tmpPath, $destPath)
                {
                    return rename($tmpPath, $destPath);
                }
            };
        }
    };
    $profile->setData([
        'name' => 'Upload MIME ' . uniqid(),
        'direction' => 'import',
        'data_transfer' => 'file',
        'store_id' => 0,
        'entity_type' => 'product',
        'gui_data' => [
            'file' => ['type' => 'file', 'path' => 'var/import', 'filename' => 'products.csv'],
            'parse' => ['type' => 'csv', 'delimiter' => ',', 'enclose' => '"', 'fieldnames' => 'true'],
            'map' => ['product' => ['db' => [], 'file' => []], 'only_specified' => ''],
            'product' => ['filter' => []],
        ],
    ]);

    try {
        $profile->save();

        expect(Mage::helper('dataflow')->getImportFiles('csv'))->toHaveCount(1);
    } finally {
        if ($profile->getId()) {
            $profile->delete();
        }
        unset($_FILES['file_1']);
        MountRegistry::reset();
        foreach (glob($root . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($root);
    }
})->with([
    'CSV MIME' => ['text/csv'],
    'generic MIME' => ['application/octet-stream'],
]);
