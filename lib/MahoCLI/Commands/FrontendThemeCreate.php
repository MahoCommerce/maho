<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace MahoCLI\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;

#[AsCommand(
    name: 'dev:frontend:theme:create',
    description: 'Create a new frontend theme with proper scaffolding',
)]
class FrontendThemeCreate extends BaseMahoCommand
{
    private const BASE_DESIGN_PATH = 'app/design/frontend';
    private const BASE_SKIN_PATH = 'public/skin/frontend';
    private const DEFAULT_PARENT = 'base/default';

    #[\Override]
    protected function configure(): void
    {
        $this
            ->addOption('package', 'p', InputOption::VALUE_REQUIRED, 'Package name (e.g., mystore)')
            ->addOption('theme', 't', InputOption::VALUE_OPTIONAL, 'Theme name (e.g., holiday)', 'default')
            ->addOption('parent', null, InputOption::VALUE_OPTIONAL, 'Parent theme (e.g., base/default)')
            ->addOption('tailwind', null, InputOption::VALUE_NEGATABLE, 'Enable Tailwind for this theme');
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Frontend Theme Creator');

        // Collect input (interactive or from options)
        $packageName = $this->getPackageName($input, $output, $io);
        if ($packageName === null) {
            return Command::INVALID;
        }

        // Loop for theme name input with validation
        while (true) {
            $themeName = $this->getThemeName($input, $output, $io);
            if ($themeName === null) {
                return Command::INVALID;
            }

            // Check if theme already exists
            if ($this->themeExists($packageName, $themeName)) {
                $io->error([
                    "Theme '{$packageName}/{$themeName}' already exists.",
                    '',
                    'Location: ' . self::BASE_DESIGN_PATH . "/{$packageName}/{$themeName}/",
                ]);
                // In non-interactive mode, exit; otherwise loop back
                if ($input->getOption('package')) {
                    return Command::FAILURE;
                }
                $io->newLine();
                continue;
            }

            $io->text("<info>✓</info> Theme path is available\n");

            // Warn if creating non-default theme without package's default theme
            if ($themeName !== 'default' && !$this->themeExists($packageName, 'default')) {
                $io->warning([
                    "Package '{$packageName}' does not have a 'default' theme.",
                    '',
                    "It's recommended to create '{$packageName}/default' first, then create",
                    'sub-themes that inherit from it. This allows you to:',
                    '',
                    '  • Share common customizations across all themes in the package',
                    '  • Create seasonal/promotional variants more easily',
                ]);

                if (!$io->confirm('Continue anyway?', false)) {
                    // In non-interactive mode, exit; otherwise loop back
                    if ($input->getOption('package')) {
                        $io->newLine();
                        $io->text('Hint: Create the default theme first:');
                        $io->text("  <info>./maho frontend:theme:create --package={$packageName} --theme=default</info>");
                        return Command::SUCCESS;
                    }
                    $io->newLine();
                    continue;
                }
                $io->newLine();
            }

            break;
        }

        // Get parent theme
        $parentTheme = $this->getParentTheme($input, $output, $io, $packageName);
        if ($parentTheme === null) {
            return Command::INVALID;
        }

        // Validate parent exists
        if (!$this->themeExists(...explode('/', $parentTheme))) {
            $io->error([
                "Parent theme '{$parentTheme}' does not exist.",
                '',
                'Available themes:',
                ...$this->formatAvailableThemes(),
                '',
                'Create the parent theme first, then run this command again.',
            ]);
            return Command::FAILURE;
        }

        $io->text("<info>✓</info> Parent theme '{$parentTheme}' exists\n");

        $withBuild = $this->wantsBuildStep($input, $io);

        // Create the theme
        $io->section('Creating theme structure');

        try {
            $createdFiles = $this->createTheme($packageName, $themeName, $parentTheme, $withBuild);
        } catch (\RuntimeException $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }

        foreach ($createdFiles as $file) {
            $io->text("  <info>Created:</info> {$file}");
        }

        // Success message
        $io->newLine();
        $io->success("Theme '{$packageName}/{$themeName}' created successfully!");

        // Show inheritance chain
        $inheritanceChain = $this->buildInheritanceChain($packageName, $themeName, $parentTheme);
        $io->text('<comment>Inheritance chain:</comment>');
        $io->text('  ' . implode(' → ', $inheritanceChain));
        $io->newLine();

        if ($withBuild) {
            $this->compile($packageName, $themeName, $io);
        }

        // Next steps
        $io->text('<comment>Next steps:</comment>');
        $io->listing([
            'Go to Admin → System → Configuration → Design',
            "Set Package to '<info>{$packageName}</info>' and Default theme to '<info>{$themeName}</info>'",
            'Customize your theme:',
        ]);
        $cssFile = $withBuild ? 'src/tailwind.css' : 'css/theme.css';
        $io->text('    • CSS:      <info>' . self::BASE_SKIN_PATH . "/{$packageName}/{$themeName}/{$cssFile}</info>");
        $io->text('    • Layout:   <info>' . self::BASE_DESIGN_PATH . "/{$packageName}/{$themeName}/layout/local.xml</info>");
        $io->text('    • Templates: <info>' . self::BASE_DESIGN_PATH . "/{$packageName}/{$themeName}/template/</info>");

        $io->newLine();
        if ($withBuild) {
            $io->text('After every change to src/tailwind.css, run this to rebuild the CSS:');
            $io->text("    <info>./maho dev:frontend:theme:build --theme {$packageName}/{$themeName}</info>");
            $io->text('Add <info>--watch</info> to rebuild by itself while you work. Run it once without');
            $io->text('<info>--watch</info> before you commit, because watch output is not minified.');
            $io->text('Never edit css/styles.css: the command overwrites it.');
            $io->text('Rebuild after a Maho upgrade too: this theme now carries its own copy');
            $io->text('of the compiled framework.');
        } else {
            $io->text('To use Tailwind later, see option B in public/skin/frontend/README.md.');
        }

        return Command::SUCCESS;
    }

    /** Default no: the choice is cheap to reverse, since both shapes write the same css/theme.css. */
    private function wantsBuildStep(InputInterface $input, SymfonyStyle $io): bool
    {
        $option = $input->getOption('tailwind');
        if ($option !== null) {
            return (bool) $option;
        }

        // Non-interactive mode is signalled by --package throughout this command
        if ($input->getOption('package')) {
            return false;
        }

        $io->text([
            '<comment>How do you want to write the CSS of this theme?</comment>',
            '',
            '  <info>Plain CSS.</info> You edit css/theme.css. Save the file, reload the page, done.',
            '',
            '  <info>Tailwind.</info> You can also write Tailwind class names in your own HTML,',
            '  like class="flex gap-4". You edit src/theme.css, and after every change',
            '  you run one command to rebuild the CSS. It needs Node.js.',
            '',
            '  You can change your mind later.',
            '',
        ]);

        return $io->confirm('Use Tailwind?', false);
    }

    /**
     * Compile once so css/theme.css exists: without it the skin fallback quietly
     * serves the parent's file. Never installs the toolchain.
     */
    private function compile(string $packageName, string $themeName, SymfonyStyle $io): void
    {
        $binary = MAHO_ROOT_DIR . '/node_modules/.bin/tailwindcss';
        $entry = self::BASE_SKIN_PATH . "/{$packageName}/{$themeName}/src/tailwind.css";
        $bundle = self::BASE_SKIN_PATH . "/{$packageName}/{$themeName}/css/styles.css";

        if (!is_file($binary)) {
            $io->warning([
                'Tailwind is not installed yet, so ' . $bundle . ' was not built.',
                'Run this once and say yes when it offers to install Tailwind:',
                "  ./maho dev:frontend:theme:build --theme {$packageName}/{$themeName}",
                'Until then the theme shows the colors of its parent.',
            ]);
            return;
        }

        $process = new Process([$binary, '-i', $entry, '-o', $bundle, '--minify'], MAHO_ROOT_DIR, null, null, 600);
        $process->run();

        if (!$process->isSuccessful()) {
            $io->warning([
                'Building the CSS failed:',
                trim($process->getErrorOutput() . "\n" . $process->getOutput()),
            ]);
            return;
        }

        $io->text("  <info>Compiled:</info> {$bundle}");
    }

    private function getPackageName(InputInterface $input, OutputInterface $output, SymfonyStyle $io): ?string
    {
        $packageName = $input->getOption('package');

        if ($packageName === null) {
            $packageName = $io->ask(
                'Package name (e.g., mystore)',
                null,
                fn($value) => $this->validateName($value, 'Package name'),
            );
        } else {
            try {
                $packageName = $this->validateName($packageName, 'Package name');
            } catch (\RuntimeException $e) {
                $io->error($e->getMessage());
                return null;
            }
        }

        return $packageName;
    }

    private function getThemeName(InputInterface $input, OutputInterface $output, SymfonyStyle $io): ?string
    {
        $themeName = $input->getOption('theme');

        if (!$input->getOption('package')) {
            // Interactive mode - ask for theme name
            $themeName = $io->ask(
                'Theme name',
                'default',
                fn($value) => $this->validateName($value, 'Theme name'),
            );
        } else {
            // Non-interactive - validate provided value
            try {
                $themeName = $this->validateName($themeName, 'Theme name');
            } catch (\RuntimeException $e) {
                $io->error($e->getMessage());
                return null;
            }
        }

        return $themeName;
    }

    private function getParentTheme(InputInterface $input, OutputInterface $output, SymfonyStyle $io, string $packageName): ?string
    {
        $parentTheme = $input->getOption('parent');

        // Determine the suggested default parent
        $packageDefault = "{$packageName}/default";
        $suggestedParent = $this->themeExists($packageName, 'default')
            ? $packageDefault
            : self::DEFAULT_PARENT;

        if ($parentTheme === null) {
            // Interactive mode - show available themes as choices
            $availableThemes = $this->getAvailableThemes();
            $choices = [];

            // Put suggested parent first
            if (in_array($suggestedParent, $availableThemes, true)) {
                $choices[$suggestedParent] = $suggestedParent;
                $availableThemes = array_diff($availableThemes, [$suggestedParent]);
            }

            // Add base/default if not already added
            if ($suggestedParent !== self::DEFAULT_PARENT && in_array(self::DEFAULT_PARENT, $availableThemes, true)) {
                $choices[self::DEFAULT_PARENT] = self::DEFAULT_PARENT;
                $availableThemes = array_diff($availableThemes, [self::DEFAULT_PARENT]);
            }

            foreach ($availableThemes as $theme) {
                $choices[$theme] = $theme;
            }

            $choices['_other'] = 'Other (type manually)';

            /** @var \Symfony\Component\Console\Helper\QuestionHelper $questionHelper */
            $questionHelper = $this->getHelper('question');
            $question = new ChoiceQuestion(
                "Select parent theme [{$suggestedParent}]",
                array_values($choices),
                0,
            );

            $selected = $questionHelper->ask($input, $output, $question);

            if ($selected === 'Other (type manually)') {
                $parentTheme = $io->ask(
                    'Enter parent theme (format: package/theme)',
                    $suggestedParent,
                    function ($value) {
                        if (!preg_match('/^[a-z][a-z0-9_-]*\/[a-z][a-z0-9_-]*$/', $value)) {
                            throw new \RuntimeException(
                                'Parent theme must be in format "package/theme" using lowercase letters, numbers, hyphens, or underscores.',
                            );
                        }
                        return $value;
                    },
                );
            } else {
                // Extract the actual theme key from the selected value
                $parentTheme = array_search($selected, $choices, true);
                if ($parentTheme === false) {
                    $parentTheme = $suggestedParent;
                }
            }
        } else {
            // Non-interactive - validate format
            if (!preg_match('/^[a-z][a-z0-9_-]*\/[a-z][a-z0-9_-]*$/', $parentTheme)) {
                $io->error('Parent theme must be in format "package/theme" using lowercase letters, numbers, hyphens, or underscores.');
                return null;
            }
        }

        return $parentTheme;
    }

    private function validateName(string $value, string $fieldName): string
    {
        $value = trim($value);

        if (empty($value)) {
            throw new \RuntimeException("{$fieldName} cannot be empty.");
        }

        if (!preg_match('/^[a-z][a-z0-9_-]*$/', $value)) {
            throw new \RuntimeException(
                "{$fieldName} must be lowercase, starting with a letter. " .
                'Only letters, numbers, hyphens, and underscores are allowed.',
            );
        }

        return $value;
    }

    private function themeExists(string $packageName, string $themeName): bool
    {
        $designPath = self::BASE_DESIGN_PATH . "/{$packageName}/{$themeName}";
        $skinPath = self::BASE_SKIN_PATH . "/{$packageName}/{$themeName}";

        return is_dir($designPath) || is_dir($skinPath);
    }

    private function getAvailableThemes(): array
    {
        $themes = [];

        $packages = glob(self::BASE_DESIGN_PATH . '/*', GLOB_ONLYDIR);
        foreach ($packages as $packagePath) {
            $packageName = basename($packagePath);
            $themesDirs = glob($packagePath . '/*', GLOB_ONLYDIR);

            foreach ($themesDirs as $themePath) {
                $themeName = basename($themePath);
                $themes[] = "{$packageName}/{$themeName}";
            }
        }

        sort($themes);
        return $themes;
    }

    private function formatAvailableThemes(): array
    {
        $themes = $this->getAvailableThemes();
        return array_map(fn($theme) => "  • {$theme}", $themes);
    }

    private function createTheme(string $packageName, string $themeName, string $parentTheme, bool $withBuild): array
    {
        $createdFiles = [];

        // Directory paths
        $designBasePath = self::BASE_DESIGN_PATH . "/{$packageName}/{$themeName}";
        $skinBasePath = self::BASE_SKIN_PATH . "/{$packageName}/{$themeName}";

        $directories = [
            "{$designBasePath}/etc",
            "{$designBasePath}/layout",
            $withBuild ? "{$skinBasePath}/src" : "{$skinBasePath}/css",
        ];

        // Create directories
        foreach ($directories as $dir) {
            if (!is_dir($dir)) {
                if (!mkdir($dir, 0755, true)) {
                    throw new \RuntimeException("Failed to create directory: {$dir}");
                }
            }
        }

        // Generate theme.xml
        $themeXmlPath = "{$designBasePath}/etc/theme.xml";
        $themeXmlContent = $this->generateThemeXml($packageName, $themeName, $parentTheme);
        if (file_put_contents($themeXmlPath, $themeXmlContent) === false) {
            throw new \RuntimeException("Failed to create file: {$themeXmlPath}");
        }
        $createdFiles[] = $themeXmlPath;

        // Generate local.xml
        $localXmlPath = "{$designBasePath}/layout/local.xml";
        $localXmlContent = $this->generateLocalXml($packageName, $themeName);
        if (file_put_contents($localXmlPath, $localXmlContent) === false) {
            throw new \RuntimeException("Failed to create file: {$localXmlPath}");
        }
        $createdFiles[] = $localXmlPath;

        $cssPath = $withBuild ? "{$skinBasePath}/src/tailwind.css" : "{$skinBasePath}/css/theme.css";
        $cssContent = $withBuild
            ? $this->generateBuildCss($packageName, $themeName)
            : $this->generateCss($packageName, $themeName, $parentTheme);
        if (file_put_contents($cssPath, $cssContent) === false) {
            throw new \RuntimeException("Failed to create file: {$cssPath}");
        }
        $createdFiles[] = $cssPath;

        return $createdFiles;
    }

    private function generateThemeXml(string $packageName, string $themeName, string $parentTheme): string
    {
        return '<?xml version="1.0"?>
<!--
    Theme: ' . $packageName . '/' . $themeName . '
    Parent: ' . $parentTheme . '

    This file defines the theme inheritance hierarchy.
    Files not found in this theme will fall back to the parent theme.
-->
<theme>
    <parent>' . $parentTheme . '</parent>
</theme>
';
    }

    private function generateLocalXml(string $packageName, string $themeName): string
    {
        return '<?xml version="1.0"?>
<!--
    Layout customizations for ' . $packageName . '/' . $themeName . '

    This file overrides and extends the base layout.
    Add your custom layout handles below.
-->
<layout version="0.1.0">

    <!-- css/theme.css needs no entry here: every page already loads it after the compiled styles.css -->

    <!--
    Example: Add a custom block to the homepage

    <cms_index_index>
        <reference name="content">
            <block type="core/template" name="custom.block" template="custom/block.phtml"/>
        </reference>
    </cms_index_index>
    -->

    <!--
    Example: Remove a block from all pages

    <default>
        <reference name="left">
            <remove name="left.newsletter"/>
        </reference>
    </default>
    -->

</layout>
';
    }

    private function generateCss(string $packageName, string $themeName, string $parentTheme): string
    {
        return '/*
 * Identity of ' . $packageName . '/' . $themeName . '
 *
 * Every page loads this file after the compiled styles.css. Override the
 * design tokens below and add plain CSS under them; the compiled framework
 * lives in cascade layers, so an unlayered rule here always wins.
 * The full token list is in public/skin/frontend/README.md.
 */
' . $this->parentThemeImport($parentTheme, "@import url('%s');") . '
:root {
    /* Colors */
    /* --color-primary: #0b6d9f; */
    /* --color-primary-content: #ffffff; */
    /* --color-base-100: #ffffff; */
    /* --color-base-200: #f4f4f5; */
    /* --color-base-content: #18181b; */

    /* Type */
    /* --font-body: system-ui, sans-serif; */
    /* --font-display: system-ui, sans-serif; */

    /* Shape */
    /* --radius-field: 0.5rem; */
    /* --radius-box: 1rem; */
}

/* Add your custom styles below */
';
    }

    /**
     * One import carries the whole engine: the DaisyUI plugin, the component
     * layer and the template scan, whose paths resolve against Maho's file and
     * so already cover this theme. The output replaces Maho's styles.css, which
     * leaves the css/theme.css slot free for the parent's identity.
     */
    private function generateBuildCss(string $packageName, string $themeName): string
    {
        return '/*! css/styles.css is generated from src/tailwind.css by "./maho dev:frontend:theme:build" - do not edit it */

/*
 * Build entry of ' . $packageName . '/' . $themeName . '. Compiles to
 * ../css/styles.css, which the skin fallback serves instead of Maho\'s.
 * The full token list is in public/skin/frontend/README.md.
 */

@import "../../../base/default/src/tailwind.css";

:root {
    /* Colors */
    /* --color-primary: #0b6d9f; */
    /* --color-primary-content: #ffffff; */
    /* --color-base-100: #ffffff; */
    /* --color-base-200: #f4f4f5; */
    /* --color-base-content: #18181b; */

    /* Type */
    /* --font-body: system-ui, sans-serif; */
    /* --font-display: system-ui, sans-serif; */

    /* Shape */
    /* --radius-field: 0.5rem; */
    /* --radius-box: 1rem; */
}

@layer components {
    /* .my-promo { @apply alert alert-info rounded-box; } */
}

/* Add your custom styles below */
';
    }

    /**
     * The skin fallback serves the first css/theme.css it finds, so this theme's
     * own file shadows the parent's whole identity. Pull it back in. base/default
     * is skipped: its theme.css declares nothing, the compiled styles.css does.
     */
    private function parentThemeImport(string $parentTheme, string $format): string
    {
        if ($parentTheme === self::DEFAULT_PARENT) {
            return '';
        }
        return sprintf($format, "../../../{$parentTheme}/css/theme.css") . "\n";
    }

    private function buildInheritanceChain(string $packageName, string $themeName, string $parentTheme): array
    {
        $chain = ["{$packageName}/{$themeName}"];

        // Add parent
        $chain[] = $parentTheme;

        // If parent is not base/default, add base/default at the end
        if ($parentTheme !== self::DEFAULT_PARENT) {
            // Check if parent's parent is base/default (simplified - assumes single level)
            $chain[] = self::DEFAULT_PARENT;
        }

        return $chain;
    }
}
