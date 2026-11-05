<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace MahoCLI\Commands;

use MahoCLI\Helper\TailwindToolchain;
use MahoCLI\Helper\ThemeContentReport;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'theme:content:build',
    description: 'Compile the Tailwind classes of the CMS, blog and catalog content into a copy of the css/styles.css of each theme',
)]
class ThemeContentBuild extends BaseMahoCommand
{
    public function __invoke(
        InputInterface $input,
        SymfonyStyle $io,
        #[Option(description: 'Install the Tailwind CSS toolchain with no question when it is missing', shortcut: 'f')]
        bool $force = false,
    ): int {
        $this->initMaho();

        if (TailwindToolchain::resolveBinary($io, $input->isInteractive(), $force) === null) {
            $io->text('Until then, the store serves the committed css/styles.css.');
            return Command::FAILURE;
        }

        return ThemeContentReport::build($io);
    }
}
