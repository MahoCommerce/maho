<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_FeedManager
 */

declare(strict_types=1);

use League\Flysystem\Local\LocalFilesystemAdapter;
use Maho\Storage\Mount;
use Maho\Storage\MountRegistry;
use Maho\Storage\Url\StoreBaseUrlGenerator;
use MahoCLI\Commands\FeedGenerate;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

uses(Tests\MahoBackendTestCase::class);

/**
 * Maho_FeedManager_Service_Feed owns the generation, the upload and the reset of a feed (#1348).
 * feed:generate, the admin Generate buttons and the cron upload a feed that has auto upload on.
 */

/**
 * A spy of the service. It records each upload and does not send the file.
 */
function feedUploadSpy(): Maho_FeedManager_Service_Feed
{
    $spy = new class extends Maho_FeedManager_Service_Feed {
        /** @var list<string|null> the content of each uploaded file, null when the service reads the mount */
        public array $uploads = [];

        #[\Override]
        public function upload(
            Maho_FeedManager_Model_Feed $feed,
            ?Maho_FeedManager_Model_Log $log = null,
            ?string $localPath = null,
        ): array {
            $this->uploads[] = $localPath === null ? null : (string) file_get_contents($localPath);
            $log?->recordUploadSuccess((int) $feed->getDestinationId(), 'Spy upload');
            return ['status' => Maho_FeedManager_Model_Log::UPLOAD_STATUS_SUCCESS, 'message' => 'Spy upload'];
        }
    };
    Mage::unregister('_service/feedmanager/feed');
    Mage::register('_service/feedmanager/feed', $spy);
    return $spy;
}

function feedServiceAdminAction(string $action, array $params): Mage_Core_Controller_Response_Http
{
    Mage::getSingleton('adminhtml/session')->getMessages(true);
    $response = new Mage_Core_Controller_Response_Http();
    $request = new Mage_Core_Controller_Request_Http(
        SymfonyRequest::create('/admin/feedmanager_feed/' . $action, 'POST', $params),
    );
    new Maho_FeedManager_Adminhtml_Feedmanager_FeedController($request, $response)->{$action . 'Action'}();
    return $response;
}

function feedServiceJson(string $action, array $params): array
{
    return Mage::helper('core')->jsonDecode((string) feedServiceAdminAction($action, $params)->getBody());
}

function feedServiceLatestLog(Maho_FeedManager_Model_Feed $feed): Maho_FeedManager_Model_Log
{
    return Mage::getResourceModel('feedmanager/log_collection')
        ->addFeedFilter((int) $feed->getId())
        ->setOrder('log_id', 'DESC')
        ->getFirstItem();
}

beforeEach(function (): void {
    $this->root = sys_get_temp_dir() . '/maho_feed_service_' . uniqid();
    mkdir($this->root . '/media', 0777, true);
    mkdir($this->root . '/feeds', 0777, true);
    $this->media = new Mount('media', new LocalFilesystemAdapter($this->root . '/media'), $this->root . '/media', new StoreBaseUrlGenerator('media'));
    MountRegistry::register($this->media);
    MountRegistry::register(new Mount('feeds', new LocalFilesystemAdapter($this->root . '/feeds'), $this->root . '/feeds'));
    Mage::app()->getStore()->setConfig('feedmanager/general/output_directory', 'feeds');

    $this->destination = Mage::getModel('feedmanager/destination')
        ->setData(['name' => 'Service Test Destination', 'type' => Maho_FeedManager_Model_Destination::TYPE_SFTP, 'is_enabled' => 1])
        ->save();
    $this->createFeed = function (array $data = []): Maho_FeedManager_Model_Feed {
        $feed = Mage::getModel('feedmanager/feed')->setData($data + [
            'name' => 'Service Test Feed ' . uniqid(),
            'platform' => 'custom',
            'store_id' => 1,
            'is_enabled' => 1,
            'filename' => 'service-test-' . uniqid(),
            'file_format' => 'csv',
            'include_product_types' => 'virtual',
            'auto_upload' => 1,
            'destination_id' => (int) $this->destination->getId(),
        ])->save();
        Mage::getModel('feedmanager/attributeMapping')
            ->setData(['platform_attribute' => 'id', 'source_type' => 'attribute', 'source_value' => 'sku', 'sort_order' => 1])
            ->setFeedId((int) $feed->getId())
            ->save();
        return $this->feeds[] = $feed;
    };
    $this->feeds = [];
});

afterEach(function (): void {
    foreach ($this->feeds as $feed) {
        $feed->delete();
    }
    $this->destination->delete();
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

it('uploads a feed with auto upload on after feed:generate, the admin Generate buttons and the cron', function (): void {
    $spy = feedUploadSpy();
    $feed = ($this->createFeed)();
    $file = fn(): string => $this->media->read((string) $feed->getStoragePath());

    $command = new FeedGenerate('feed:generate');
    new Application()->addCommand($command);
    new CommandTester($command)->execute(['feed_id' => (string) $feed->getId()], ['interactive' => false]);
    $cli = $file();

    feedServiceAdminAction('massGenerate', ['feed_ids' => [$feed->getId()]]);
    $massGenerate = $file();

    $cron = new class extends Maho_FeedManager_Model_Cron {
        public function generateFeed(Maho_FeedManager_Model_Feed $feed): void
        {
            $this->_generateFeed($feed);
        }
    };
    $cron->generateFeed($feed);
    $cronFile = $file();

    $jobId = feedServiceJson('generateInit', ['id' => $feed->getId()])['job_id'];
    do {
        $batch = feedServiceJson('generateBatch', ['job_id' => $jobId]);
    } while ($batch['status'] === Maho_FeedManager_Model_Generator_Batch::STATUS_PROCESSING);
    $final = feedServiceJson('generateFinalize', ['job_id' => $jobId]);

    expect($spy->uploads)->toBe([$cli, $massGenerate, $cronFile, $file()])
        ->and($final['upload_status'])->toBe(Maho_FeedManager_Model_Log::UPLOAD_STATUS_SUCCESS)
        ->and(feedServiceLatestLog($feed)->getUploadMessage())->toBe('Spy upload');
});

it('records a skipped upload when auto upload is off', function (): void {
    $spy = feedUploadSpy();
    $feed = ($this->createFeed)(['auto_upload' => 0]);

    $log = Mage::getService('feedmanager/feed')->generate($feed);

    expect($log->getStatus())->toBe(Maho_FeedManager_Model_Log::STATUS_COMPLETED)
        ->and($spy->uploads)->toBe([])
        ->and($log->getUploadStatus())->toBe(Maho_FeedManager_Model_Log::UPLOAD_STATUS_SKIPPED)
        ->and($log->getUploadMessage())->toBe('Auto-upload disabled');
});

it('refuses a batch generation of a feed that another generation holds, unless the admin forces it', function (): void {
    $feed = ($this->createFeed)();
    $running = Mage::getModel('feedmanager/log')
        ->setFeedId((int) $feed->getId())
        ->setStatus(Maho_FeedManager_Model_Log::STATUS_RUNNING)
        ->setStartedAt(Mage::app()->getLocale()->formatDateForDb('now'))
        ->save();
    $message = "Feed '{$feed->getName()}' is already being generated";

    $init = feedServiceJson('generateInit', ['id' => $feed->getId()]);
    $mass = feedServiceJson('massBatchGenerate', ['feed_ids' => [$feed->getId()]]);
    $forced = feedServiceJson('generateInit', ['id' => $feed->getId(), 'force' => '1']);
    $running->load($running->getId());

    expect($init)->toMatchArray(['error' => true, 'message' => $message])
        ->and($mass['errors'])->toBe([$message])
        ->and($forced['success'])->toBeTrue()
        ->and($running->getStatus())->toBe(Maho_FeedManager_Model_Log::STATUS_FAILED)
        ->and($running->getErrorMessagesArray())->toBe(['Generation was reset'])
        ->and($running->getCompletedAt())->not->toBeNull();
});

it('resets only a generation that runs for longer than the timeout when the admin does not force it', function (): void {
    $feed = ($this->createFeed)();
    $log = fn(string $startedAt) => Mage::getModel('feedmanager/log')
        ->setFeedId((int) $feed->getId())
        ->setStatus(Maho_FeedManager_Model_Log::STATUS_RUNNING)
        ->setStartedAt(Mage::app()->getLocale()->formatDateForDb($startedAt))
        ->save();
    $hung = $log('-31 minutes');
    $live = $log('-5 minutes');
    $cron = new class extends Maho_FeedManager_Model_Cron {
        public function cleanup(): void
        {
            $this->_cleanupHungFeeds();
        }
    };

    $cron->cleanup();

    expect($hung->load($hung->getId())->getStatus())->toBe(Maho_FeedManager_Model_Log::STATUS_FAILED)
        ->and($hung->getErrorMessagesArray())->toBe(['Feed generation timed out (exceeded 30 minutes)'])
        ->and($live->load($live->getId())->getStatus())->toBe(Maho_FeedManager_Model_Log::STATUS_RUNNING);
});

it('gives the manual upload the same checks and the same log record as the generation upload', function (): void {
    $feed = ($this->createFeed)(['auto_upload' => 0]);

    $noFile = feedServiceJson('upload', ['id' => $feed->getId()]);
    Mage::getService('feedmanager/feed')->generate($feed);
    $this->destination->setIsEnabled(0)->save();
    $disabled = feedServiceJson('upload', ['id' => $feed->getId()]);
    $log = feedServiceLatestLog($feed);

    expect($noFile)->toBe(['error' => true, 'message' => 'Feed file not found. Please generate the feed first.'])
        ->and($disabled)->toBe(['error' => true, 'message' => 'Destination is disabled'])
        ->and($log->getUploadStatus())->toBe(Maho_FeedManager_Model_Log::UPLOAD_STATUS_FAILED)
        ->and($log->getUploadMessage())->toBe('Destination is disabled');
});
