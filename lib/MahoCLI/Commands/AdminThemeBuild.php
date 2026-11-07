<?php

/**
 * Compiles admin theme CSS sources (src/) into their deployable css/ bundles.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package MahoCLI
 */

declare(strict_types=1);

namespace MahoCLI\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'dev:admin:theme:build',
    description: 'Compile the CSS sources (src/*.css) of every admin theme into their css/ bundles',
)]
class AdminThemeBuild extends AbstractThemeBuild
{
    protected const SKIN_PATH = 'public/skin/adminhtml';

    public function __invoke(
        InputInterface $input,
        OutputInterface $output,
        SymfonyStyle $io,
        #[Option(description: 'Limit the build to one theme instead of all, as package/theme (e.g. --theme default/default)', name: 'theme', shortcut: 't')]
        ?string $themeFilter = null,
        #[Option(description: 'Rebuild on change; output is unminified, run a plain build before committing', shortcut: 'w')]
        bool $watch = false,
        #[Option(description: 'Add an inline source map, so devtools show the src/ partials instead of the bundle', name: 'map')]
        bool $sourceMap = false,
        #[Option(description: 'Install the Tailwind CSS toolchain with no question when it is missing', shortcut: 'f')]
        bool $force = false,
    ): int {
        return $this->compileThemes($input, $output, $io, $themeFilter, $watch, $sourceMap, false, $force);
    }
}
