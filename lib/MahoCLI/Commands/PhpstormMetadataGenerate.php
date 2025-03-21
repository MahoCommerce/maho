<?php

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
    description: 'Generate PhpStorm metadata files for better IDE support'
)]
class PhpstormMetadataGenerate extends BaseMahoCommand
{
    private array $phpFiles = [];
    private array $phpClasses = [];

    private array $moduleConfigs = [
        'models' => [],
        'blocks' => [],
        'helpers' => [],
        'resourceModels' => [],
    ];

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
        $directoryIterator = new RecursiveDirectoryIterator(
            MAHO_ROOT_DIR,
            RecursiveDirectoryIterator::SKIP_DOTS
        );
        $iterator = new RecursiveIteratorIterator(
            $directoryIterator,
            RecursiveIteratorIterator::SELF_FIRST
        );
        $phpFiles = new RegexIterator(
            $iterator,
            '/^.+\.php$/i',
            \RecursiveRegexIterator::GET_MATCH
        );

        // Extract a list of all classes in those .php files
        foreach ($phpFiles as $file) {
            $this->phpFiles[] = $file[0];
            foreach ($this->extractClassesFromFile($file[0]) as $class) {
                $this->phpClasses[] = $class;
            }
        }

        $this->loadModuleConfigurations($output);

        $this->generateFactoryMethodsMetadata($output, $metaDir);
        $this->generateHelperMethodsMetadata($output, $metaDir);
        $this->generateRegistryMetadata($output, $metaDir);
        $this->generateBlockMetadata($output, $metaDir);
        $this->generateResourceModelMetadata($output, $metaDir);

        print_r($this->moduleConfigs);die();

        $output->writeln("<info>PhpStorm metadata generation complete!</info>");
        $output->writeln("<comment>Remember to add .phpstorm.meta.php to your .gitignore file</comment>");

        return Command::SUCCESS;
    }

    /**
     * Load all module configurations from Magento
     */
    private function loadModuleConfigurations(OutputInterface $output): void
    {
        $output->writeln("Loading module configurations from XML...");

        // Extract model configurations
        $modelsConfig = Mage::getConfig()->getNode('global/models');
        if ($modelsConfig) {
            foreach ($modelsConfig->children() as $alias => $model) {
                if (isset($model->class)) {
                    $classPrefix = (string)$model->class;
                    $this->moduleConfigs['models'][$alias] = $classPrefix;

                    // Also extract resource model configuration if available
                    if (isset($model->resourceModel)) {
                        $resourceAlias = (string)$model->resourceModel;
                        $this->moduleConfigs['resourceModels'][$alias] = $resourceAlias;
                    }
                }
            }
        }

        // Extract block configurations
        $blocksConfig = Mage::getConfig()->getNode('global/blocks');
        if ($blocksConfig) {
            foreach ($blocksConfig->children() as $alias => $block) {
                if (isset($block->class)) {
                    $this->moduleConfigs['blocks'][$alias] = (string)$block->class;
                }
            }
        }

        // Extract helper configurations
        $helpersConfig = Mage::getConfig()->getNode('global/helpers');
        if ($helpersConfig) {
            foreach ($helpersConfig->children() as $alias => $helper) {
                if (isset($helper->class)) {
                    $this->moduleConfigs['helpers'][$alias] = (string)$helper->class;
                }
            }
        }

        $output->writeln("<info>Found " . count($this->moduleConfigs['models']) . " model configs</info>");
        $output->writeln("<info>Found " . count($this->moduleConfigs['blocks']) . " block configs</info>");
        $output->writeln("<info>Found " . count($this->moduleConfigs['helpers']) . " helper configs</info>");
        $output->writeln("<info>Found " . count($this->moduleConfigs['resourceModels']) . " resource model configs</info>");
    }

    /**
     * Generate metadata for block factories
     */
    private function generateBlockMetadata(OutputInterface $output, string $metaDir): void
    {
        $output->writeln("Scanning for block classes...");

        $blocks = [];
        foreach ($this->moduleConfigs['blocks'] as $aliasPrefix => $classPrefix) {
            foreach ($this->phpClasses as $class) {
                if (str_starts_with($class, $classPrefix)) {
                    $aliasSuffix = str_replace("{$classPrefix}_", '', $class);
                    $aliasSuffix = strtolower($aliasSuffix);;
                    $blocks["{$aliasPrefix}/{$aliasSuffix}"] = $class;
                }
            }
        }

        $output->writeln("<info>Found " . count($blocks) . " block classes</info>");

        $blockContent = $this->generateBlockMetadataContent($blocks);
        file_put_contents($metaDir . '/block.meta.php', $blockContent);

        $output->writeln("<info>Block methods metadata written to block.meta.php</info>");
    }

    /**
     * Generate metadata for resource models
     */
    private function generateResourceModelMetadata(OutputInterface $output, string $metaDir): void
    {
        $output->writeln("Scanning for resource model classes...");

        $resourceModels = [];
        foreach ($this->moduleConfigs['resourceModels'] as $aliasPrefix => $classPrefix) {
            foreach ($this->phpClasses as $class) {
                if (str_starts_with($class, $classPrefix)) {
                    $aliasSuffix = str_replace("{$classPrefix}_", '', $class);
                    $aliasSuffix = strtolower($aliasSuffix);;
                    $resourceModels["{$aliasPrefix}/{$aliasSuffix}"] = $class;
                }
            }
        }

        $output->writeln("<info>Found " . count($resourceModels) . " resource model classes</info>");

        $resourceContent = $this->generateResourceModelMetadataContent($resourceModels);
        file_put_contents($metaDir . '/resource_model.meta.php', $resourceContent);

        $output->writeln("<info>Resource model metadata written to resource_model.meta.php</info>");
    }

    /**
     * Generate metadata for resource helpers
     */
    private function generateResourceHelperMetadata(OutputInterface $output, string $metaDir): void
    {
        $output->writeln("Scanning for resource helper classes...");

        $resourceHelpers = [];
        foreach ($this->moduleConfigs['resourceModels'] as $aliasPrefix => $classPrefix) {
            foreach ($this->phpClasses as $class) {
                if (str_starts_with($class, $classPrefix)) {
                    $aliasSuffix = str_replace("{$classPrefix}_", '', $class);
                    $aliasSuffix = strtolower($aliasSuffix);;
                    $resourceHelpers["{$aliasPrefix}/{$aliasSuffix}"] = $class;
                }
            }
        }

        $output->writeln("<info>Found " . count($resourceHelpers) . " resource helper classes</info>");

        $resourceHelperContent = $this->generateResourceHelperMetadataContent($resourceHelpers);
        file_put_contents($metaDir . '/resource_helper.meta.php', $resourceHelperContent);

        $output->writeln("<info>Resource helper metadata written to resource_helper.meta.php</info>");
    }

    /**
     * Generate metadata for Mage::getModel(), Mage::getSingleton(), etc.
     */
    private function generateFactoryMethodsMetadata(OutputInterface $output, string $metaDir): void
    {
        $output->writeln("Scanning for model classes...");

        $models = [];
        foreach ($this->moduleConfigs['models'] as $aliasPrefix => $classPrefix) {
            foreach ($this->phpClasses as $class) {
                if (str_starts_with($class, $classPrefix)) {
                    $aliasSuffix = str_replace("{$classPrefix}_", '', $class);
                    $aliasSuffix = strtolower($aliasSuffix);;
                    $models["{$aliasPrefix}/{$aliasSuffix}"] = $class;
                }
            }
        }

        $output->writeln("<info>Found " . count($models) . " model classes</info>");

        $factoryContent = $this->generateFactoryMetadataContent($models);
        file_put_contents($metaDir . '/factory.meta.php', $factoryContent);

        $output->writeln("<info>Factory methods metadata written to factory.meta.php</info>");
    }

    /**
     * Generate metadata for Mage::helper() calls
     */
    private function generateHelperMethodsMetadata(OutputInterface $output, string $metaDir): void
    {
        $output->writeln("Scanning for helper classes...");

        $helpers = [];
        foreach ($this->moduleConfigs['helpers'] as $aliasPrefix => $classPrefix) {
            foreach ($this->phpClasses as $class) {
                if (str_starts_with($class, $classPrefix)) {
                    $aliasSuffix = str_replace("{$classPrefix}_", '', $class);
                    $aliasSuffix = strtolower($aliasSuffix);;
                    $helpers["{$aliasPrefix}/{$aliasSuffix}"] = $class;
                }
            }
        }

        $output->writeln("<info>Found " . count($helpers) . " helper classes</info>");

        $helperContent = $this->generateHelperMetadataContent($helpers);
        file_put_contents($metaDir . '/helper.meta.php', $helperContent);

        $output->writeln("<info>Helper methods metadata written to helper.meta.php</info>");
    }

    /**
     * Generate metadata for Mage::registry() calls
     */
    private function generateRegistryMetadata(OutputInterface $output, string $metaDir): void
    {
        $output->writeln("Scanning for registry keys...");

        $registryKeys = $this->findRegistryKeys();
        $output->writeln("<info>Found " . count($registryKeys) . " registry keys</info>");

        $registryContent = $this->generateRegistryMetadataContent($registryKeys);
        file_put_contents($metaDir . '/registry.meta.php', $registryContent);

        $output->writeln("<info>Registry metadata written to registry.meta.php</info>");
    }

    /**
     * Find all classes of a specific type using reflection
     *
     * @param string $baseDir Directory to scan for PHP files
     * @param string $parentClass The parent class to check against (e.g., 'Mage_Core_Block_Abstract')
     * @param string $type Type of class to find ('Model', 'Block', 'Helper', etc.)
     * @return array Associative array of [alias => fully qualified class name]
     */
    private function findClassesOfType(string $baseDir, string $parentClass, string $type): array
    {
        $result = [];

        // First, get all PHP files
        $directory = new RecursiveDirectoryIterator($baseDir);
        $iterator = new RecursiveIteratorIterator($directory);
        $phpFiles = new RegexIterator($iterator, '/\.php$/i');

        foreach ($phpFiles as $file) {
            // Skip certain directories that might contain issues
            if (strpos($file->getPathname(), '/lib/') !== false) {
                continue;
            }

            // Extract namespace and class from the file
            $classes = $this->extractClassesFromFile($file->getPathname());

            foreach ($classes as $class) {
                try {
                    // Only proceed if the class can be autoloaded
                    if (!class_exists($class, true)) {
                        continue;
                    }

                    // Use reflection to check inheritance
                    $reflection = new \ReflectionClass($class);

                    // Skip interfaces, traits and abstract classes
                    if ($reflection->isInterface() || $reflection->isTrait() || $reflection->isAbstract()) {
                        continue;
                    }

                    // Check if this class is a descendant of the parent class
                    if ($reflection->isSubclassOf($parentClass)) {
                        // Generate the alias based on the class name and XML config
                        $alias = $this->generateAliasFromClass($class, $type);
                        if ($alias) {
                            $result[$alias] = $class;
                        }
                    }
                } catch (\Throwable $e) {
                    // Silently continue with other classes
                }
            }
        }

        return $result;
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
                    } else if ($tokens[$j] === '{' || $tokens[$j] === ';') {
                        break;
                    }
                }
            }

            if ($tokens[$i][0] === T_CLASS) {
                // Skip tokens until we find the class name
                for ($j = $i + 1; $j < $count; $j++) {
                    if ($tokens[$j][0] === T_STRING) {
                        // Handle Magento 1 class names (no namespace but underscore notation)
                        if (empty($namespace) && strpos($tokens[$j][1], '_') !== false) {
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
     * Generate Magento alias from a class using XML configuration
     *
     * @param string $className Full class name
     * @param string $type Type of class ('Model', 'Block', 'Helper', etc.)
     * @return string|null Magento alias for the class or null if not found
     */
    private function generateAliasFromClass(string $className, string $type): ?string
    {
        switch ($type) {
            case 'Model':
                return $this->generateModelAlias($className);
            case 'Block':
                return $this->generateBlockAlias($className);
            case 'Helper':
                return $this->generateHelperAlias($className);
            case 'ResourceModel':
                return $this->generateResourceModelAlias($className);
            case 'ResourceHelper':
                return $this->generateResourceHelperAlias($className);
            default:
                return null;
        }
    }

    /**
     * Generate model alias from class name using XML configuration
     */
    private function generateModelAlias(string $className): ?string
    {
        foreach ($this->moduleConfigs['models'] as $alias => $classPrefix) {
            if (strpos($className, $classPrefix) === 0) {
                $remainder = substr($className, strlen($classPrefix) + 1); // +1 for the underscore
                if (empty($remainder)) {
                    return $alias;
                }

                return $alias . '/' . strtolower(str_replace('_', '/', $remainder));
            }
        }

        return null;
    }

    /**
     * Generate block alias from class name using XML configuration
     */
    private function generateBlockAlias(string $className): ?string
    {
        foreach ($this->moduleConfigs['blocks'] as $alias => $classPrefix) {
            if (strpos($className, $classPrefix) === 0) {
                $remainder = substr($className, strlen($classPrefix) + 1); // +1 for the underscore
                if (empty($remainder)) {
                    return $alias;
                }

                return $alias . '/' . strtolower(str_replace('_', '/', $remainder));
            }
        }

        return null;
    }

    /**
     * Generate helper alias from class name using XML configuration
     */
    private function generateHelperAlias(string $className): ?string
    {
        foreach ($this->moduleConfigs['helpers'] as $alias => $classPrefix) {
            if (strpos($className, $classPrefix) === 0) {
                $remainder = substr($className, strlen($classPrefix) + 1); // +1 for the underscore

                // Special case for Data helpers
                if ($remainder === 'Data') {
                    return $alias;
                }

                if (empty($remainder)) {
                    return $alias;
                }

                return $alias . '/' . strtolower(str_replace('_', '/', $remainder));
            }
        }

        return null;
    }

    /**
     * Generate resource model alias from class name using XML configuration
     */
    private function generateResourceModelAlias(string $className): ?string
    {
        // Find which resource model group this class belongs to
        foreach ($this->moduleConfigs['models'] as $modelAlias => $modelPrefix) {
            $resourceAlias = $this->moduleConfigs['resourceModels'][$modelAlias] ?? null;

            if (!$resourceAlias) {
                continue;
            }

            $resourceConfig = Mage::getConfig()->getNode("global/models/$resourceAlias");
            if ($resourceConfig && isset($resourceConfig->class)) {
                $resourcePrefix = (string)$resourceConfig->class;

                if (strpos($className, $resourcePrefix) === 0) {
                    $remainder = substr($className, strlen($resourcePrefix) + 1); // +1 for the underscore
                    if (empty($remainder)) {
                        return $resourceAlias;
                    }

                    return $resourceAlias . '/' . strtolower(str_replace('_', '/', $remainder));
                }
            }
        }

        return null;
    }

    /**
     * Generate resource helper alias from class name using XML configuration
     */
    private function generateResourceHelperAlias(string $className): ?string
    {
        foreach ($this->moduleConfigs['resourceHelpers'] as $alias => $classPrefix) {
            if (strpos($className, $classPrefix) === 0) {
                $remainder = substr($className, strlen($classPrefix) + 1); // +1 for the underscore
                if (empty($remainder)) {
                    return $alias;
                }

                return $alias . '/' . strtolower(str_replace('_', '/', $remainder));
            }
        }

        return null;
    }

    /**
     * Scan the codebase for block classes
     */
    private function scanForBlocks(): array
    {
        return $this->findClassesOfType(BP . '/app/code', 'Mage_Core_Block_Abstract', 'Block');
    }

    /**
     * Scan the codebase for model classes
     */
    private function scanForModels(): array
    {
        return $this->findClassesOfType(BP . '/app/code', 'Mage_Core_Model_Abstract', 'Model');
    }

    /**
     * Scan the codebase for resource model classes
     */
    private function scanForResourceModels(): array
    {
        return $this->findClassesOfType(BP . '/app/code', 'Mage_Core_Model_Resource_Abstract', 'ResourceModel');
    }

    /**
     * Scan the codebase for helper classes
     */
    private function scanForHelpers(): array
    {
        return $this->findClassesOfType(BP . '/app/code', 'Mage_Core_Helper_Abstract', 'Helper');
    }

    /**
     * Scan the codebase for resource helper classes
     */
    private function scanForResourceHelpers(): array
    {
        return $this->findClassesOfType(BP . '/app/code', 'Mage_Core_Model_Resource_Helper_Abstract', 'ResourceHelper');
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
            if (strpos($content, $search) !== false) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    /**
     * Generate factory methods metadata content
     */
    private function generateFactoryMetadataContent(array $models): string
    {
        $content = <<<'PHP'
<?php
namespace PHPSTORM_META {
    override(\Mage::getModel(0), map([
PHP;

        foreach ($models as $alias => $class) {
            $content .= "\n        '$alias' => \\$class::class,";
        }

        $content .= <<<'PHP'

    ]));
    
    override(\Mage::getSingleton(0), map([
PHP;

        foreach ($models as $alias => $class) {
            $content .= "\n        '$alias' => \\$class::class,";
        }

        $content .= <<<'PHP'

    ]));
}

PHP;

        return $content;
    }

    /**
     * Generate helper methods metadata content
     */
    private function generateHelperMetadataContent(array $helpers): string
    {
        $content = <<<'PHP'
<?php
namespace PHPSTORM_META {
    override(\Mage::helper(0), map([
PHP;

        foreach ($helpers as $alias => $class) {
            $content .= "\n        '$alias' => \\$class::class,";
        }

        $content .= <<<'PHP'

    ]));
}

PHP;

        return $content;
    }

    /**
     * Generate registry metadata content
     */
    private function generateRegistryMetadataContent(array $registryKeys): string
    {
        $content = <<<'PHP'
<?php
namespace PHPSTORM_META {
    expectedArguments(\Mage::registry(), 0, 
PHP;

        $keysList = array_map(function($key) {
            return "'$key'";
        }, array_keys($registryKeys));

        $content .= implode(', ', $keysList);

        $content .= <<<'PHP'
);
}

PHP;

        return $content;
    }

    /**
     * Generate block metadata content
     */
    private function generateBlockMetadataContent(array $blocks): string
    {
        $content = <<<'PHP'
<?php
namespace PHPSTORM_META {
    override(\Mage_Core_Model_Layout::createBlock(0), map([
PHP;

        foreach ($blocks as $alias => $class) {
            $content .= "\n        '$alias' => \\$class::class,";
        }

        $content .= <<<'PHP'

    ]));
    
    override(\Mage_Core_Model_Layout::getBlockSingleton(0), map([
PHP;

        foreach ($blocks as $alias => $class) {
            $content .= "\n        '$alias' => \\$class::class,";
        }

        $content .= <<<'PHP'

    ]));
}

PHP;

        return $content;
    }

    /**
     * Generate resource model metadata content
     */
    private function generateResourceModelMetadataContent(array $resourceModels): string
    {
        $content = <<<'PHP'
<?php
namespace PHPSTORM_META {
    override(\Mage_Core_Model_Abstract::getResource(), map([
PHP;

        foreach ($resourceModels as $alias => $class) {
            $content .= "\n        '$alias' => \\$class::class,";
        }

        $content .= <<<'PHP'

    ]));
}

PHP;

        return $content;
    }

    /**
     * Generate resource helper metadata content
     */
    private function generateResourceHelperMetadataContent(array $resourceHelpers): string
    {
        $content = <<<'PHP'
<?php
namespace PHPSTORM_META {
    override(\Mage_Core_Model_Resource_Abstract::getHelper(0), map([
PHP;

        foreach ($resourceHelpers as $alias => $class) {
            $content .= "\n        '$alias' => \\$class::class,";
        }

        $content .= <<<'PHP'

    ]));
}

PHP;

        return $content;
    }
}