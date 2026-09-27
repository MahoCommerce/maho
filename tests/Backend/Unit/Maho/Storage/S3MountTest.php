<?php

/**
 * Runs against an S3 endpoint (S3Mock in CI) to check the S3 mount that the factory builds.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Storage
 */

declare(strict_types=1);

use Aws\S3\S3Client;
use League\Flysystem\Visibility;
use Maho\Storage\AdapterFactory;
use Maho\Storage\Mount;
use Maho\Storage\MountDefinition;
use Tests\TestEnv;

function storageS3Definition(string $prefix, ?string $publicUrl = null): MountDefinition
{
    return new MountDefinition(
        name: 'media',
        publicUrl: $publicUrl,
        adapterType: 's3',
        adapterOptions: [
            'bucket' => TestEnv::get('MAHO_TEST_S3_BUCKET'),
            'prefix' => $prefix,
            'region' => 'us-east-1',
            'endpoint' => TestEnv::get('MAHO_TEST_S3_ENDPOINT'),
            'key' => TestEnv::get('MAHO_TEST_S3_KEY'),
            'secret' => TestEnv::get('MAHO_TEST_S3_SECRET'),
            'use_path_style_endpoint' => '1',
        ],
    );
}

function storageS3Client(): S3Client
{
    return new S3Client([
        'version' => 'latest',
        'region' => 'us-east-1',
        'endpoint' => TestEnv::get('MAHO_TEST_S3_ENDPOINT'),
        'use_path_style_endpoint' => true,
        'credentials' => ['key' => TestEnv::get('MAHO_TEST_S3_KEY'), 'secret' => TestEnv::get('MAHO_TEST_S3_SECRET')],
    ]);
}

function storageS3Mount(string $prefix, ?string $publicUrl = null): Mount
{
    $definition = storageS3Definition($prefix, $publicUrl);
    $generator = $publicUrl === null ? null : new League\Flysystem\UrlGeneration\PrefixPublicUrlGenerator($publicUrl);

    return new Mount('media', new AdapterFactory()->create($definition), null, $generator);
}

describe('Maho\Storage\Mount on S3', function () {
    beforeEach(function (): void {
        if (!class_exists(\League\Flysystem\AwsS3V3\AwsS3V3Adapter::class)) {
            $this->markTestSkipped('league/flysystem-aws-s3-v3 is not installed');
        }
        if (!TestEnv::has('MAHO_TEST_S3_ENDPOINT', 'MAHO_TEST_S3_KEY', 'MAHO_TEST_S3_SECRET', 'MAHO_TEST_S3_BUCKET')) {
            $this->markTestSkipped('MAHO_TEST_S3_* is not set');
        }
        $bucket = TestEnv::get('MAHO_TEST_S3_BUCKET');
        $client = storageS3Client();
        if (!$client->doesBucketExistV2($bucket)) {
            $client->createBucket(['Bucket' => $bucket]);
        }
        $this->prefix = 'maho-test-' . bin2hex(random_bytes(4));
        $this->mount = storageS3Mount($this->prefix);
    });

    afterEach(function (): void {
        if (isset($this->mount)) {
            $this->mount->deleteDirectory('');
        }
    });

    it('is a remote mount', function (): void {
        expect($this->mount->isLocal())->toBeFalse()
            ->and($this->mount->localRoot())->toBeNull()
            ->and($this->mount->supportsTemporaryUrls())->toBeTrue();
    });

    it('round-trips a file', function (): void {
        $this->mount->write('catalog/a.txt', 'hello');
        $this->mount->copy('catalog/a.txt', 'catalog/b.txt');

        expect($this->mount->fileExists('catalog/a.txt'))->toBeTrue()
            ->and($this->mount->read('catalog/b.txt'))->toBe('hello')
            ->and($this->mount->fileSize('catalog/a.txt'))->toBe(5);

        $this->mount->delete('catalog/a.txt');

        expect($this->mount->fileExists('catalog/a.txt'))->toBeFalse();
    });

    it('moves atomically with a plain put and no temp key', function (): void {
        $this->mount->moveAtomic('catalog/c.txt', 'bytes');
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, 'streamed');
        $this->mount->moveAtomic('catalog/d.txt', $stream);
        fclose($stream);

        $paths = array_map(fn($item) => $item->path(), $this->mount->listContents('catalog', true)->toArray());
        sort($paths);

        expect($this->mount->read('catalog/c.txt'))->toBe('bytes')
            ->and($this->mount->read('catalog/d.txt'))->toBe('streamed')
            ->and($paths)->toBe(['catalog/c.txt', 'catalog/d.txt']);
    });

    it('lists the files at any depth and leaves out a folder that createDirectory() made', function (): void {
        $this->mount->write('d/e/f.txt', 'x');
        $this->mount->createDirectory('empty');

        expect(array_map(fn($item) => $item->path(), $this->mount->listFiles()->toArray()))->toBe(['d/e/f.txt']);
    });

    it('builds the bucket url without a public_url and a cdn url with one', function (): void {
        $this->mount->write('u.txt', 'u');
        $bucket = TestEnv::get('MAHO_TEST_S3_BUCKET');

        expect($this->mount->publicUrl('u.txt'))->toBe(rtrim(TestEnv::get('MAHO_TEST_S3_ENDPOINT'), '/') . "/$bucket/{$this->prefix}/u.txt")
            ->and(storageS3Mount($this->prefix, 'https://cdn.example.com/media/')->publicUrl('u.txt'))->toBe('https://cdn.example.com/media/u.txt');
    });

    it('signs a temporary url that a plain GET can fetch', function (): void {
        $this->mount->write('private/t.txt', 'signed', ['visibility' => Visibility::PRIVATE]);

        $url = $this->mount->temporaryUrl('private/t.txt', new DateTimeImmutable('+5 minutes'));

        expect($url)->toContain('X-Amz-Signature=')
            ->and(file_get_contents($url))->toBe('signed');
    });
})->group('storage-s3');
