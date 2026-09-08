<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package MahoCLI
 */

declare(strict_types=1);

namespace MahoCLI\Commands;

use Maho\Import\Importer\Products;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'import:products',
    description: 'Import products from a CSV file in the Import/Export layout, with pictures from a media folder',
)]
class ImportProducts extends BaseMahoCommand
{
    use ImportCommandTrait;

    protected bool $traceWholeCommand = false;

    #[\Override]
    protected function configure(): void
    {
        $this->addArgument('csv', InputArgument::REQUIRED, 'Path to products.csv (sku, _attribute_set, _type, _product_websites, _root_category, _category, ...)');
        $this->addOption('behavior', null, InputOption::VALUE_REQUIRED, 'append, replace or delete', 'append');
        $this->addOption('media-dir', null, InputOption::VALUE_REQUIRED, 'Folder the _media_image paths are relative to (default: media/import next to the CSV)');
        $this->addOption('trusted-media', null, InputOption::VALUE_NONE, 'Store the pictures as they are, without the security re-encode; only for a media folder you placed on the server yourself');
        $this->addDryRunOption();
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->initMaho();
        $options = [Products::OPTION_BEHAVIOR => $input->getOption('behavior')];
        if ($input->getOption('media-dir') !== null) {
            $options[Products::OPTION_MEDIA_DIR] = $input->getOption('media-dir');
        }
        if ($input->getOption('trusted-media')) {
            $options[Products::OPTION_TRUSTED_MEDIA] = true;
        }
        return $this->runImport(new Products(), $input->getArgument('csv'), $options, (bool) $input->getOption('dry-run'), $output);
    }
}
