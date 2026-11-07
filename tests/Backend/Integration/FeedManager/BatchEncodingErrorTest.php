<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_FeedManager
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

it('keeps product encoding errors when the batch exceeds its error threshold', function (): void {
    if (Mage::getResourceModel('catalog/product_collection')->getSize() < 10) {
        $this->markTestSkipped('At least ten sample products are needed for the error threshold');
    }

    $store = Mage::app()->getStore();
    $threshold = $store->getConfig('feedmanager/general/error_threshold_percent');
    $store->setConfig('feedmanager/general/error_threshold_percent', 10);
    $feed = Mage::getModel('feedmanager/feed')->setName('JSON batch error test')
        ->setPlatform('custom')->setStoreId(1)->setFileFormat('json')
        ->setFilename('json-batch-error-' . uniqid())->setExcludeOutOfStock(0)->save();
    $batch = new class extends Maho_FeedManager_Model_Generator_Batch {
        #[\Override]
        protected function _writeProduct(Mage_Catalog_Model_Product $product): void
        {
            $this->_writer->writeProduct(['sku' => $product->getSku(), 'value' => INF]);
        }
    };
    $job = null;

    try {
        $job = $batch->initBatch($feed);
        $result = $batch->processBatch($job['job_id']);
        $status = new Maho_FeedManager_Model_Generator_Batch()->getStatus($job['job_id']);
        $log = Mage::getModel('feedmanager/log')->load($job['log_id']);

        expect($result['message'])->toContain('Error threshold exceeded');
        expect($result['status'])->toBe(Maho_FeedManager_Model_Generator_Batch::STATUS_FAILED)
            ->and($result['errors'])->toHaveCount(11)
            ->and($result['errors'][0])->toStartWith('Product ')
            ->and($result['errors'][0])->toContain('Inf and NaN cannot be JSON encoded')
            ->and($result['errors'][10])->toContain('Error threshold exceeded')
            ->and($status['errors'])->toBe($result['errors'])
            ->and($log->getErrorMessagesArray())->toBe($result['errors']);
    } finally {
        if ($job !== null) {
            $batch->cancel($job['job_id']);
            Mage::getModel('feedmanager/log')->load($job['log_id'])->delete();
        }
        $feed->delete();
        $store->setConfig('feedmanager/general/error_threshold_percent', $threshold);
    }
});
