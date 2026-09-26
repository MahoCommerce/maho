<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use League\Flysystem\Local\LocalFilesystemAdapter;
use Maho\Storage\Mount;
use Maho\Storage\MountRegistry;
use Maho\Storage\Url\StoreUrlGenerator;

uses(Tests\MahoBackendTestCase::class);

describe('FeedManager on the media and feeds mounts', function () {
    beforeEach(function (): void {
        $this->root = sys_get_temp_dir() . '/maho_feeds_' . uniqid();
        mkdir($this->root . '/media', 0777, true);
        mkdir($this->root . '/feeds', 0777, true);
        $this->media = new Mount('media', new LocalFilesystemAdapter($this->root . '/media'), $this->root . '/media', new StoreUrlGenerator('media'));
        $this->feeds = new Mount('feeds', new LocalFilesystemAdapter($this->root . '/feeds'), $this->root . '/feeds');
        MountRegistry::register($this->media);
        MountRegistry::register($this->feeds);

        $store = Mage::app()->getStore();
        $this->previousConfig = [
            'feedmanager/general/batch_size' => $store->getConfig('feedmanager/general/batch_size'),
            'feedmanager/general/output_directory' => $store->getConfig('feedmanager/general/output_directory'),
        ];
        $store->setConfig('feedmanager/general/output_directory', 'feeds');

        $this->connection = Mage::getSingleton('core/resource')->getConnection('core_write');
        $this->connection->beginTransaction();

        $this->createFeed = function (string $format, array $data = []): Maho_FeedManager_Model_Feed {
            $feed = Mage::getModel('feedmanager/feed');
            $feed->setData($data + [
                'name' => 'Storage Test Feed ' . $format,
                'platform' => 'custom',
                'store_id' => 1,
                'is_enabled' => 1,
                'filename' => 'storage-test-' . $format . '-' . uniqid(),
                'file_format' => $format,
                'configurable_mode' => 'simple_only',
                'exclude_disabled' => 1,
                'exclude_out_of_stock' => 0,
                'include_product_types' => 'virtual',
            ]);
            $feed->save();

            $mappings = [
                ['platform_attribute' => 'id', 'source_value' => 'sku', 'sort_order' => 1],
                ['platform_attribute' => 'title', 'source_value' => 'name', 'sort_order' => 2],
                ['platform_attribute' => 'price', 'source_value' => 'price', 'sort_order' => 3],
            ];
            foreach ($mappings as $mapping) {
                Mage::getModel('feedmanager/attributeMapping')
                    ->setData($mapping + ['source_type' => 'attribute'])
                    ->setFeedId((int) $feed->getId())
                    ->save();
            }

            return $feed;
        };
    });

    afterEach(function (): void {
        $this->connection->rollBack();
        foreach ($this->previousConfig as $path => $value) {
            Mage::app()->getStore()->setConfig($path, $value);
        }
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

    it('puts the generated feed on the media mount and gives its public URL', function (): void {
        $feed = ($this->createFeed)('json');

        $log = new Maho_FeedManager_Model_Generator()->generate($feed);

        $path = 'feeds/' . $feed->getFilename() . '.json';
        expect($log->getStatus())->toBe(Maho_FeedManager_Model_Log::STATUS_COMPLETED)
            ->and($feed->getStoragePath())->toBe($path)
            ->and($this->media->fileExists($path))->toBeTrue()
            ->and(json_decode($this->media->read($path), true)['products'] ?? [])->not->toBeEmpty()
            ->and((int) $log->getFileSize())->toBe($this->media->fileSize($path))
            ->and(Mage::helper('feedmanager')->getFeedUrl($feed))->toBe(Mage::getBaseUrl('media') . $path);
    });

    it('puts a compressed feed on the mount and deletes the uncompressed file of an earlier generation', function (): void {
        $feed = ($this->createFeed)('xml', ['gzip_compression' => 1]);
        $plainPath = 'feeds/' . $feed->getFilename() . '.xml';
        $this->media->write($plainPath, '<old/>');

        $log = new Maho_FeedManager_Model_Generator()->generate($feed);

        expect($log->getStatus())->toBe(Maho_FeedManager_Model_Log::STATUS_COMPLETED)
            ->and($this->media->fileExists($plainPath))->toBeFalse()
            ->and(gzdecode($this->media->read($plainPath . '.gz')))->toStartWith('<?xml');
    });

    it('writes the same bytes in batches across requests as in one pass', function (string $format): void {
        Mage::app()->getStore()->setConfig('feedmanager/general/batch_size', '2');
        $feed = ($this->createFeed)($format);
        $path = 'feeds/' . $feed->getFilename() . '.' . $format;

        $log = new Maho_FeedManager_Model_Generator()->generate($feed);
        expect($log->getStatus())->toBe(Maho_FeedManager_Model_Log::STATUS_COMPLETED);
        $onePass = $this->media->read($path);
        $this->media->delete($path);

        $init = new Maho_FeedManager_Model_Generator_Batch()->initBatch($feed);
        $jobId = $init['job_id'];
        do {
            // A new object for each call, as each AJAX request builds one
            $result = new Maho_FeedManager_Model_Generator_Batch()->processBatch($jobId);
        } while ($result['status'] === Maho_FeedManager_Model_Generator_Batch::STATUS_PROCESSING);
        $parts = array_map(
            fn($item) => basename($item->path()),
            iterator_to_array($this->feeds->listContents($jobId, false)),
        );
        $final = new Maho_FeedManager_Model_Generator_Batch()->finalize($jobId);

        expect($init['batches_total'])->toBeGreaterThanOrEqual(3)
            ->and($result['status'])->toBe(Maho_FeedManager_Model_Generator_Batch::STATUS_FINALIZING)
            ->and($parts)->toContain('state.json', sprintf('part-000000.%s', $format), sprintf('part-%06d.%s', $init['batches_total'], $format))
            ->and($final['status'])->toBe(Maho_FeedManager_Model_Generator_Batch::STATUS_COMPLETED)
            ->and($final['file_url'])->toBe(Mage::getBaseUrl('media') . $path)
            ->and($this->media->read($path))->toBe($onePass)
            ->and($this->feeds->directoryExists($jobId))->toBeFalse();
    })->with(['json', 'csv', 'xml', 'jsonl']);

    it('removes the parts and the state when a job is cancelled', function (): void {
        Mage::app()->getStore()->setConfig('feedmanager/general/batch_size', '2');
        $feed = ($this->createFeed)('xml');

        $jobId = new Maho_FeedManager_Model_Generator_Batch()->initBatch($feed)['job_id'];
        new Maho_FeedManager_Model_Generator_Batch()->processBatch($jobId);
        $before = $this->feeds->fileExists($jobId . '/part-000001.xml');

        $result = new Maho_FeedManager_Model_Generator_Batch()->cancel($jobId);

        expect($before)->toBeTrue()
            ->and($result['status'])->toBe('cancelled')
            ->and($this->feeds->directoryExists($jobId))->toBeFalse();
    });

    it('fails the batch generation when the parts are joined in a wrong order', function (): void {
        Mage::app()->getStore()->setConfig('feedmanager/general/batch_size', '2');
        $feed = ($this->createFeed)('json');
        $path = 'feeds/' . $feed->getFilename() . '.json';

        $jobId = new Maho_FeedManager_Model_Generator_Batch()->initBatch($feed)['job_id'];
        do {
            $result = new Maho_FeedManager_Model_Generator_Batch()->processBatch($jobId);
        } while ($result['status'] === Maho_FeedManager_Model_Generator_Batch::STATUS_PROCESSING);
        $header = $this->feeds->read($jobId . '/part-000000.json');
        $this->feeds->write($jobId . '/part-000000.json', $this->feeds->read($jobId . '/part-000001.json'));
        $this->feeds->write($jobId . '/part-000001.json', $header);

        $final = new Maho_FeedManager_Model_Generator_Batch()->finalize($jobId);

        expect($final['status'])->toBe(Maho_FeedManager_Model_Generator_Batch::STATUS_FAILED)
            ->and($this->media->fileExists($path))->toBeFalse();
    });

    it('deletes the idle jobs of one feed and the old jobs of every feed', function (): void {
        foreach (['feed_5_aa', 'feed_5_bb', 'feed_55_cc'] as $jobId) {
            $this->feeds->write($jobId . '/state.json', '{}');
        }
        touch($this->root . '/feeds/feed_5_aa/state.json', time() - 600);
        touch($this->root . '/feeds/feed_55_cc/state.json', time() - 600);

        $idle = new Maho_FeedManager_Model_Generator_Batch()->deleteFeedJobs(5, 300);
        $afterIdle = [
            $this->feeds->directoryExists('feed_5_aa'),
            $this->feeds->directoryExists('feed_5_bb'),
            $this->feeds->directoryExists('feed_55_cc'),
        ];
        touch($this->root . '/feeds/feed_55_cc/state.json', time() - 7200);
        $old = Maho_FeedManager_Model_Generator_Batch::cleanupOldJobs(1);

        expect($idle)->toBe(1)
            ->and($afterIdle)->toBe([false, true, true])
            ->and($old)->toBe(1)
            ->and($this->feeds->directoryExists('feed_5_bb'))->toBeTrue()
            ->and($this->feeds->directoryExists('feed_55_cc'))->toBeFalse();
    });

    it('refuses a job ID that is not a job ID', function (): void {
        $this->feeds->write('other/state.json', '{"status":"processing"}');

        $result = new Maho_FeedManager_Model_Generator_Batch()->processBatch('other');

        expect($result['status'])->toBe(Maho_FeedManager_Model_Generator_Batch::STATUS_FAILED)
            ->and($result['message'])->toBe('Invalid or expired job ID');
    });

    it('deletes the feed file with the feed', function (): void {
        $feed = ($this->createFeed)('csv');
        $path = (string) $feed->getStoragePath();
        $this->media->write($path, "id\n1\n");

        $feed->delete();

        expect($path)->toBe('feeds/' . $feed->getFilename() . '.csv')
            ->and($this->media->fileExists($path))->toBeFalse();
    });

    it('refuses an output directory that leaves the media mount', function (string $value): void {
        $config = Mage::getModel('feedmanager/system_config_backend_outputDirectory')
            ->setPath('feedmanager/general/output_directory')
            ->setValue($value);

        expect(fn() => $config->save())
            ->toThrow(Mage_Core_Exception::class, 'Output directory must be a relative path within the media folder.');
    })->with(['../app/etc', 'feeds/../../app', '..']);

    it('keeps an output directory inside the media mount', function (): void {
        $config = Mage::getModel('feedmanager/system_config_backend_outputDirectory')
            ->setPath('feedmanager/general/output_directory')
            ->setScope('default')
            ->setScopeId(0)
            ->setValue('/feeds/google/');

        $config->save();

        expect($config->getValue())->toBe('feeds/google');
    });
});
