<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Storage
 */

declare(strict_types=1);

use Maho\Storage\AdapterFactory;
use Maho\Storage\Mount;
use Maho\Storage\MountDefinition;

describe('Maho\Storage\Mount on Google Cloud Storage', function () {
    it('builds the adapter through the factory and its public url with no request', function (): void {
        $definition = new MountDefinition(name: 'media', adapterType: 'gcs', adapterOptions: [
            'bucket' => 'maho-test',
            'project_id' => 'maho',
            'prefix' => 'media',
        ]);

        $mount = new Mount('media', new AdapterFactory()->create($definition));

        expect($mount->isLocal())->toBeFalse()
            ->and($mount->publicUrl('catalog/a.jpg'))->toBe('https://storage.googleapis.com/maho-test/media/catalog/a.jpg');
    })->skip(
        fn() => !class_exists(\League\Flysystem\GoogleCloudStorage\GoogleCloudStorageAdapter::class),
        'league/flysystem-google-cloud-storage is not installed',
    );
})->group('storage-gcs');
