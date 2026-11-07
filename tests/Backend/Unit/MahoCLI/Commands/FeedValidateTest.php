<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package MahoCLI
 */

declare(strict_types=1);

use MahoCLI\Commands\FeedValidate;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

uses(Tests\MahoBackendTestCase::class);

it('validates a feed with the delimiter of the feed', function (): void {
    $feed = Mage::getModel('feedmanager/feed')
        ->setName('unit_test_feed_validate')
        ->setPlatform('custom')
        ->setStoreId(1)
        ->setFilename('unit_test_feed_validate')
        ->setFileFormat('csv')
        ->setCsvDelimiter('|')
        ->setCsvEnclosure('"')
        ->save();

    try {
        Mage::helper('feedmanager')->getOutputMount()->write($feed->getStoragePath(), "id|title\nA,B|x\n");

        $command = new FeedValidate('feed:validate');
        $application = new Application();
        $application->addCommand($command);
        $tester = new CommandTester($command);
        $tester->execute(['feed_id' => (int) $feed->getId()]);

        expect($tester->getStatusCode())->toBe(Command::SUCCESS)
            ->and($tester->getDisplay())->toContain('Feed is valid!');
    } finally {
        $feed->deleteFile();
        $feed->delete();
    }
});
