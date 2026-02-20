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

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'frontend:theme:create',
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
            ->addOption('parent', null, InputOption::VALUE_OPTIONAL, 'Parent theme (e.g., base/default)');
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

        // Check for CSS collision
        $collision = $this->checkCssCollision($themeName, $parentTheme);
        if ($collision !== null) {
            $io->error([
                'File collision detected!',
                '',
                "Your theme name '{$themeName}' would create '{$themeName}.css',",
                'but this file already exists in the fallback chain:',
                '',
                "  → {$collision}",
                '',
                'This would cause your CSS to unintentionally override core styles.',
                '',
                'Please choose a different theme name.',
            ]);
            return Command::FAILURE;
        }

        $io->text("<info>✓</info> No file collisions detected\n");

        // Create the theme
        $io->section('Creating theme structure');

        try {
            $createdFiles = $this->createTheme($packageName, $themeName, $parentTheme);
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

        // Next steps
        $io->text('<comment>Next steps:</comment>');
        $io->listing([
            'Go to Admin → System → Configuration → Design',
            "Set Package to '<info>{$packageName}</info>' and Default theme to '<info>{$themeName}</info>'",
            'Customize your theme:',
        ]);
        $io->text('    • CSS:      <info>' . self::BASE_SKIN_PATH . "/{$packageName}/{$themeName}/css/{$themeName}.css</info>");
        $io->text('    • Layout:   <info>' . self::BASE_DESIGN_PATH . "/{$packageName}/{$themeName}/layout/local.xml</info>");
        $io->text('    • Templates: <info>' . self::BASE_DESIGN_PATH . "/{$packageName}/{$themeName}/template/</info>");

        return Command::SUCCESS;
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

    private function checkCssCollision(string $themeName, string $parentTheme): ?string
    {
        $cssFileName = "{$themeName}.css";

        // Build fallback chain to check
        $fallbackThemes = [self::DEFAULT_PARENT];
        if ($parentTheme !== self::DEFAULT_PARENT) {
            $fallbackThemes[] = $parentTheme;
        }

        foreach ($fallbackThemes as $fallback) {
            $checkPath = self::BASE_SKIN_PATH . "/{$fallback}/css/{$cssFileName}";
            if (file_exists($checkPath)) {
                return $checkPath;
            }
        }

        return null;
    }

    private function createTheme(string $packageName, string $themeName, string $parentTheme): array
    {
        $createdFiles = [];

        // Directory paths
        $designBasePath = self::BASE_DESIGN_PATH . "/{$packageName}/{$themeName}";
        $skinBasePath = self::BASE_SKIN_PATH . "/{$packageName}/{$themeName}";

        $directories = [
            "{$designBasePath}/etc",
            "{$designBasePath}/layout",
            "{$skinBasePath}/css",
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

        // Generate CSS file
        $cssPath = "{$skinBasePath}/css/{$themeName}.css";
        $cssContent = $this->generateCss($packageName, $themeName);
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

    <!-- Add CSS stylesheet to all pages -->
    <default>
        <reference name="head">
            <action method="addCss">
                <stylesheet>css/' . $themeName . '.css</stylesheet>
            </action>
        </reference>
    </default>

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

    private function generateCss(string $packageName, string $themeName): string
    {
        return '/**
 * Custom styles for ' . $packageName . '/' . $themeName . '
 *
 * This stylesheet is loaded after the base theme styles.
 * Override CSS variables and add custom rules below.
 *
 * Base theme CSS variables:
 * https://github.com/MahoCommerce/maho/tree/main/public/skin/frontend/base/default/css
 */

/* ---------------------- */
/* CSS Variable Overrides */
/* ---------------------- */

:root {
    /* Primary Colors */
    /* --maho-color-primary: #0472ad; */
    /* --maho-color-primary-hover: #2e8ab8; */

    /* Text Colors */
    /* --maho-color-text-primary: #636363; */
    /* --maho-color-text-secondary: #767676; */

    /* Background Colors */
    /* --maho-color-background: #ffffff; */
    /* --maho-color-background-alt: #f4f4f4; */
}

/* ------------- */
/* Custom Styles */
/* ------------- */

/* Add your custom styles below */
';
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
