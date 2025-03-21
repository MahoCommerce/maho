<?php

namespace MahoCLI\Commands;

use Mage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
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
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->initMaho();

        $metaDir = BP . '/.phpstorm.meta.php';
        $filesystem = new Filesystem();

        if ($filesystem->exists($metaDir)) {
            $filesystem->remove($metaDir);
        }
        $filesystem->mkdir($metaDir);

        $this->generateFactoryMethodsMetadata($output, $metaDir);
        $this->generateHelperMethodsMetadata($output, $metaDir);
        $this->generateRegistryMetadata($output, $metaDir);
        $this->generateBlockMetadata($output, $metaDir);
        $this->generateResourceModelMetadata($output, $metaDir);
        $this->generateResourceHelperMetadata($output, $metaDir);

        $output->writeln("<info>PhpStorm metadata generation complete!</info>");
        $output->writeln("<comment>Remember to add .phpstorm.meta.php to your .gitignore file</comment>");

        return Command::SUCCESS;
    }

    /**
     * Generate metadata for block factories
     */
    private function generateBlockMetadata(OutputInterface $output, string $metaDir): void
    {
        $output->writeln("Scanning for block classes...");

        $blocks = $this->scanForBlocks();
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

        $resourceModels = $this->scanForResourceModels();
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

        $resourceHelpers = $this->scanForResourceHelpers();
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

        $models = $this->scanForModels();
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

        $helpers = $this->scanForHelpers();
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
     * Scan the codebase for block classes
     */
    private function scanForBlocks(): array
    {
        $blocks = [];
        $codeBase = BP . '/app/code';

        $blockFiles = $this->findPhpFilesContaining($codeBase, 'extends Mage_Core_Block_');

        foreach ($blockFiles as $file) {
            // Extract namespace and class information
            $content = file_get_contents($file);
            if (preg_match('/class\s+(\w+)/', $content, $matches)) {
                $className = $matches[1];

                // Parse the class path to determine block alias
                $relativePath = str_replace($codeBase, '', $file);
                if (preg_match('#/([^/]+)/([^/]+)/Block/(.+)\.php$#', $relativePath, $parts)) {
                    $namespace = strtolower($parts[1]);
                    $module = $parts[2];
                    $blockPath = str_replace('/', '_', $parts[3]);

                    $alias = $namespace . '/' . strtolower($blockPath);
                    $blocks[$alias] = '\\' . $namespace . '_' . $module . '_Block_' . $blockPath;
                }
            }
        }

        return $blocks;
    }

    /**
     * Scan the codebase for model classes
     */
    private function scanForModels(): array
    {
        $models = [];
        $codeBase = BP . '/app/code';

        $modelFiles = $this->findPhpFilesContaining($codeBase, 'extends Mage_Core_Model_Abstract');

        foreach ($modelFiles as $file) {
            // Extract namespace and class information
            $content = file_get_contents($file);
            if (preg_match('/class\s+(\w+)/', $content, $matches)) {
                $className = $matches[1];

                // Parse the class path to determine model alias
                $relativePath = str_replace($codeBase, '', $file);
                if (preg_match('#/([^/]+)/([^/]+)/Model/(.+)\.php$#', $relativePath, $parts)) {
                    $namespace = strtolower($parts[1]);
                    $module = $parts[2];
                    $modelPath = str_replace('/', '_', $parts[3]);

                    $alias = $namespace . '/' . strtolower($modelPath);
                    $models[$alias] = '\\' . $namespace . '_' . $module . '_Model_' . $modelPath;
                }
            }
        }

        return $models;
    }

    /**
     * Scan the codebase for resource model classes
     */
    private function scanForResourceModels(): array
    {
        $resourceModels = [];
        $codeBase = BP . '/app/code';

        $resourceFiles = $this->findPhpFilesContaining($codeBase, 'extends Mage_Core_Model_Resource_');

        foreach ($resourceFiles as $file) {
            // Extract namespace and class information
            $content = file_get_contents($file);
            if (preg_match('/class\s+(\w+)/', $content, $matches)) {
                $className = $matches[1];

                // Parse the class path to determine resource model alias
                $relativePath = str_replace($codeBase, '', $file);
                if (preg_match('#/([^/]+)/([^/]+)/Model/Resource/(.+)\.php$#', $relativePath, $parts)) {
                    $namespace = strtolower($parts[1]);
                    $module = $parts[2];
                    $resourcePath = str_replace('/', '_', $parts[3]);

                    $alias = $namespace . '/resource_' . strtolower($resourcePath);
                    $resourceModels[$alias] = '\\' . $namespace . '_' . $module . '_Model_Resource_' . $resourcePath;
                }
            }
        }

        return $resourceModels;
    }

    /**
     * Scan the codebase for helper classes
     */
    private function scanForHelpers(): array
    {
        $helpers = [];
        $codeBase = BP . '/app/code';

        $helperFiles = $this->findPhpFilesContaining($codeBase, 'extends Mage_Core_Helper_Abstract');

        foreach ($helperFiles as $file) {
            // Extract namespace and class information
            $content = file_get_contents($file);
            if (preg_match('/class\s+(\w+)/', $content, $matches)) {
                $className = $matches[1];

                // Parse the class path to determine helper alias
                $relativePath = str_replace($codeBase, '', $file);
                if (preg_match('#/([^/]+)/([^/]+)/Helper/(.+)\.php$#', $relativePath, $parts)) {
                    $namespace = strtolower($parts[1]);
                    $module = $parts[2];
                    $helperPath = str_replace('/', '_', $parts[3]);

                    // Standard data helper
                    if ($helperPath === 'Data') {
                        $alias = $namespace;
                        $helpers[$alias] = '\\' . $namespace . '_' . $module . '_Helper_Data';
                    } else {
                        $alias = $namespace . '/' . strtolower($helperPath);
                        $helpers[$alias] = '\\' . $namespace . '_' . $module . '_Helper_' . $helperPath;
                    }
                }
            }
        }

        return $helpers;
    }

    /**
     * Scan the codebase for resource helper classes
     */
    private function scanForResourceHelpers(): array
    {
        $resourceHelpers = [];
        $codeBase = BP . '/app/code';

        $resourceHelperFiles = $this->findPhpFilesContaining($codeBase, 'extends Mage_Core_Model_Resource_Helper_');

        foreach ($resourceHelperFiles as $file) {
            // Extract namespace and class information
            $content = file_get_contents($file);
            if (preg_match('/class\s+(\w+)/', $content, $matches)) {
                $className = $matches[1];

                // Parse the class path to determine resource helper alias
                $relativePath = str_replace($codeBase, '', $file);
                if (preg_match('#/([^/]+)/([^/]+)/Model/Resource/Helper/(.+)\.php$#', $relativePath, $parts)) {
                    $namespace = strtolower($parts[1]);
                    $module = $parts[2];
                    $helperPath = str_replace('/', '_', $parts[3]);

                    $alias = $namespace;
                    $resourceHelpers[$alias] = '\\' . $namespace . '_' . $module . '_Model_Resource_Helper_' . $helperPath;
                }
            }
        }

        return $resourceHelpers;
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
     * Find PHP files containing specific text
     */
    private function findPhpFilesContaining(string $dir, string $text): array
    {
        $directory = new RecursiveDirectoryIterator($dir);
        $iterator = new RecursiveIteratorIterator($directory);
        $files = new RegexIterator($iterator, '/\.php$/i');

        $matchingFiles = [];
        foreach ($files as $file) {
            $content = file_get_contents($file->getPathname());
            if (strpos($content, $text) !== false) {
                $matchingFiles[] = $file->getPathname();
            }
        }

        return $matchingFiles;
    }

    /**
     * Generate metadata content for block methods
     */
    private function generateBlockMetadataContent(array $blocks): string
    {
        $content = "<?php\n\nnamespace PHPSTORM_META {\n";

        // Mage::getBlockSingleton() metadata
        $content .= "    // Type information for Mage::getBlockSingleton()\n";
        $content .= "    override(\\Mage::getBlockSingleton(0), map([\n";
        foreach ($blocks as $alias => $class) {
            $content .= "        '$alias' => $class::class,\n";
        }
        $content .= "    ]));\n\n";

        // Mage_Core_Model_Layout::createBlock() metadata
        $content .= "    // Type information for Mage_Core_Model_Layout::createBlock()\n";
        $content .= "    override(\\Mage_Core_Model_Layout::createBlock(0), map([\n";
        foreach ($blocks as $alias => $class) {
            $content .= "        '$alias' => $class::class,\n";
        }
        $content .= "    ]));\n";

        $content .= "}\n";
        return $content;
    }

    /**
     * Generate metadata content for resource models
     */
    private function generateResourceModelMetadataContent(array $resourceModels): string
    {
        $content = "<?php\n\nnamespace PHPSTORM_META {\n";

        // Mage::getResourceModel() metadata
        $content .= "    // Type information for Mage::getResourceModel()\n";
        $content .= "    override(\\Mage::getResourceModel(0), map([\n";
        foreach ($resourceModels as $alias => $class) {
            $content .= "        '$alias' => $class::class,\n";
        }
        $content .= "    ]));\n";

        $content .= "}\n";
        return $content;
    }

    /**
     * Generate metadata content for resource helpers
     */
    private function generateResourceHelperMetadataContent(array $resourceHelpers): string
    {
        $content = "<?php\n\nnamespace PHPSTORM_META {\n";

        // Mage::getResourceHelper() metadata
        $content .= "    // Type information for Mage::getResourceHelper()\n";
        $content .= "    override(\\Mage::getResourceHelper(0), map([\n";
        foreach ($resourceHelpers as $alias => $class) {
            $content .= "        '$alias' => $class::class,\n";
        }
        $content .= "    ]));\n";

        $content .= "}\n";
        return $content;
    }

    /**
     * Generate metadata content for factory methods
     */
    private function generateFactoryMetadataContent(array $models): string
    {
        $content = "<?php\n\nnamespace PHPSTORM_META {\n";

        // Mage::getModel() metadata
        $content .= "    // Type information for Mage::getModel()\n";
        $content .= "    override(\\Mage::getModel(0), map([\n";
        foreach ($models as $alias => $class) {
            $content .= "        '$alias' => $class::class,\n";
        }
        $content .= "    ]));\n\n";

        // Mage::getSingleton() metadata
        $content .= "    // Type information for Mage::getSingleton()\n";
        $content .= "    override(\\Mage::getSingleton(0), map([\n";
        foreach ($models as $alias => $class) {
            $content .= "        '$alias' => $class::class,\n";
        }
        $content .= "    ]));\n";

        $content .= "}\n";
        return $content;
    }

    /**
     * Generate metadata content for helper methods
     */
    private function generateHelperMetadataContent(array $helpers): string
    {
        $content = "<?php\n\nnamespace PHPSTORM_META {\n";

        // Mage::helper() metadata
        $content .= "    // Type information for Mage::helper()\n";
        $content .= "    override(\\Mage::helper(0), map([\n";
        foreach ($helpers as $alias => $class) {
            $content .= "        '$alias' => $class::class,\n";
        }
        $content .= "    ]));\n";

        $content .= "}\n";
        return $content;
    }

    /**
     * Generate metadata content for registry calls
     */
    private function generateRegistryMetadataContent(array $registryKeys): string
    {
        $content = "<?php\n\nnamespace PHPSTORM_META {\n";

        // Create argument set for registry keys
        $content .= "    // Registry keys\n";
        $content .= "    registerArgumentsSet('registry_keys',\n";
        foreach ($registryKeys as $key) {
            $content .= "        '$key',\n";
        }
        $content .= "    );\n\n";

        // Apply to Mage::registry() method
        $content .= "    expectedArguments(\\Mage::registry(0), 0, argumentsSet('registry_keys'));\n";

        $content .= "}\n";
        return $content;
    }
}