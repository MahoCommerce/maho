<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package MahoCLI
 */

declare(strict_types=1);

namespace MahoCLI\Commands;

use Maho\Import\Importer\Products;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'import:products',
    description: 'Import products from a CSV file in the Import/Export layout, with pictures from a media folder',
)]
class ImportProducts extends BaseMahoCommand
{
    use ImportCommandTrait;

    #[\Override]
    protected bool $traceWholeCommand = false;

    public function __invoke(
        OutputInterface $output,
        #[Argument(description: 'Path to products.csv (sku, _attribute_set, _type, _product_websites, _root_category, _category, ...)')]
        string $csv,
        #[Option(description: 'append, replace or delete')]
        string $behavior = 'append',
        #[Option(description: 'Folder the _media_image paths are relative to (default: media/import next to the CSV)')]
        ?string $mediaDir = null,
        #[Option(description: 'Store the pictures as they are, without the security re-encode; only for a media folder you placed on the server yourself')]
        bool $trustedMedia = false,
        #[Option(description: self::DRY_RUN_DESCRIPTION)]
        bool $dryRun = false,
    ): int {
        $this->initMaho();
        $options = [Products::OPTION_BEHAVIOR => $behavior];
        if ($mediaDir !== null) {
            $options[Products::OPTION_MEDIA_DIR] = $mediaDir;
        }
        if ($trustedMedia) {
            $options[Products::OPTION_TRUSTED_MEDIA] = true;
        }
        return $this->runImport(new Products(), $csv, $options, $dryRun, $output);
    }
}
