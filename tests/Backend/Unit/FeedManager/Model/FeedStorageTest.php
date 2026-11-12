<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use League\Flysystem\Local\LocalFilesystemAdapter;
use Maho\Storage\Mount;
use Maho\Storage\MountRegistry;
use Maho\Storage\Url\StoreBaseUrlGenerator;

uses(Tests\MahoBackendTestCase::class);

class FeedStorageEmptyPageBatch extends Maho_FeedManager_Model_Generator_Batch
{
    public static bool $emptyPage = false;

    #[\Override]
    protected function _getProductCollection(): Mage_Catalog_Model_Resource_Product_Collection
    {
        $collection = parent::_getProductCollection();
        if (self::$emptyPage) {
            $collection->getSelect()->where('1 = 0');
        }

        return $collection;
    }
}

class FeedStorageFilteredBatch extends Maho_FeedManager_Model_Generator_Batch
{
    #[\Override]
    protected function _validateProductConditions(Mage_Catalog_Model_Product $product): bool
    {
        return false;
    }
}

class FeedStorageFilteredGenerator extends Maho_FeedManager_Model_Generator
{
    #[\Override]
    protected function _validateProductConditions(Mage_Catalog_Model_Product $product): bool
    {
        return false;
    }
}

class FeedStorageErrorBatch extends Maho_FeedManager_Model_Generator_Batch
{
    #[\Override]
    protected function _writeProduct(Mage_Catalog_Model_Product $product): void
    {
        throw new RuntimeException('Injected tolerated product error');
    }
}

class FeedStorageErrorGenerator extends Maho_FeedManager_Model_Generator
{
    #[\Override]
    protected function _writeProduct(Mage_Catalog_Model_Product $product): void
    {
        throw new RuntimeException('Injected tolerated product error');
    }
}

class FeedStorageEmptyAfterCountGenerator extends Maho_FeedManager_Model_Generator
{
    public static int $collectionCalls = 0;

    private const COLLECTION_CALL_LIMIT = 3;

    #[\Override]
    protected function _getProductCollection(): Mage_Catalog_Model_Resource_Product_Collection
    {
        if (self::$collectionCalls >= self::COLLECTION_CALL_LIMIT) {
            throw new RuntimeException('Injected collection-call sentinel reached');
        }

        $collection = parent::_getProductCollection();
        if (self::$collectionCalls++ > 0) {
            $collection->getSelect()->where('1 = 0');
        }

        return $collection;
    }
}

class FeedStorageCloseFailingGenerator extends FeedStorageEmptyAfterCountGenerator
{
    public static int $closeCalls = 0;

    #[\Override]
    protected function _closeOutput(): void
    {
        parent::_closeOutput();
        self::$closeCalls++;
        throw new RuntimeException('Injected close failure');
    }
}

class FeedStoragePauseFailingBatch extends FeedStorageEmptyPageBatch
{
    public static int $pauseCalls = 0;

    #[\Override]
    protected function _pauseOutput(): void
    {
        parent::_pauseOutput();
        self::$pauseCalls++;
        throw new RuntimeException('Injected pause failure');
    }
}

class FeedStorageOpenFailingGenerator extends Maho_FeedManager_Model_Generator
{
    #[\Override]
    protected function _openOutput(string $filePath): void
    {
        throw new RuntimeException('Injected open failure');
    }
}

class FeedStorageResumeFailingBatch extends Maho_FeedManager_Model_Generator_Batch
{
    #[\Override]
    protected function _resumeOutput(string $filePath, array $writerState = []): void
    {
        throw new RuntimeException('Injected resume failure');
    }
}

class FeedStorageCollectionCounter extends Maho_FeedManager_Model_Generator
{
    public function countProducts(Maho_FeedManager_Model_Feed $feed): int
    {
        $this->_feed = $feed;

        return $this->_getProductCollection()->getSize();
    }
}

describe('FeedManager on the media and feeds mounts', function () {
    beforeEach(function (): void {
        $this->root = sys_get_temp_dir() . '/maho_feeds_' . uniqid();
        mkdir($this->root . '/media', 0777, true);
        mkdir($this->root . '/feeds', 0777, true);
        $this->media = new Mount('media', new LocalFilesystemAdapter($this->root . '/media'), $this->root . '/media', new StoreBaseUrlGenerator('media'));
        $this->feeds = new Mount('feeds', new LocalFilesystemAdapter($this->root . '/feeds'), $this->root . '/feeds');
        MountRegistry::register($this->media);
        MountRegistry::register($this->feeds);

        $store = Mage::app()->getStore();
        $this->previousConfig = [
            'feedmanager/general/batch_size' => $store->getConfig('feedmanager/general/batch_size'),
            'feedmanager/general/output_directory' => $store->getConfig('feedmanager/general/output_directory'),
        ];
        $store->setConfig('feedmanager/general/output_directory', 'feeds');

        $resource = Mage::getSingleton('core/resource');
        $this->connection = $resource->getConnection('core_write');
        if ($this->connection !== $resource->getConnection('feedmanager_write')) {
            throw new RuntimeException('FeedManager and core write connections must be shared for this test');
        }
        $this->feedResource = Mage::getResourceModel('feedmanager/feed');
        $this->feedResource->beginTransaction();

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
        FeedStorageEmptyPageBatch::$emptyPage = false;
        FeedStorageEmptyAfterCountGenerator::$collectionCalls = 0;
        FeedStorageCloseFailingGenerator::$closeCalls = 0;
        FeedStoragePauseFailingBatch::$pauseCalls = 0;
        $this->feedResource->rollBack();
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

    it('gives the local file of the published feed to the callback and deletes it after the call', function (): void {
        $feed = ($this->createFeed)('csv');
        $seen = null;
        $content = null;

        $log = new Maho_FeedManager_Model_Generator()->generate(
            $feed,
            function (string $localPath) use (&$seen, &$content): void {
                $seen = $localPath;
                $content = file_get_contents($localPath);
            },
        );

        expect($log->getStatus())->toBe(Maho_FeedManager_Model_Log::STATUS_COMPLETED)
            ->and($content)->toBe($this->media->read('feeds/' . $feed->getFilename() . '.csv'))
            ->and(is_file((string) $seen))->toBeFalse();
    });

    it('gives the cron upload the local file of the feed that it just generated', function (): void {
        $destination = Mage::getModel('feedmanager/destination')
            ->setData(['name' => 'Storage Test Destination', 'type' => Maho_FeedManager_Model_Destination::TYPE_SFTP, 'is_enabled' => 1])
            ->save();
        $feed = ($this->createFeed)('csv', ['auto_upload' => 1, 'destination_id' => (int) $destination->getId()]);
        $cron = new class extends Maho_FeedManager_Model_Cron {
            public ?string $uploaded = null;

            public function generateFeed(Maho_FeedManager_Model_Feed $feed): void
            {
                $this->_generateFeed($feed);
            }

            #[\Override]
            protected function _uploadFeed(Maho_FeedManager_Model_Feed $feed, Maho_FeedManager_Model_Log $log, string $localPath): void
            {
                $this->uploaded = (string) file_get_contents($localPath);
            }
        };

        $cron->generateFeed($feed);

        expect($cron->uploaded)->toBe($this->media->read('feeds/' . $feed->getFilename() . '.csv'));
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
        $processCalls = 0;
        do {
            // A new object for each call, as each AJAX request builds one
            $result = new Maho_FeedManager_Model_Generator_Batch()->processBatch($jobId);
            $processCalls++;
        } while ($result['status'] === Maho_FeedManager_Model_Generator_Batch::STATUS_PROCESSING && $processCalls < $init['batches_total']);
        $parts = array_map(
            fn($item) => basename($item->path()),
            iterator_to_array($this->feeds->listContents($jobId, false)),
        );
        $final = new Maho_FeedManager_Model_Generator_Batch()->finalize($jobId);

        expect($init['batches_total'])->toBeGreaterThanOrEqual(3)
            ->and($processCalls)->toBe($init['batches_total'])
            ->and($result['status'])->toBe(Maho_FeedManager_Model_Generator_Batch::STATUS_FINALIZING)
            ->and($parts)->toContain('state.json', sprintf('part-000000.%s', $format), sprintf('part-%06d.%s', $init['batches_total'], $format))
            ->and($final['status'])->toBe(Maho_FeedManager_Model_Generator_Batch::STATUS_COMPLETED)
            ->and($final['file_url'])->toBe(Mage::getBaseUrl('media') . $path)
            ->and($this->media->read($path))->toBe($onePass)
            ->and($this->feeds->directoryExists($jobId))->toBeFalse();
    })->with(['json', 'csv', 'xml', 'jsonl']);

    it('fails a synchronous generation when a page visits no products and preserves the published file', function (): void {
        $feed = ($this->createFeed)('json');
        expect(new FeedStorageCollectionCounter()->countProducts($feed))->toBeGreaterThan(0);
        $path = 'feeds/' . $feed->getFilename() . '.json';
        $this->media->write($path, 'previous feed bytes');
        $feed->setLastGeneratedAt('2026-11-07 12:00:00')->setLastProductCount(19)->setLastFileSize(321)->save();
        FeedStorageEmptyAfterCountGenerator::$collectionCalls = 0;

        $log = new FeedStorageEmptyAfterCountGenerator()->generate($feed);

        expect($log->getStatus())->toBe(Maho_FeedManager_Model_Log::STATUS_FAILED)
            ->and($this->media->read($path))->toBe('previous feed bytes')
            ->and($feed->getLastGeneratedAt())->toBe('2026-11-07 12:00:00')
            ->and((int) $feed->getLastProductCount())->toBe(19)
            ->and((int) $feed->getLastFileSize())->toBe(321)
            ->and($log->getErrorMessagesArray())->toContain('Generation stopped because a batch visited no products before the initial total was reached');
    });

    it('fails an empty AJAX page without saving page output or advancing work counters', function (): void {
        Mage::app()->getStore()->setConfig('feedmanager/general/batch_size', '1');
        $feed = ($this->createFeed)('json');
        $publishedPath = 'feeds/' . $feed->getFilename() . '.json';
        $this->media->write($publishedPath, 'previous feed bytes');
        FeedStorageEmptyPageBatch::$emptyPage = false;
        $batch = new FeedStorageEmptyPageBatch();
        $job = $batch->initBatch($feed);
        expect($job['total_products'])->toBeGreaterThan(1);
        $first = $batch->processBatch($job['job_id']);
        expect($first['status'])->toBe(Maho_FeedManager_Model_Generator_Batch::STATUS_PROCESSING);
        $before = json_decode($this->feeds->read($job['job_id'] . '/state.json'), true, flags: JSON_THROW_ON_ERROR);
        expect($this->feeds->fileExists($job['job_id'] . '/part-000001.json'))->toBeTrue();
        FeedStorageEmptyPageBatch::$emptyPage = true;

        $result = $batch->processBatch($job['job_id']);
        $state = $batch->getStatus($job['job_id']);
        $savedState = json_decode($this->feeds->read($job['job_id'] . '/state.json'), true, flags: JSON_THROW_ON_ERROR);

        expect($result['status'])->toBe(Maho_FeedManager_Model_Generator_Batch::STATUS_FAILED)
            ->and($state['status'])->toBe(Maho_FeedManager_Model_Generator_Batch::STATUS_FAILED)
            ->and($state['processed'])->toBe($before['processed_count'])
            ->and($state['progress'])->toBe($before['product_count'])
            ->and($state['batches_processed'])->toBe($before['batches_processed'])
            ->and($savedState['current_page'])->toBe(2)
            ->and($savedState['processed_count'])->toBe($before['processed_count'])
            ->and($savedState['product_count'])->toBe($before['product_count'])
            ->and($savedState['error_count'])->toBe($before['error_count'])
            ->and($savedState['batches_processed'])->toBe($before['batches_processed'])
            ->and($savedState['writer_state'])->toBe($before['writer_state'])
            ->and($this->feeds->fileExists($job['job_id'] . '/part-000001.json'))->toBeTrue()
            ->and($this->feeds->fileExists($job['job_id'] . '/part-000002.json'))->toBeFalse()
            ->and($this->media->read($publishedPath))->toBe('previous feed bytes');
        $log = Mage::getModel('feedmanager/log')->load($job['log_id']);
        expect($log->getStatus())->toBe(Maho_FeedManager_Model_Log::STATUS_FAILED);
    });

    it('rejects direct finalization before the initial product target is visited', function (): void {
        $feed = ($this->createFeed)('json');
        $path = 'feeds/' . $feed->getFilename() . '.json';
        $this->media->write($path, 'previous feed bytes');
        $batch = new Maho_FeedManager_Model_Generator_Batch();
        $job = $batch->initBatch($feed);
        expect($job['total_products'])->toBeGreaterThan(0);

        $result = $batch->finalize($job['job_id']);

        expect($result['status'])->toBe(Maho_FeedManager_Model_Generator_Batch::STATUS_FAILED)
            ->and($this->media->read($path))->toBe('previous feed bytes')
            ->and($this->feeds->fileExists($job['job_id'] . '/part-000000.json'))->toBeTrue();
    });

    it('allows a batch job whose initial product target is zero to finalize', function (): void {
        $feed = ($this->createFeed)('json');
        FeedStorageEmptyPageBatch::$emptyPage = true;
        $batch = new FeedStorageEmptyPageBatch();
        $job = $batch->initBatch($feed);
        expect($job['total_products'])->toBe(0);

        $result = $batch->processBatch($job['job_id']);
        expect($result['status'])->toBe(Maho_FeedManager_Model_Generator_Batch::STATUS_FINALIZING);
        $final = $batch->finalize($job['job_id']);
        expect($final['status'])->toBe(Maho_FeedManager_Model_Generator_Batch::STATUS_COMPLETED);
    });

    it('advances synchronous and AJAX visits when every product is filtered out', function (): void {
        Mage::app()->getStore()->setConfig('feedmanager/general/batch_size', '2');
        $feed = ($this->createFeed)('json');
        expect(new FeedStorageCollectionCounter()->countProducts($feed))->toBeGreaterThan(0);

        $syncLog = new FeedStorageFilteredGenerator()->generate($feed);
        expect($syncLog->getStatus())->toBe(Maho_FeedManager_Model_Log::STATUS_COMPLETED)
            ->and((int) $syncLog->getProductCount())->toBe(0);

        $job = new FeedStorageFilteredBatch()->initBatch($feed);
        $processCalls = 0;
        do {
            $result = new FeedStorageFilteredBatch()->processBatch($job['job_id']);
            $processCalls++;
        } while ($result['status'] === Maho_FeedManager_Model_Generator_Batch::STATUS_PROCESSING && $processCalls < $job['batches_total']);
        expect($result['status'])->toBe(Maho_FeedManager_Model_Generator_Batch::STATUS_FINALIZING)
            ->and($processCalls)->toBe($job['batches_total'])
            ->and($result['processed'])->toBe($job['total_products'])
            ->and($result['progress'])->toBe(0)
            ->and($result['batch_products'])->toBe(0);
        expect(new Maho_FeedManager_Model_Generator_Batch()->finalize($job['job_id'])['status'])
            ->toBe(Maho_FeedManager_Model_Generator_Batch::STATUS_COMPLETED);
    });

    it('advances synchronous and AJAX visits for caught product errors below the threshold', function (): void {
        Mage::app()->getStore()->setConfig('feedmanager/general/batch_size', '2');
        $store = Mage::app()->getStore();
        $threshold = $store->getConfig('feedmanager/general/error_threshold_percent');
        $store->setConfig('feedmanager/general/error_threshold_percent', '100');
        $feed = ($this->createFeed)('json');
        expect(new FeedStorageCollectionCounter()->countProducts($feed))->toBeGreaterThan(0);

        try {
            $syncLog = new FeedStorageErrorGenerator()->generate($feed);
            expect($syncLog->getStatus())->toBe(Maho_FeedManager_Model_Log::STATUS_COMPLETED)
                ->and((int) $syncLog->getProductCount())->toBe(0)
                ->and($syncLog->getErrorMessagesArray())->not->toBeEmpty();

            $job = new Maho_FeedManager_Model_Generator_Batch()->initBatch($feed);
            $processCalls = 0;
            do {
                $result = new FeedStorageErrorBatch()->processBatch($job['job_id']);
                $processCalls++;
            } while ($result['status'] === Maho_FeedManager_Model_Generator_Batch::STATUS_PROCESSING && $processCalls < $job['batches_total']);
            $savedState = new Maho_FeedManager_Model_Generator_Batch()->getStatus($job['job_id']);

            expect($result['status'])->toBe(Maho_FeedManager_Model_Generator_Batch::STATUS_FINALIZING)
                ->and($processCalls)->toBe($job['batches_total'])
                ->and($result['processed'])->toBe($job['total_products'])
                ->and($result['progress'])->toBe(0)
                ->and($result['batch_products'])->toBe(0)
                ->and($savedState['errors'])->not->toBeEmpty();
            $final = new Maho_FeedManager_Model_Generator_Batch()->finalize($job['job_id']);
            expect($final['status'])->toBe(Maho_FeedManager_Model_Generator_Batch::STATUS_COMPLETED)
                ->and($final['errors'])->not->toBeEmpty();
        } finally {
            $store->setConfig('feedmanager/general/error_threshold_percent', (string) $threshold);
        }
    });

    it('preserves the no-visit error when closing the synchronous output also fails', function (): void {
        $feed = ($this->createFeed)('json');
        expect(new FeedStorageCollectionCounter()->countProducts($feed))->toBeGreaterThan(0);
        FeedStorageEmptyAfterCountGenerator::$collectionCalls = 0;
        FeedStorageCloseFailingGenerator::$closeCalls = 0;

        $log = new FeedStorageCloseFailingGenerator()->generate($feed);
        $errors = $log->getErrorMessagesArray();

        expect($log->getStatus())->toBe(Maho_FeedManager_Model_Log::STATUS_FAILED)
            ->and(FeedStorageCloseFailingGenerator::$closeCalls)->toBe(1)
            ->and($errors[0])->toContain('visited no products')
            ->and($errors[1])->toContain('Injected close failure');
    });

    it('attempts one batch pause when pausing a no-visit page fails', function (): void {
        $feed = ($this->createFeed)('json');
        $job = new Maho_FeedManager_Model_Generator_Batch()->initBatch($feed);
        expect($job['total_products'])->toBeGreaterThan(0);
        FeedStorageEmptyPageBatch::$emptyPage = true;
        FeedStoragePauseFailingBatch::$pauseCalls = 0;

        $batch = new FeedStoragePauseFailingBatch();
        $result = $batch->processBatch($job['job_id']);

        expect($result['status'])->toBe(Maho_FeedManager_Model_Generator_Batch::STATUS_FAILED)
            ->and($result['message'])->toContain('visited no products')
            ->and(FeedStoragePauseFailingBatch::$pauseCalls)->toBe(1)
            ->and($result['errors'][0])->toContain('Injected pause failure')
            ->and($result['errors'][1])->toContain('visited no products');
    });

    it('reports only the open failure when opening fails before it returns', function (): void {
        $feed = ($this->createFeed)('json');

        $log = new FeedStorageOpenFailingGenerator()->generate($feed);

        expect($log->getStatus())->toBe(Maho_FeedManager_Model_Log::STATUS_FAILED)
            ->and($log->getErrorMessagesArray())->toHaveCount(1)
            ->and($log->getErrorMessagesArray()[0])->toContain('Injected open failure');
    });

    it('reports only the resume failure when resuming fails before it returns', function (): void {
        $feed = ($this->createFeed)('json');
        $batch = new FeedStorageResumeFailingBatch();
        $job = $batch->initBatch($feed);

        $result = $batch->processBatch($job['job_id']);

        expect($result['status'])->toBe(Maho_FeedManager_Model_Generator_Batch::STATUS_FAILED)
            ->and($result['message'])->toContain('Injected resume failure')
            ->and($result['errors'])->toHaveCount(1);
    });

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
        $job = new Maho_FeedManager_Model_Generator_Batch()->getStatus($jobId);
        $processCalls = 0;
        do {
            $result = new Maho_FeedManager_Model_Generator_Batch()->processBatch($jobId);
            $processCalls++;
        } while ($result['status'] === Maho_FeedManager_Model_Generator_Batch::STATUS_PROCESSING && $processCalls < $job['batches_total']);
        expect($result['status'])->toBe(Maho_FeedManager_Model_Generator_Batch::STATUS_FINALIZING)
            ->and($processCalls)->toBe($job['batches_total']);
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
