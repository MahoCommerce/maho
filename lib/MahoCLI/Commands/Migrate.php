<?php

/**
 * Maho
 *
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace MahoCLI\Commands;

use Mage;
use Mage_Core_Model_Resource_Setup;
use Maho\Db\Schema\Applier;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'migrate',
    description: 'Apply pending database schema and data updates from modules',
)]
class Migrate extends BaseMahoCommand
{
    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->initMaho();

        $output->writeln('<info>Applying pending updates...</info>');

        // Replicate the exact logic from CacheController::flushSystemAction()
        Mage::app()->getCache()->banUse('config');
        Mage::getConfig()->reinit();
        Mage::getConfig()->getCacheSaveLock(30, true);

        try {
            Mage::app()->cleanCache();
            $output->writeln('✓ Cleaned cache');

            /** @var \Maho\Db\Adapter\AdapterInterface $adapter */
            $adapter = Mage::getSingleton('core/resource')->getConnection('core_setup');
            $result = Applier::applyAll($adapter->getConnection());
            if ($result['contributors'] === []) {
                $output->writeln('✓ Applied declarative schema (no modules declare etc/db_schema.php)');
            } elseif ($result['executed'] === []) {
                $output->writeln(sprintf(
                    '✓ Applied declarative schema (%d module(s), already up to date)',
                    count($result['contributors']),
                ));
            } else {
                $output->writeln(sprintf(
                    '✓ Applied declarative schema (%d module(s), %d statement(s) executed)',
                    count($result['contributors']),
                    count($result['executed']),
                ));
            }

            Mage_Core_Model_Resource_Setup::applyAllUpdates();
            $output->writeln('✓ Applied legacy schema/data updates');

            Mage_Core_Model_Resource_Setup::applyAllDataUpdates();
            $output->writeln('✓ Applied data updates');

            Mage_Core_Model_Resource_Setup::applyAllMahoUpdates();
            $output->writeln('✓ Applied Maho updates');

            Mage::app()->getCache()->unbanUse('config');
            Mage::getConfig()->saveCache();
            $output->writeln('✓ Saved cache configuration');
        } finally {
            Mage::getConfig()->releaseCacheSaveLock();
        }

        Mage::dispatchEvent('adminhtml_cache_flush_system');

        $output->writeln('<info>All updates applied successfully!</info>');
        return Command::SUCCESS;
    }
}
