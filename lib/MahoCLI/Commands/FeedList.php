<?php

/**
 * Maho
 *
 * @package    MahoCLI
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace MahoCLI\Commands;

use Mage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'feed:list',
    description: 'List all feeds with status',
)]
class FeedList extends BaseMahoCommand
{
    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->initMaho();

        $collection = Mage::getModel('feedmanager/feed')->getCollection();

        if ($collection->getSize() === 0) {
            $output->writeln('<info>No feeds configured.</info>');
            return Command::SUCCESS;
        }

        $table = new Table($output);
        $table->setHeaders(['ID', 'Name', 'Platform', 'Format', 'Enabled', 'Last Generated', 'Products', 'File Size']);

        foreach ($collection as $feed) {
            $lastGenerated = $feed->getLastGeneratedAt();
            $fileSize = $feed->getLastFileSize();

            $table->addRow([
                $feed->getId(),
                $feed->getName(),
                $feed->getPlatform() ?: 'custom',
                $feed->getFileFormat() ?: 'xml',
                $feed->getIsEnabled() ? '<info>Yes</info>' : '<comment>No</comment>',
                $lastGenerated ?: '<comment>Never</comment>',
                $feed->getLastProductCount() ?: '-',
                $fileSize ? $this->humanReadableSize((int) $fileSize) : '-',
            ]);
        }

        $table->render();

        return Command::SUCCESS;
    }
}
