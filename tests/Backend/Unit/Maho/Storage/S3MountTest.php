<?php

/**
 * Runs against a real S3 endpoint (MinIO in CI) to lock the semantics that differ from a disk.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Storage
 */

declare(strict_types=1);

use Aws\S3\S3Client;
use League\Flysystem\UnableToMoveFile;
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

    return new Mount('media', (new AdapterFactory())->create($definition), null, $generator);
}

describe('Maho\Storage\Mount on S3', function () {
    beforeEach(function (): void {
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

    it('moves by copy plus delete and fails on a missing source', function (): void {
        $this->mount->write('a.txt', 'x');
        $this->mount->move('a.txt', 'b.txt');

        expect($this->mount->fileExists('a.txt'))->toBeFalse()
            ->and($this->mount->read('b.txt'))->toBe('x')
            ->and(fn() => $this->mount->move('missing.txt', 'c.txt'))->toThrow(UnableToMoveFile::class);
    });

    it('has prefixes instead of directories', function (): void {
        $this->mount->write('dir/sub/f.txt', '1');
        $this->mount->createDirectory('empty');

        expect($this->mount->directoryExists('dir'))->toBeTrue()
            ->and($this->mount->directoryExists('dir/sub'))->toBeTrue();

        // A shallow listing synthesizes the prefix as a directory; a deep one returns objects only.
        $shallow = array_map(fn($item) => $item->path(), $this->mount->listContents('dir', false)->toArray());
        $deep = array_map(fn($item) => $item->path(), $this->mount->listContents('dir', true)->toArray());

        expect($shallow)->toBe(['dir/sub'])
            ->and($deep)->toBe(['dir/sub/f.txt']);

        $this->mount->deleteDirectory('dir');

        expect($this->mount->fileExists('dir/sub/f.txt'))->toBeFalse()
            ->and($this->mount->directoryExists('dir'))->toBeFalse();
    });

    it('overwrites without a lock', function (): void {
        $this->mount->write('same.txt', 'first');
        $this->mount->write('same.txt', 'second');

        expect($this->mount->read('same.txt'))->toBe('second');
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
