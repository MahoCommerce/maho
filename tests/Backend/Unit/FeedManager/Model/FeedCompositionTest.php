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

/** Limits the real product collection to this test's saved products. */
trait FeedCompositionProducts
{
    /** @var list<int> */
    public array $productIds = [];
    /** @var list<int> */
    public array $visitedIds = [];
    /** @var list<int> */
    public array $writtenIds = [];
    public ?Mage_Catalog_Model_Resource_Product_Collection $lastCollection = null;

    #[\Override]
    protected function _getProductCollection(): Mage_Catalog_Model_Resource_Product_Collection
    {
        $collection = parent::_getProductCollection();
        $collection->addIdFilter($this->productIds)->addAttributeToSort('entity_id', 'ASC');
        $this->lastCollection = $collection;
        return $collection;
    }

    #[\Override]
    protected function _validateProductConditions(Mage_Catalog_Model_Product $product): bool
    {
        $this->visitedIds[] = (int) $product->getId();
        return parent::_validateProductConditions($product);
    }

    #[\Override]
    protected function _writeProduct(Mage_Catalog_Model_Product $product): void
    {
        parent::_writeProduct($product);
        $this->writtenIds[] = (int) $product->getId();
    }
}

class FeedCompositionGenerator extends Maho_FeedManager_Model_Generator
{
    use FeedCompositionProducts;
}

class FeedCompositionBatch extends Maho_FeedManager_Model_Generator_Batch
{
    use FeedCompositionProducts;

    /** @var list<array<string, int>> */
    public array $pages = [];
    public int $publishCalls = 0;

    #[\Override]
    protected function _processOneBatch(int $page): int
    {
        $written = parent::_processOneBatch($page);
        $this->pages[] = [
            'requested' => $page,
            'effective' => $this->lastCollection->getCurPage(),
            'processed' => $this->_processedCount,
            'written' => $this->_productCount,
            'errors' => $this->_errorCount,
        ];
        return $written;
    }

    #[\Override]
    protected function _validateAndPublish(string $tempPath, array &$errors): string
    {
        $this->publishCalls++;
        return parent::_validateAndPublish($tempPath, $errors);
    }
}

describe('Feed generation with saved products and mappings', function (): void {
    beforeEach(function (): void {
        $this->root = sys_get_temp_dir() . '/maho_feeds_' . uniqid();
        mkdir($this->root . '/media', 0777, true);
        mkdir($this->root . '/feeds', 0777, true);
        $this->media = new Mount('media', new LocalFilesystemAdapter($this->root . '/media'), $this->root . '/media', new StoreBaseUrlGenerator('media'));
        $this->feeds = new Mount('feeds', new LocalFilesystemAdapter($this->root . '/feeds'), $this->root . '/feeds');
        MountRegistry::register($this->media);
        MountRegistry::register($this->feeds);
        $store = Mage::app()->getStore();
        $this->previousConfig = [];
        foreach (['feedmanager/general/batch_size' => '2', 'feedmanager/general/output_directory' => 'feeds'] as $path => $value) {
            $this->previousConfig[$path] = $store->getConfig($path);
            $store->setConfig($path, $value);
        }
        $coreWrite = Mage::getSingleton('core/resource')->getConnection('core_write');
        $this->feedResource = Mage::getResourceModel('feedmanager/feed');
        foreach ([$this->feedResource, Mage::getResourceModel('catalog/product')] as $resource) {
            expect(new ReflectionMethod($resource, '_getWriteAdapter')->invoke($resource))->toBe($coreWrite);
        }
        expect(Mage::getSingleton('core/resource')->getConnection('feedmanager_write'))->toBe($coreWrite);
        $this->feedResource->beginTransaction();

        $this->createProduct = function (string $label): Mage_Catalog_Model_Product {
            $product = Mage::getModel('catalog/product');
            $product->setStoreId(0)->setTypeId('virtual')->setAttributeSetId($product->getDefaultAttributeSetId())
                ->setSku('feed-composition-' . $label . '-' . uniqid())->setName('Synthetic feed product ' . $label)
                ->setDescription('Synthetic feed description')->setPrice(100)->setSpecialPrice(50)
                ->setSpecialFromDate('2020-01-01')->setSpecialToDate('2099-12-31')
                ->setTaxClassId(0)->setStatus(Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
                ->setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH)->setWebsiteIds([1])
                ->setStockData(['use_config_manage_stock' => 0, 'manage_stock' => 0, 'is_in_stock' => 1, 'qty' => 10])
                ->save();
            return $product;
        };
        $this->createFeed = (fn(string $format, string $platform): Maho_FeedManager_Model_Feed => Mage::getModel('feedmanager/feed')->setData([
            'name' => 'Synthetic feed composition', 'platform' => $platform, 'store_id' => 1,
            'is_enabled' => 0, 'filename' => 'feed-composition-' . uniqid(), 'file_format' => $format,
            'configurable_mode' => 'simple_only', 'include_product_types' => 'virtual',
            'exclude_disabled' => 1, 'exclude_out_of_stock' => 0, 'auto_upload' => 0,
            'notification_mode' => 'none', 'tax_mode' => 'excl', 'price_currency' => 'USD',
            'csv_include_header' => 1, 'csv_delimiter' => ',', 'csv_enclosure' => '"',
        ])->save());
        $this->generateBoth = function (Maho_FeedManager_Model_Feed $feed, int $productId): array {
            $generator = new FeedCompositionGenerator();
            $generator->productIds = [$productId];
            $log = $generator->generate($feed);
            expect($log->getStatus())->toBe(Maho_FeedManager_Model_Log::STATUS_COMPLETED)
                ->and($generator->visitedIds)->toBe([$productId])
                ->and($generator->writtenIds)->toBe([$productId]);
            $path = (string) $feed->getStoragePath();
            $single = $this->media->read($path);
            $batch = new FeedCompositionBatch();
            $batch->productIds = [$productId];
            $job = $batch->initBatch($feed);
            expect($job['total_products'])->toBe(1)->and($job['batches_total'])->toBe(1);
            // One processing call and one finalization call are sufficient for this one-product job.
            $page = new FeedCompositionBatch();
            $page->productIds = [$productId];
            $result = $page->processBatch($job['job_id']);
            expect($result['status'])->toBe(Maho_FeedManager_Model_Generator_Batch::STATUS_FINALIZING)
                ->and($page->visitedIds)->toBe([$productId])->and($page->writtenIds)->toBe([$productId]);
            $final = new FeedCompositionBatch()->finalize($job['job_id']);
            expect($final['status'])->toBe(Maho_FeedManager_Model_Generator_Batch::STATUS_COMPLETED)
                ->and($this->feeds->directoryExists($job['job_id']))->toBeFalse();
            return [$single, $this->media->read($path)];
        };
    });

    afterEach(function (): void {
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

    it('rejects a page clamped to nonempty survivors without replacing the published feed', function (): void {
        $products = array_map($this->createProduct, ['A', 'B', 'C', 'D']);
        $ids = array_map(fn(Mage_Catalog_Model_Product $product): int => (int) $product->getId(), $products);
        $feed = ($this->createFeed)('json', 'custom');
        foreach (['id' => 'sku', 'title' => 'name', 'price' => 'price'] as $field => $attribute) {
            Mage::getModel('feedmanager/attributeMapping')->setFeedId((int) $feed->getId())
                ->setData('platform_attribute', $field)->setData('source_type', 'attribute')
                ->setData('source_value', $attribute)->save();
        }
        $feed->setLastGeneratedAt('2026-11-07 12:00:00')->setLastProductCount(19)->setLastFileSize(321)->save();
        $path = (string) $feed->getStoragePath();
        $this->media->write($path, 'previous feed bytes');
        $batch = new FeedCompositionBatch();
        $batch->productIds = $ids;
        $job = $batch->initBatch($feed);
        expect($job['total_products'])->toBe(4)->and($job['batches_total'])->toBe(2);
        $first = $batch->processBatch($job['job_id']);
        expect($first['status'])->toBe(Maho_FeedManager_Model_Generator_Batch::STATUS_PROCESSING)
            ->and($batch->visitedIds)->toBe([$ids[0], $ids[1]])
            ->and($batch->writtenIds)->toBe([$ids[0], $ids[1]]);
        $before = json_decode($this->feeds->read($job['job_id'] . '/state.json'), true, flags: JSON_THROW_ON_ERROR);
        // Reload saved products so this status change does not reuse their stock creation data.
        Mage::getModel('catalog/product')->setStoreId(0)->load($ids[0])->setStatus(Mage_Catalog_Model_Product_Status::STATUS_DISABLED)->save();
        Mage::getModel('catalog/product')->setStoreId(0)->load($ids[2])->setStatus(Mage_Catalog_Model_Product_Status::STATUS_DISABLED)->save();
        $survivors = Mage::getResourceModel('catalog/product_collection')->setStoreId(1)
            ->addIdFilter($ids)->addAttributeToFilter('status', Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
            ->addAttributeToSort('entity_id', 'ASC')->getAllIds();
        expect(array_map(intval(...), $survivors))->toBe([$ids[1], $ids[3]]);
        $second = new FeedCompositionBatch();
        $second->productIds = $ids;
        $result = $second->processBatch($job['job_id']);
        $after = json_decode($this->feeds->read($job['job_id'] . '/state.json'), true, flags: JSON_THROW_ON_ERROR);
        $savedPart = $this->feeds->fileExists($job['job_id'] . '/part-000002.json');
        // Exactly two process calls and one finalize call, including against the unfixed code.
        $final = $second->finalize($job['job_id']);
        $reloaded = Mage::getModel('feedmanager/feed')->load($feed->getId());

        expect($second->pages)->toBe([['requested' => 2, 'effective' => 1, 'processed' => 2, 'written' => 2, 'errors' => 0]])
            ->and($second->visitedIds)->toBe([])->and($second->writtenIds)->toBe([])
            ->and($result['status'])->toBe(Maho_FeedManager_Model_Generator_Batch::STATUS_FAILED)
            ->and($result['message'])->toContain('visited no products')
            ->and($savedPart)->toBeFalse()
            ->and($final['status'])->toBe(Maho_FeedManager_Model_Generator_Batch::STATUS_FAILED)
            ->and($second->publishCalls)->toBe(0)
            ->and($this->media->read($path))->toBe('previous feed bytes')
            ->and($reloaded->getLastGeneratedAt())->toBe('2026-11-07 12:00:00')
            ->and((int) $reloaded->getLastProductCount())->toBe(19)
            ->and((int) $reloaded->getLastFileSize())->toBe(321);
        foreach (['processed_count', 'product_count', 'error_count', 'batches_processed', 'writer_state'] as $counter) {
            expect($after[$counter])->toBe($before[$counter]);
        }
    });

    it('generates saved Google CSV prices and bounded text in synchronous and AJAX output', function (): void {
        $product = ($this->createProduct)('CSV');
        $feed = ($this->createFeed)('csv', 'google');
        $defaults = new Maho_FeedManager_Model_Platform_Google()->getDefaultMappings();
        $columns = [];
        foreach (['id', 'price', 'sale_price', 'sale_price_effective_date'] as $field) {
            $columns[] = ['name' => $field] + $defaults[$field];
        }
        foreach (['short' => ['abcdef', 2], 'equal' => ['abcdef', 3], 'ordinary' => ['abcdefghij', 7]] as $name => [$value, $limit]) {
            $columns[] = ['name' => $name, 'source_type' => 'static', 'source_value' => $value,
                'transformers' => "truncate:max_length={$limit},suffix=...,word_boundary=0"];
        }
        $feed->setCsvColumns(json_encode($columns, JSON_THROW_ON_ERROR))->save();
        $feed = Mage::getModel('feedmanager/feed')->load($feed->getId());
        expect(json_decode($feed->getCsvColumns(), true, flags: JSON_THROW_ON_ERROR))->toBe($columns);
        foreach (($this->generateBoth)($feed, (int) $product->getId()) as $output) {
            $lines = array_values(array_filter(explode("\n", trim($output)), strlen(...)));
            expect($lines)->toHaveCount(2);
            $header = str_getcsv($lines[0], ',', '"', '');
            $row = array_combine($header, str_getcsv($lines[1], ',', '"', ''));
            expect($row)->toBe(['id' => $product->getSku(), 'price' => '100.00 USD', 'sale_price' => '50.00 USD',
                'sale_price_effective_date' => '', 'short' => 'ab', 'equal' => '...', 'ordinary' => 'abcd...']);
        }
    });

    it('generates saved Google XML prices in synchronous and AJAX output', function (): void {
        $product = ($this->createProduct)('XML');
        $feed = ($this->createFeed)('xml', 'google');
        $structure = new Maho_FeedManager_Model_Platform_Google()->getDefaultXmlStructure();
        $feed->setXmlStructure(json_encode($structure, JSON_THROW_ON_ERROR))->setXmlItemTag('entry')
            ->setXmlHeader('<?xml version="1.0" encoding="UTF-8"?><feed xmlns="http://www.w3.org/2005/Atom" xmlns:g="http://base.google.com/ns/1.0">')
            ->setXmlFooter('</feed>')->save();
        $feed = Mage::getModel('feedmanager/feed')->load($feed->getId());
        expect(json_decode($feed->getXmlStructure(), true, flags: JSON_THROW_ON_ERROR))->toBe($structure);
        foreach (($this->generateBoth)($feed, (int) $product->getId()) as $output) {
            $xml = simplexml_load_string($output, SimpleXMLElement::class, LIBXML_NONET);
            expect($xml)->not->toBeFalse();
            $xml->registerXPathNamespace('atom', 'http://www.w3.org/2005/Atom');
            $entries = $xml->xpath('/atom:feed/atom:entry');
            expect($entries)->toHaveCount(1);
            $fields = $entries[0]->children('http://base.google.com/ns/1.0');
            expect((string) $fields->id)->toBe($product->getSku())
                ->and((string) $fields->price)->toBe('100.00 USD')
                ->and((string) $fields->sale_price)->toBe('50.00 USD')
                ->and((string) $fields->sale_price_effective_date)->toBe('');
        }
    });
});
