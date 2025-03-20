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
        $a = get_declared_classes();print_r($a);die();

        $metaDir = BP . '/.phpstorm.meta.php';
        $filesystem = new Filesystem();

        if ($filesystem->exists($metaDir)) {
            $filesystem->remove($metaDir);
        }
        $filesystem->mkdir($metaDir);

        $this->generateFactoryMethodsMetadata($output, $metaDir);
        $this->generateHelperMethodsMetadata($output, $metaDir);
        $this->generateRegistryMetadata($output, $metaDir);

        $output->writeln("<info>PhpStorm metadata generation complete!</info>");
        $output->writeln("<comment>Remember to add .phpstorm.meta.php to your .gitignore file</comment>");

        return Command::SUCCESS;
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
     * Scan the codebase for model classes
     */
    private function scanForModels(): array
    {
        $models = [];
        $codeBase = BP . '/app/code';

        $dirs = ['core', 'community', 'local'];

        foreach ($dirs as $dir) {
            $path = $codeBase . '/' . $dir;
            if (!is_dir($path)) continue;

            $modelFiles = $this->findPhpFilesContaining($path, 'extends Mage_Core_Model_Abstract');

            foreach ($modelFiles as $file) {
                // Extract namespace and class information
                $content = file_get_contents($file);
                if (preg_match('/class\s+(\w+)/', $content, $matches)) {
                    $className = $matches[1];

                    // Parse the class path to determine model alias
                    $relativePath = str_replace($path, '', $file);
                    if (preg_match('#/([^/]+)/([^/]+)/Model/(.+)\.php$#', $relativePath, $parts)) {
                        $namespace = strtolower($parts[1]);
                        $module = $parts[2];
                        $modelPath = str_replace('/', '_', $parts[3]);

                        $alias = $namespace . '/' . strtolower($modelPath);
                        $models[$alias] = '\\' . $namespace . '_' . $module . '_Model_' . $modelPath;
                    }
                }
            }
        }

        return $models;
    }

    /**
     * Scan the codebase for helper classes
     */
    private function scanForHelpers(): array
    {
        $helpers = [];
        $codeBase = BP . '/app/code';

        $dirs = ['core', 'community', 'local'];

        foreach ($dirs as $dir) {
            $path = $codeBase . '/' . $dir;
            if (!is_dir($path)) continue;

            $helperFiles = $this->findPhpFilesContaining($path, 'extends Mage_Core_Helper_Abstract');

            foreach ($helperFiles as $file) {
                // Extract namespace and class information
                $content = file_get_contents($file);
                if (preg_match('/class\s+(\w+)/', $content, $matches)) {
                    $className = $matches[1];

                    // Parse the class path to determine helper alias
                    $relativePath = str_replace($path, '', $file);
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
        }

        return $helpers;
    }

    /**
     * Find all registry keys used in the codebase
     */
    private function findRegistryKeys(): array
    {
        $registryKeys = [];
        $codeBase = BP . '/app/code';

        $dirs = ['core', 'community', 'local'];

        foreach ($dirs as $dir) {
            $path = $codeBase . '/' . $dir;
            if (!is_dir($path)) continue;

            $files = $this->findPhpFilesContaining($path, 'Mage::register');

            foreach ($files as $file) {
                $content = file_get_contents($file);
                preg_match_all('/Mage::register\(\s*[\'"]([^\'"]+)[\'"]/', $content, $matches);
                if (!empty($matches[1])) {
                    foreach ($matches[1] as $key) {
                        $registryKeys[$key] = $key;
                    }
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