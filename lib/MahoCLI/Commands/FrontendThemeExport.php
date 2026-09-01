<?php

/**
 * Writes the design tokens a store configured in the admin back out as a theme.css file.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package MahoCLI
 */

declare(strict_types=1);

namespace MahoCLI\Commands;

use Mage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'dev:frontend:theme:export',
    description: 'Write a store\'s admin theme settings to a theme.css file, so they can be committed',
)]
class FrontendThemeExport extends BaseMahoCommand
{
    private const SKIN_PATH = 'public/skin/frontend';

    #[\Override]
    protected function configure(): void
    {
        $this
            ->addOption('theme', 't', InputOption::VALUE_REQUIRED, 'Where to write, as package/theme (e.g. --theme maho/pharmacy)')
            ->addOption('store', 's', InputOption::VALUE_REQUIRED, 'Read the settings of this store view instead of the default scope')
            ->addOption('stdout', null, InputOption::VALUE_NONE, 'Print the file instead of writing it')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Overwrite an existing theme.css');
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->initMaho();
        $io = new SymfonyStyle($input, $output);

        $storeCode = $input->getOption('store');
        try {
            $store = Mage::app()->getStore($storeCode ?? 0);
        } catch (\Throwable) {
            $io->error("Unknown store view '{$storeCode}'. List them with ./maho store:list.");
            return Command::FAILURE;
        }

        $vars = Mage::getModel('core/design_tokens')->resolve((int) $store->getId());
        if (!$vars) {
            $io->warning([
                'This scope has no theme settings, so there is nothing to export.',
                'Set them in System > Configuration > Design > Theme Settings first.',
            ]);
            return Command::SUCCESS;
        }

        $css = $this->render($vars, $storeCode === null ? 'the default scope' : "store view '{$store->getCode()}'");

        if ($input->getOption('stdout')) {
            $output->write($css);
            return Command::SUCCESS;
        }

        $theme = (string) $input->getOption('theme');
        if (!preg_match('#^[a-z0-9_-]+/[a-z0-9_-]+$#i', $theme)) {
            $io->error('Pass the destination as --theme package/theme, for example --theme maho/pharmacy.');
            return Command::FAILURE;
        }

        $file = MAHO_ROOT_DIR . '/' . self::SKIN_PATH . "/{$theme}/css/theme.css";
        if (is_file($file) && !$input->getOption('force')) {
            $io->error([
                $this->relativePath($file) . ' already exists.',
                'Pass --force to overwrite it, or --stdout to review the output first.',
            ]);
            return Command::FAILURE;
        }

        if (!is_dir(dirname($file)) && !mkdir(dirname($file), 0755, true)) {
            $io->error('Could not create ' . $this->relativePath(dirname($file)) . '.');
            return Command::FAILURE;
        }
        if (file_put_contents($file, $css) === false) {
            $io->error('Could not write ' . $this->relativePath($file) . '.');
            return Command::FAILURE;
        }

        $io->success([
            'Wrote ' . count($vars) . ' variables to ' . $this->relativePath($file) . '.',
            'Commit it, then clear the fields in System > Configuration > Design > Theme Settings.',
        ]);
        return Command::SUCCESS;
    }

    /**
     * @param array<string, string> $vars
     */
    private function render(array $vars, string $scope): string
    {
        $year = date('Y');
        $lines = [
            '/*',
            " * Exported from the theme settings of {$scope}.",
            ' *',
            " * SPDX-FileCopyrightText: {$year} Maho <https://mahocommerce.com>",
            ' * SPDX-License-Identifier: OSL-3.0',
            ' */',
            '',
            ':root {',
        ];
        foreach ($vars as $name => $value) {
            $lines[] = "    {$name}: {$value};";
        }
        $lines[] = '}';
        $lines[] = '';

        return implode(PHP_EOL, $lines);
    }

    private function relativePath(string $path): string
    {
        return str_replace(MAHO_ROOT_DIR . '/', '', $path);
    }
}
