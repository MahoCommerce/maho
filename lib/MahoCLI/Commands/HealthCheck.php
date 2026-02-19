<?php

/**
 * Maho
 *
 * @package    MahoCLI
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace MahoCLI\Commands;

use Mage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'health-check',
    description: 'Health check your Maho project',
)]
class HealthCheck extends BaseMahoCommand
{
    private const DESIGN_PATH = 'app/design/frontend';
    private const SKIN_PATH = 'public/skin/frontend';

    /**
     * Mapping of deprecated Varien_ classes to their Maho\ replacements
     */
    private const VARIEN_TO_MAHO_MAP = [
        'Varien_Convert' => \Maho\Convert::class,
        'Varien_Convert_Action_Abstract' => \Maho\Convert\Action\AbstractAction::class,
        'Varien_Convert_Action_Interface' => \Maho\Convert\Action\ActionInterface::class,
        'Varien_Convert_Container_Abstract' => \Maho\Convert\Container\AbstractContainer::class,
        'Varien_Convert_Container_Interface' => \Maho\Convert\Container\ContainerInterface::class,
        'Varien_Convert_Mapper_Abstract' => \Maho\Convert\Mapper\AbstractMapper::class,
        'Varien_Convert_Mapper_Interface' => \Maho\Convert\Mapper\MapperInterface::class,
        'Varien_Convert_Parser_Abstract' => \Maho\Convert\Parser\AbstractParser::class,
        'Varien_Convert_Parser_Interface' => \Maho\Convert\Parser\ParserInterface::class,
        'Varien_Convert_Profile_Abstract' => \Maho\Convert\Profile\AbstractProfile::class,
        'Varien_Data_Collection' => \Maho\Data\Collection::class,
        'Varien_Data_Collection_Db' => \Maho\Data\Collection\Db::class,
        'Varien_Data_Collection_Filesystem' => \Maho\Data\Collection\Filesystem::class,
        'Varien_Data_Form' => \Maho\Data\Form::class,
        'Varien_Data_Form_Abstract' => \Maho\Data\Form\AbstractForm::class,
        'Varien_Data_Form_Element_Abstract' => \Maho\Data\Form\Element\AbstractElement::class,
        'Varien_Data_Form_Element_Renderer_Interface' => \Maho\Data\Form\Element\Renderer\RendererInterface::class,
        'Varien_Data_Form_Filter_Interface' => \Maho\Data\Form\Filter\FilterInterface::class,
        'Varien_Data_Tree' => \Maho\Data\Tree::class,
        'Varien_Data_Tree_Node' => \Maho\Data\Tree\Node::class,
        'Varien_Data_Tree_Node_Collection' => \Maho\Data\Tree\Node\Collection::class,
        'Varien_Db_Adapter_Interface' => \Maho\Db\Adapter\AdapterInterface::class,
        'Varien_Db_Adapter_Pdo_Mysql' => \Maho\Db\Adapter\Pdo\Mysql::class,
        'Varien_Db_Ddl_Table' => \Maho\Db\Ddl\Table::class,
        'Varien_Db_Expr' => \Maho\Db\Expr::class,
        'Varien_Db_Select' => \Maho\Db\Select::class,
        'Varien_Db_Helper' => \Maho\Db\Helper::class,
        'Varien_Event' => \Maho\Event::class,
        'Varien_Event_Collection' => \Maho\Event\Collection::class,
        'Varien_Event_Observer' => \Maho\Event\Observer::class,
        'Varien_Event_Observer_Collection' => \Maho\Event\Observer\Collection::class,
        'Varien_File_Csv' => \Maho\File\Csv::class,
        'Varien_File_Uploader' => \Maho\File\Uploader::class,
        'Varien_Filter_Array' => \Maho\Filter\ArrayFilter::class,
        'Varien_Filter_Object' => \Maho\Filter\ObjectFilter::class,
        'Varien_Filter_Template' => \Maho\Filter\Template::class,
        'Varien_Filter_Template_Tokenizer_Abstract' => \Maho\Filter\Template\Tokenizer\AbstractTokenizer::class,
        'Varien_Io_Abstract' => \Maho\Io::class,
        'Varien_Io_File' => \Maho\Io\File::class,
        'Varien_Io_Ftp' => \Maho\Io\Ftp::class,
        'Varien_Io_Interface' => \Maho\Io\IoInterface::class,
        'Varien_Io_Sftp' => \Maho\Io\Sftp::class,
        'Varien_Object' => \Maho\DataObject::class,
        'Varien_Object_Cache' => \Maho\DataObject\Cache::class,
        'Varien_Object_Mapper' => \Maho\DataObject\Mapper::class,
        'Varien_Simplexml_Config' => \Maho\Simplexml\Config::class,
        'Varien_Simplexml_Element' => \Maho\Simplexml\Element::class,
        'Varien_Exception' => \Maho\Exception::class,
        'Varien_Profiler' => \Maho\Profiler::class,
    ];

    protected function checkComposer(OutputInterface $output): ?int
    {
        $result = Command::SUCCESS;

        /** @var \Composer\Autoload\ClassLoader $composerClassLoader */
        $composerClassLoader = require MAHO_ROOT_DIR . '/vendor/autoload.php';

        $classMap = $composerClassLoader->getClassMap();
        if (isset($classMap['Mage_Core_Model_App'])) {
            $result = Command::FAILURE;
            $output->writeln('');
            $output->writeln('<comment>Warning: Optimized autoloader detected.</comment>');
            $output->writeln('Ignore if you are in a production environment, otherwise run: composer dump');
        }

        return $result;
    }

    /**
     * Check frontend themes for common issues
     *
     * @return array{errors: array<string>, warnings: array<string>}
     */
    protected function checkFrontendThemes(): array
    {
        $errors = [];
        $warnings = [];

        // Get themes from all packages (including vendor) for parent validation
        $packageThemes = $this->getAllThemesFromPackages();
        $allThemes = $packageThemes['all'];
        $allDesignThemes = $packageThemes['design'];
        $allSkinThemes = $packageThemes['skin'];

        // Get themes only from the project root for checking issues
        // (we don't want to report issues in vendor packages)
        $projectDesignThemes = $this->getThemesFromProjectPath(self::DESIGN_PATH);
        $projectSkinThemes = $this->getThemesFromProjectPath(self::SKIN_PATH);
        $projectThemes = array_unique(array_merge($projectDesignThemes, $projectSkinThemes));

        foreach ($projectThemes as $theme) {
            [$package, $themeName] = explode('/', $theme);

            // Check for orphaned directories
            // A project skin/design directory is not orphaned if a matching counterpart
            // exists in any package (including vendor)
            $hasDesignInProject = in_array($theme, $projectDesignThemes, true);
            $hasSkinInProject = in_array($theme, $projectSkinThemes, true);
            $hasDesignAnywhere = in_array($theme, $allDesignThemes, true);
            $hasSkinAnywhere = in_array($theme, $allSkinThemes, true);

            if ($hasDesignInProject && !$hasSkinInProject && !$hasSkinAnywhere) {
                $warnings[] = "{$theme}: Missing skin directory (expected: " . self::SKIN_PATH . "/{$theme}/)";
            } elseif (!$hasDesignInProject && $hasSkinInProject && !$hasDesignAnywhere) {
                $warnings[] = "{$theme}: Orphaned skin directory (no matching design folder at " . self::DESIGN_PATH . "/{$theme}/)";
            }

            // Check theme.xml if design directory exists in project
            if ($hasDesignInProject) {
                $themeXmlPath = MAHO_ROOT_DIR . '/' . self::DESIGN_PATH . "/{$package}/{$themeName}/etc/theme.xml";

                if (file_exists($themeXmlPath)) {
                    // Validate against ALL themes (including vendor) for parent checking
                    $themeXmlErrors = $this->validateThemeXml($themeXmlPath, $theme, $allThemes);
                    $errors = array_merge($errors, $themeXmlErrors);
                }
            }
        }

        // Check for circular inheritance (only for project themes, but considering all available parents)
        $circularErrors = $this->checkCircularInheritance($projectThemes, $allThemes);
        $errors = array_merge($errors, $circularErrors);

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    /**
     * Get all theme identifiers from all installed packages (including vendor)
     *
     * @return array{design: array<string>, skin: array<string>, all: array<string>}
     */
    private function getAllThemesFromPackages(): array
    {
        $designThemes = [];
        $skinThemes = [];

        foreach (\Maho::listDirectories(self::DESIGN_PATH) as $packageName) {
            foreach (\Maho::listDirectories(self::DESIGN_PATH . '/' . $packageName) as $themeName) {
                $designThemes[] = "{$packageName}/{$themeName}";
            }
        }

        foreach (\Maho::listDirectories(self::SKIN_PATH) as $packageName) {
            foreach (\Maho::listDirectories(self::SKIN_PATH . '/' . $packageName) as $themeName) {
                $skinThemes[] = "{$packageName}/{$themeName}";
            }
        }

        return [
            'design' => array_unique($designThemes),
            'skin' => array_unique($skinThemes),
            'all' => array_unique(array_merge($designThemes, $skinThemes)),
        ];
    }

    /**
     * Get theme identifiers from the project root only (not vendor)
     *
     * @return array<string>
     */
    private function getThemesFromProjectPath(string $relativePath): array
    {
        $themes = [];
        $basePath = MAHO_ROOT_DIR . '/' . $relativePath;

        if (!is_dir($basePath)) {
            return $themes;
        }

        $packages = glob($basePath . '/*', GLOB_ONLYDIR);
        foreach ($packages as $packagePath) {
            $packageName = basename($packagePath);
            $themeDirs = glob($packagePath . '/*', GLOB_ONLYDIR);

            foreach ($themeDirs as $themePath) {
                $themeName = basename($themePath);
                $themes[] = "{$packageName}/{$themeName}";
            }
        }

        return $themes;
    }

    /**
     * Validate theme.xml file
     *
     * @param array<string> $allThemes
     * @return array<string>
     */
    private function validateThemeXml(string $themeXmlPath, string $theme, array $allThemes): array
    {
        $errors = [];

        // Check XML syntax
        libxml_use_internal_errors(true);
        $xml = simplexml_load_file($themeXmlPath);

        if ($xml === false) {
            $xmlErrors = libxml_get_errors();
            $errorMessages = [];
            foreach ($xmlErrors as $error) {
                $errorMessages[] = trim($error->message);
            }
            libxml_clear_errors();
            $errors[] = "{$theme}: Invalid theme.xml syntax - " . implode(', ', $errorMessages);
            return $errors;
        }

        libxml_clear_errors();

        // Check parent theme reference
        if (isset($xml->parent)) {
            $parentTheme = (string) $xml->parent;

            if (!empty($parentTheme) && !in_array($parentTheme, $allThemes, true)) {
                $errors[] = "{$theme}: Parent theme '{$parentTheme}' does not exist";
            }
        }

        return $errors;
    }

    /**
     * Check for circular inheritance in themes
     *
     * @param array<string> $projectThemes Themes in the project to check
     * @param array<string> $allThemes All available themes (including vendor)
     * @return array<string>
     */
    private function checkCircularInheritance(array $projectThemes, array $allThemes): array
    {
        $errors = [];
        $parentMap = [];

        // Build parent map for all themes (need to know parent relationships across all packages)
        foreach ($allThemes as $theme) {
            [$package, $themeName] = explode('/', $theme);

            // Find theme.xml across all packages
            $themeXmlPath = \Maho::findFile(self::DESIGN_PATH . "/{$package}/{$themeName}/etc/theme.xml");

            if ($themeXmlPath !== false) {
                libxml_use_internal_errors(true);
                $xml = simplexml_load_file($themeXmlPath);
                libxml_clear_errors();

                if ($xml !== false && isset($xml->parent)) {
                    $parentMap[$theme] = (string) $xml->parent;
                }
            }
        }

        // Only check for cycles starting from project themes
        foreach ($projectThemes as $theme) {
            if (!isset($parentMap[$theme])) {
                continue;
            }

            $visited = [$theme];
            $current = $parentMap[$theme];

            while (!empty($current)) {
                if (in_array($current, $visited, true)) {
                    $cycle = array_slice($visited, array_search($current, $visited, true));
                    $cycle[] = $current;
                    $errors[] = "{$theme}: Circular inheritance detected (" . implode(' → ', $cycle) . ')';
                    break;
                }
                $visited[] = $current;
                $current = $parentMap[$current] ?? null;
            }
        }

        return array_unique($errors);
    }

    /**
     * Check for usage of deprecated Varien_ classes in user code
     *
     * @return array<string, array<string, array<int>>>
     */
    protected function checkVarienClassUsage(): array
    {
        $findings = [];

        // Directories to scan (user code, not vendor or Maho core)
        $scanDirs = [
            'app/code/local',
            'app/code/community',
        ];

        foreach ($scanDirs as $dir) {
            $fullPath = MAHO_ROOT_DIR . '/' . $dir;
            if (!is_dir($fullPath)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($fullPath, \RecursiveDirectoryIterator::SKIP_DOTS),
            );

            foreach ($iterator as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $content = file_get_contents($file->getPathname());
                if ($content === false) {
                    continue;
                }

                $relativePath = str_replace(MAHO_ROOT_DIR . '/', '', $file->getPathname());
                $lines = explode("\n", $content);

                foreach ($lines as $lineNum => $line) {
                    // Look for Varien_ class references
                    if (preg_match_all('/\bVarien_[A-Za-z_]+/', $line, $matches)) {
                        foreach ($matches[0] as $match) {
                            // Only report if it's a known Varien class that has a Maho replacement
                            $classKey = $this->findVarienClassKey($match);
                            if ($classKey !== null) {
                                $findings[$relativePath][$classKey][] = $lineNum + 1;
                            }
                        }
                    }
                }
            }
        }

        return $findings;
    }

    /**
     * Find the matching Varien class key for a given class name
     * Handles both exact matches and partial matches (e.g., Varien_Data_Form_Element_Text)
     */
    private function findVarienClassKey(string $className): ?string
    {
        // Check for exact match first
        if (isset(self::VARIEN_TO_MAHO_MAP[$className])) {
            return $className;
        }

        // Check if it starts with any known Varien class prefix
        // Sort by length descending to match most specific first
        $keys = array_keys(self::VARIEN_TO_MAHO_MAP);
        usort($keys, fn($a, $b) => strlen($b) - strlen($a));

        foreach ($keys as $key) {
            if (str_starts_with($className, $key . '_') || $className === $key) {
                return $key;
            }
        }

        // It's a Varien_ class but not in our map - still report it generically
        if (str_starts_with($className, 'Varien_')) {
            return $className;
        }

        return null;
    }

    /**
     * Format Varien class usage findings for output
     *
     * @param array<string, array<string, array<int>>> $findings
     * @return array<string>
     */
    private function formatVarienFindings(array $findings): array
    {
        $output = [];

        foreach ($findings as $file => $classes) {
            $classDetails = [];
            foreach ($classes as $className => $lines) {
                $replacement = self::VARIEN_TO_MAHO_MAP[$className] ?? 'Maho\\*';
                $lineList = implode(', ', array_unique($lines));
                $classDetails[] = "{$className} → {$replacement} (lines: {$lineList})";
            }
            $output[] = "{$file}:";
            foreach ($classDetails as $detail) {
                $output[] = "    {$detail}";
            }
        }

        return $output;
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $hasErrors = false;

        // Check for use-include-path in composer.json
        $output->write('Checking composer.json... ');
        if ($this->checkComposer($output) === Command::SUCCESS) {
            $output->writeln('<info>OK</info>');
        } else {
            $hasErrors = true;
            $output->writeln('');
        }

        // Check for M1 core files
        $output->write('Checking Magento/OpenMage core... ');
        $folders = [
            'app/bootstrap.php',
            'app/Mage.php',
            'app/code/core',
        ];
        $existingFolders = [];
        foreach ($folders as $folder) {
            if (file_exists(MAHO_ROOT_DIR . "/{$folder}")) {
                $existingFolders[] = $folder;
            }
        }

        if (empty($existingFolders)) {
            $output->writeln('<info>OK</info>');
        } else {
            $hasErrors = true;
            $output->writeln('');
            $output->writeln('<error>Error: Detected files/folder from an old Magento/OpenMage core:</error>');
            foreach ($existingFolders as $folder) {
                $output->writeln('- ' . $folder);
            }
            $output->writeln('Make sure you delete them,');
            $output->writeln('unless you need to override a specific file from the core (not advisable).');
            $output->writeln('');
        }

        // Check for custom API
        $output->write('Checking custom APIs... ');
        exec('grep -ir -l -E "urn:Magento|urn:OpenMage" . --include="*.xml"', $matchingFiles, $returnCode);

        if (empty($matchingFiles)) {
            $output->writeln('<info>OK</info>');
        } else {
            $hasErrors = true;
            $output->writeln('');
            $output->writeln('<error>Error: Found "urn:Magento" or "urn:OpenMage" in the following files:</error>');
            foreach ($matchingFiles as $file) {
                $output->writeln('- ' . substr($file, 2));
            }
            $output->writeln('Replace all occurrences of "urn:Magento" or "urn:OpenMage" with "urn:Maho".');
            $output->writeln('');
        }

        // Check for deprecated folders
        $output->write('Checking for deprecated folders... ');
        $folders = [
            'app/code/core/Zend',
            'lib/Cm',
            'lib/Credis',
            'lib/mcryptcompat',
            'lib/Pelago',
            'lib/phpseclib',
            'lib/Zend',
            'skin',
        ];
        $existingFolders = [];
        foreach ($folders as $folder) {
            if (file_exists(MAHO_ROOT_DIR . "/{$folder}")) {
                $existingFolders[] = $folder;
            }
        }
        if (empty($existingFolders)) {
            $output->writeln('<info>OK</info>');
        } else {
            $hasErrors = true;
            $output->writeln('');
            $output->writeln('<error>Error: Found deprecated folders:</error>');
            foreach ($existingFolders as $folder) {
                $output->writeln('- ' . $folder);
            }
            $output->writeln('You should remove them to avoid unpredictable behaviors.');
            $output->writeln('');
        }

        // Check frontend themes
        $output->write('Checking frontend themes... ');
        $themeResults = $this->checkFrontendThemes();

        if (empty($themeResults['errors']) && empty($themeResults['warnings'])) {
            $output->writeln('<info>OK</info>');
        } else {
            if (!empty($themeResults['errors'])) {
                $hasErrors = true;
                $output->writeln('');
                $output->writeln('<error>Error: Frontend theme issues found:</error>');
                foreach ($themeResults['errors'] as $error) {
                    $output->writeln('- ' . $error);
                }
            }
            if (!empty($themeResults['warnings'])) {
                if (empty($themeResults['errors'])) {
                    $output->writeln('');
                }
                $output->writeln('<comment>Warning: Frontend theme warnings:</comment>');
                foreach ($themeResults['warnings'] as $warning) {
                    $output->writeln('- ' . $warning);
                }
            }
            $output->writeln('');
        }

        // Check for deprecated Varien_ class usage
        $output->write('Checking for deprecated Varien_ classes... ');
        $varienFindings = $this->checkVarienClassUsage();

        if (empty($varienFindings)) {
            $output->writeln('<info>OK</info>');
        } else {
            $output->writeln('');
            $output->writeln('<comment>Warning: Found deprecated Varien_ class usage:</comment>');
            $output->writeln('These classes have been migrated to the Maho\\ namespace.');
            $output->writeln('Class aliases exist for backward compatibility, but you should migrate to the new classes.');
            $output->writeln('');
            foreach ($this->formatVarienFindings($varienFindings) as $line) {
                $output->writeln($line);
            }
            $output->writeln('');
            $output->writeln('See: https://github.com/MahoCommerce/maho/pull/340');
            $output->writeln('');
        }

        if ($hasErrors) {
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
