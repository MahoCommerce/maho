<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_FeedManager
 */

declare(strict_types=1);

use MahoCLI\Commands\FeedGenerate;
use MahoCLI\Commands\FeedGenerateAll;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

uses(Tests\MahoBackendTestCase::class);

/**
 * The admin grid, feed:generate, feed:generate:all and the cron generate a feed through
 * Maho_FeedManager_Service_Feed::generate() (#1348). The service refuses a feed that another generation holds.
 */

/**
 * A spy of the service. It runs the rules of the service for the feed of the test, and gives a completed log for
 * any other feed of the store, so that the test generates no real file.
 */
function feedGenerateSpy(int $feedId): Maho_FeedManager_Service_Feed
{
    $spy = new class ($feedId) extends Maho_FeedManager_Service_Feed {
        /** @var list<int> */
        public array $feedIds = [];

        public function __construct(private readonly int $testFeedId) {}

        #[\Override]
        public function generate(Maho_FeedManager_Model_Feed $feed, ?callable $afterPublish = null): Maho_FeedManager_Model_Log
        {
            $this->feedIds[] = (int) $feed->getId();
            if ((int) $feed->getId() !== $this->testFeedId) {
                return Mage::getModel('feedmanager/log')->setStatus(Maho_FeedManager_Model_Log::STATUS_COMPLETED);
            }
            return parent::generate($feed, $afterPublish);
        }
    };
    Mage::unregister('_service/feedmanager/feed');
    Mage::register('_service/feedmanager/feed', $spy);
    return $spy;
}

/**
 * @return array{status: int, output: string}
 */
function feedGenerateViaCli(Command $command, array $input): array
{
    new Application()->addCommand($command);
    $tester = new CommandTester($command);
    $status = $tester->execute($input, ['interactive' => false]);
    return ['status' => $status, 'output' => $tester->getDisplay()];
}

/**
 * @return list<string> the notices and the errors of the admin session
 */
function feedGenerateViaAdmin(int $feedId): array
{
    Mage::getSingleton('adminhtml/session')->getMessages(true);
    $request = new Mage_Core_Controller_Request_Http(
        SymfonyRequest::create('/admin/feedmanager_feed/massGenerate', 'POST', ['feed_ids' => [$feedId]]),
    );
    new Maho_FeedManager_Adminhtml_Feedmanager_FeedController($request, new Mage_Core_Controller_Response_Http())
        ->massGenerateAction();

    $messages = [];
    foreach (Mage::getSingleton('adminhtml/session')->getMessages(true)->getItems() as $message) {
        $messages[] = $message->getType() . ': ' . $message->getText();
    }
    return $messages;
}

beforeEach(function () {
    $this->feed = Mage::getModel('feedmanager/feed')->setName('Parity feed ' . uniqid())
        ->setPlatform('custom')->setStoreId(1)->setFileFormat('csv')
        ->setFilename('parity-feed-' . uniqid())->setIsEnabled(1)->setSchedule('hourly')->save();
    $this->runningLog = Mage::getModel('feedmanager/log')
        ->setFeedId((int) $this->feed->getId())
        ->setStatus(Maho_FeedManager_Model_Log::STATUS_RUNNING)
        ->setStartedAt(Mage::app()->getLocale()->formatDateForDb('now'))
        ->save();
});

afterEach(function () {
    $this->runningLog->delete();
    $this->feed->delete();
});

it('refuses a feed that another generation holds on the admin, the CLI and the cron', function () {
    $spy = feedGenerateSpy((int) $this->feed->getId());
    $message = "Feed '{$this->feed->getName()}' is already being generated";

    $admin = feedGenerateViaAdmin((int) $this->feed->getId());
    $one = feedGenerateViaCli(new FeedGenerate('feed:generate'), ['feed_id' => (string) $this->feed->getId()]);
    $all = feedGenerateViaCli(new FeedGenerateAll('feed:generate:all'), []);
    Mage::app()->getStore()->setConfig(Maho_FeedManager_Helper_Data::XML_PATH_ENABLED, '1');
    Mage::getModel('feedmanager/cron')->generateScheduledFeeds();

    expect($admin)->toBe([Mage_Core_Model_Message::NOTICE . ': ' . $message])
        ->and($one['status'])->toBe(Command::FAILURE)
        ->and($one['output'])->toContain("Error: {$message}")
        ->and($all['status'])->toBe(Command::FAILURE)
        ->and($all['output'])->toContain($message)
        ->and(array_count_values($spy->feedIds)[(int) $this->feed->getId()])->toBe(4);

    $logs = Mage::getResourceModel('feedmanager/log_collection')->addFeedFilter((int) $this->feed->getId());
    expect($logs->getSize())->toBe(1);
});
