<?php

/**
 * Maho
 *
 * @package    MahoCLI
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace MahoCLI\Commands;

use Mage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'cache:minify:flush',
    description: 'Flush minified CSS/JS cache',
)]
class CacheMinifyFlush extends BaseMahoCommand
{
    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->initMaho();

        $minifyHelper = Mage::helper('core/minify');
        $minifyHelper->clearCache();

        $output->writeln('Minified CSS/JS cache flushed successfully!');
        return Command::SUCCESS;
    }
}
