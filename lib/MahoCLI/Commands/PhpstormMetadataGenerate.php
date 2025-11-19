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
use Symfony\Component\Filesystem\Filesystem;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;

#[AsCommand(
    name: 'phpstorm:metadata:generate',
    description: 'Generate PhpStorm metadata files for better IDE support',
)]
class PhpstormMetadataGenerate extends BaseMahoCommand
{
    private array $phpClasses = [];

    private array $moduleConfigs = [
        'models' => [],
        'blocks' => [],
        'helpers' => [],
        'resourceModels' => [],
    ];

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->initMaho();

        $metaDir = BP . '/.phpstorm.meta.php';
        $filesystem = new Filesystem();
        if ($filesystem->exists($metaDir)) {
            $filesystem->remove($metaDir);
        }
        $filesystem->mkdir($metaDir);

        // Get all .php files
        $output->writeln('Extracting all PHP classes...');
        $directoryIterator = new RecursiveDirectoryIterator(
            MAHO_ROOT_DIR,
            RecursiveDirectoryIterator::SKIP_DOTS,
        );
        $iterator = new RecursiveIteratorIterator(
            $directoryIterator,
            RecursiveIteratorIterator::SELF_FIRST,
        );
        $phpFiles = new RegexIterator(
            $iterator,
            '/^.+\.php$/i',
            \RecursiveRegexIterator::GET_MATCH,
        );

        // Extract a list of all classes in those .php files
        foreach ($phpFiles as $file) {
            foreach ($this->extractClassesFromFile($file[0]) as $class) {
                $this->phpClasses[] = $class;
            }
        }
        $output->writeln('<info>Found ' . count($this->phpClasses) . ' classes</info>');

        $this->loadModuleConfigurations($output);

        $this->generateModelsMetadata($output, $metaDir);
        $this->generateHelpersMetadata($output, $metaDir);
        $this->generateRegistryMetadata($output, $metaDir);
        $this->generateBlocksMetadata($output, $metaDir);
        $this->generateResourceModelsMetadata($output, $metaDir);

        $output->writeln('<info>PhpStorm metadata generation complete!</info>');
        return Command::SUCCESS;
    }

    /**
     * Load all module configurations from Magento
     */
    private function loadModuleConfigurations(OutputInterface $output): void
    {
        $output->writeln('Loading module configurations from XML...');

        // Extract model configurations
        $modelsConfig = Mage::getConfig()->getNode('global/models');
        if ($modelsConfig) {
            foreach ($modelsConfig->children() as $alias => $model) {
                if (isset($model->class)) {
                    $classPrefix = (string) $model->class;
                    $this->moduleConfigs['models'][$alias] = $classPrefix;

                    // Also extract resource model configuration if available
                    if (isset($model->resourceModel)) {
                        $resourceAlias = (string) $model->resourceModel;
                        $this->moduleConfigs['resourceModels'][$resourceAlias] = (string) Mage::getConfig()->getNode("global/models/{$resourceAlias}/class");
                    }
                }
            }
        }

        // Extract block configurations
        $blocksConfig = Mage::getConfig()->getNode('global/blocks');
        if ($blocksConfig) {
            foreach ($blocksConfig->children() as $alias => $block) {
                if (isset($block->class)) {
                    $this->moduleConfigs['blocks'][$alias] = (string) $block->class;
                }
            }
        }

        // Extract helper configurations
        $helpersConfig = Mage::getConfig()->getNode('global/helpers');
        if ($helpersConfig) {
            foreach ($helpersConfig->children() as $alias => $helper) {
                if (isset($helper->class)) {
                    $this->moduleConfigs['helpers'][$alias] = (string) $helper->class;
                }
            }
        }

        $output->writeln('<info>Found ' . count($this->moduleConfigs['models']) . ' model configs</info>');
        $output->writeln('<info>Found ' . count($this->moduleConfigs['blocks']) . ' block configs</info>');
        $output->writeln('<info>Found ' . count($this->moduleConfigs['helpers']) . ' helper configs</info>');
        $output->writeln('<info>Found ' . count($this->moduleConfigs['resourceModels']) . ' resource model configs</info>');
    }

    /**
     * Generate metadata for block factories
     */
    private function generateBlocksMetadata(OutputInterface $output, string $metaDir): void
    {
        $output->writeln('Scanning for block classes...');

        $blocks = [];
        foreach ($this->moduleConfigs['blocks'] as $aliasPrefix => $classPrefix) {
            foreach ($this->phpClasses as $class) {
                if (str_starts_with($class, $classPrefix)) {
                    $aliasSuffix = str_replace("{$classPrefix}_", '', $class);
                    $aliasSuffix = strtolower($aliasSuffix);
                    ;
                    $blocks["{$aliasPrefix}/{$aliasSuffix}"] = $class;
                }
            }
        }

        $output->writeln('<info>Found ' . count($blocks) . ' block classes</info>');

        $blockContent = $this->generateBlockMetadataContent($blocks);
        file_put_contents($metaDir . '/blocks.meta.php', $blockContent);

        $output->writeln('<info>Block methods metadata written to blocks.meta.php</info>');
    }

    /**
     * Generate metadata for resource models
     */
    private function generateResourceModelsMetadata(OutputInterface $output, string $metaDir): void
    {
        $output->writeln('Scanning for resource model classes...');

        $resourceModels = [];
        foreach ($this->moduleConfigs['resourceModels'] as $aliasPrefix => $classPrefix) {
            foreach ($this->phpClasses as $class) {
                if (str_starts_with($class, $classPrefix)) {
                    $aliasSuffix = str_replace("{$classPrefix}_", '', $class);
                    $aliasSuffix = strtolower($aliasSuffix);
                    ;
                    $resourceModels["{$aliasPrefix}/{$aliasSuffix}"] = $class;
                }
            }
        }

        $output->writeln('<info>Found ' . count($resourceModels) . ' resource model classes</info>');

        $resourceContent = $this->generateResourceModelMetadataContent($resourceModels);
        file_put_contents($metaDir . '/resource_models.meta.php', $resourceContent);

        $output->writeln('<info>Resource model metadata written to resource_models.meta.php</info>');
    }

    /**
     * Generate metadata for Mage::getModel(), Mage::getSingleton(), etc.
     */
    private function generateModelsMetadata(OutputInterface $output, string $metaDir): void
    {
        $output->writeln('Scanning for model classes...');

        $models = [];
        foreach ($this->moduleConfigs['models'] as $aliasPrefix => $classPrefix) {
            foreach ($this->phpClasses as $class) {
                if (str_starts_with($class, $classPrefix)) {
                    $aliasSuffix = str_replace("{$classPrefix}_", '', $class);
                    $aliasSuffix = strtolower($aliasSuffix);
                    ;
                    $models["{$aliasPrefix}/{$aliasSuffix}"] = $class;
                }
            }
        }

        $output->writeln('<info>Found ' . count($models) . ' model classes</info>');

        $factoryContent = $this->generateModelsMetadataContent($models);
        file_put_contents($metaDir . '/models.meta.php', $factoryContent);

        $output->writeln('<info>Factory methods metadata written to models.meta.php</info>');
    }

    /**
     * Generate metadata for Mage::helper() calls
     */
    private function generateHelpersMetadata(OutputInterface $output, string $metaDir): void
    {
        $output->writeln('Scanning for helper classes...');

        $helpers = [];
        foreach ($this->moduleConfigs['helpers'] as $aliasPrefix => $classPrefix) {
            foreach ($this->phpClasses as $class) {
                if (str_starts_with($class, $classPrefix)) {
                    $aliasSuffix = str_replace("{$classPrefix}_", '', $class);
                    $aliasSuffix = strtolower($aliasSuffix);
                    ;
                    $helpers["{$aliasPrefix}/{$aliasSuffix}"] = $class;
                }
            }
        }

        $output->writeln('<info>Found ' . count($helpers) . ' helper classes</info>');

        $helperContent = $this->generateHelperMetadataContent($helpers);
        file_put_contents($metaDir . '/helpers.meta.php', $helperContent);

        $output->writeln('<info>Helper methods metadata written to helpers.meta.php</info>');
    }

    /**
     * Generate metadata for Mage::registry() calls
     */
    private function generateRegistryMetadata(OutputInterface $output, string $metaDir): void
    {
        $output->writeln('Scanning for registry keys...');

        $registryKeys = $this->findRegistryKeys();
        $output->writeln('<info>Found ' . count($registryKeys) . ' registry keys</info>');

        $registryContent = $this->generateRegistryMetadataContent($registryKeys);
        file_put_contents($metaDir . '/registry.meta.php', $registryContent);

        $output->writeln('<info>Registry metadata written to registry.meta.php</info>');
    }

    /**
     * Extract class names from a PHP file
     *
     * @param string $filePath Path to PHP file
     * @return array List of fully qualified class names
     */
    private function extractClassesFromFile(string $filePath): array
    {
        $classes = [];
        $namespace = '';
        $tokens = token_get_all(file_get_contents($filePath));
        $count = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            if ($tokens[$i][0] === T_NAMESPACE) {
                for ($j = $i + 1; $j < $count; $j++) {
                    if ($tokens[$j][0] === T_STRING) {
                        $namespace .= '\\' . $tokens[$j][1];
                    } elseif ($tokens[$j] === '{' || $tokens[$j] === ';') {
                        break;
                    }
                }
            }

            if ($tokens[$i][0] === T_CLASS) {
                // Skip tokens until we find the class name
                for ($j = $i + 1; $j < $count; $j++) {
                    if ($tokens[$j][0] === T_STRING) {
                        // Handle Magento 1 class names (no namespace but underscore notation)
                        if (empty($namespace) && str_contains($tokens[$j][1], '_')) {
                            $classes[] = $tokens[$j][1];
                        } else {
                            $classes[] = $namespace . '\\' . $tokens[$j][1];
                        }
                        break;
                    }
                }
            }
        }

        return $classes;
    }

    /**
     * Find all registry keys used in the codebase
     */
    private function findRegistryKeys(): array
    {
        $registryKeys = [];
        $codeBase = BP . '/app/code';

        $files = $this->findPhpFilesContaining($codeBase, 'Mage::register');

        foreach ($files as $file) {
            $content = file_get_contents($file);
            preg_match_all('/Mage::register\(\s*[\'"]([^\'"]+)[\'"]/', $content, $matches);
            if (!empty($matches[1])) {
                foreach ($matches[1] as $key) {
                    $registryKeys[$key] = $key;
                }
            }
        }

        return $registryKeys;
    }

    /**
     * Find PHP files containing a specific string
     *
     * @param string $directory Directory to search
     * @param string $search String to search for
     * @return array List of file paths
     */
    private function findPhpFilesContaining(string $directory, string $search): array
    {
        $files = [];
        $directory = new RecursiveDirectoryIterator($directory);
        $iterator = new RecursiveIteratorIterator($directory);
        $phpFiles = new RegexIterator($iterator, '/\.php$/i');

        foreach ($phpFiles as $file) {
            $content = file_get_contents($file->getPathname());
            if (str_contains($content, $search)) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    private function generateModelsMetadataContent(array $models): string
    {
        ksort($models);
        $content = "<?php\nnamespace PHPSTORM_META {\n";
        $content .= $this->generateMappingContent('\Mage::getModel(0)', $models);
        $content .= $this->generateMappingContent('\Mage::getSingleton(0)', $models);
        $content .= "}\n";
        return $content;
    }

    private function generateHelperMetadataContent(array $helpers): string
    {
        ksort($helpers);
        $content = "<?php\nnamespace PHPSTORM_META {\n";
        $content .= $this->generateMappingContent('\Mage::helper(0)', $helpers);
        $content .= "}\n";
        return $content;
    }

    private function generateRegistryMetadataContent(array $registryKeys): string
    {
        natcasesort($registryKeys);
        $content = "<?php\nnamespace PHPSTORM_META {\n";
        $content .= "    expectedArguments(\Mage::registry(), 0, \n        ";
        $content .= implode(",\n        ", $registryKeys);
        $content .= ");\n}\n";
        return $content;
    }

    private function generateBlockMetadataContent(array $blocks): string
    {
        ksort($blocks);
        $content = "<?php\nnamespace PHPSTORM_META {\n";
        $content .= $this->generateMappingContent('\Mage_Core_Model_Layout::createBlock(0)', $blocks);
        $content .= $this->generateMappingContent('\Mage_Core_Model_Layout::getBlockSingleton(0)', $blocks);
        $content .= "}\n";
        return $content;
    }

    private function generateResourceModelMetadataContent(array $resourceModels): string
    {
        ksort($resourceModels);
        $content = "<?php\nnamespace PHPSTORM_META {\n";
        $content .= $this->generateMappingContent('\Mage::getResourceModel(0)', $resourceModels);
        $content .= "}\n";
        return $content;
    }

    private function generateMappingContent(string $function, array $items): string
    {
        $content = "    override($function, map([\n";
        foreach ($items as $alias => $class) {
            $content .= "        '$alias' => \\$class::class,\n";
        }
        $content .= "    ]));\n";
        return $content;
    }
}
