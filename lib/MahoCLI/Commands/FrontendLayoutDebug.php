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
use Mage_Core_Block_Abstract;
use Mage_Core_Block_Template;
use Mage_Core_Model_Layout;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TreeHelper;
use Symfony\Component\Console\Helper\TreeNode;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'frontend:layout:debug',
    description: 'Debug layout for a given URL showing handles, XML files, and block tree',
)]
class FrontendLayoutDebug extends BaseMahoCommand
{
    /**
     * Collected layout XML files
     * @var array<string, array<string>>
     */
    private array $loadedXmlFiles = [];

    /**
     * Removed blocks
     * @var array<string>
     */
    private array $removedBlocks = [];

    #[\Override]
    protected function configure(): void
    {
        $this->addArgument(
            'url',
            InputArgument::REQUIRED,
            'The URL to debug (e.g., http://maho.test/men/new-arrivals.html)',
        );
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $url = $input->getArgument('url');

        // Parse URL
        $parsedUrl = parse_url($url);
        if (!$parsedUrl || !isset($parsedUrl['host'])) {
            $io->error('Invalid URL provided');
            return Command::FAILURE;
        }

        $path = $parsedUrl['path'] ?? '/';

        $io->title('Layout Debug: ' . $url);

        // Dispatch the request and capture layout
        $result = $this->dispatchAndCaptureLayout($path);

        if (!$result['success']) {
            $io->error($result['error'] ?? 'Failed to dispatch request');
            return Command::FAILURE;
        }

        $layout = $result['layout'];
        $controller = $result['controller'];
        $store = Mage::app()->getStore();

        // Build route info from the dispatched controller
        $routeInfo = $this->buildRouteInfo($controller);

        // Display request info
        $this->displayRequestInfo($output, $routeInfo, $store);

        // Collect layout XML files
        $this->loadedXmlFiles = $this->collectLayoutXmlFiles();

        // Collect removed blocks
        $this->collectRemovedBlocks($layout);

        // Get handles
        $handles = $layout->getUpdate()->getHandles();

        // Display layout handles
        $this->displayLayoutHandles($output, $handles);

        // Display layout XML files
        $this->displayLayoutXmlFiles($io, $output, $this->loadedXmlFiles, $handles);

        // Display block tree
        $this->displayBlockTree($io, $layout);

        // Display removed blocks
        if (!empty($this->removedBlocks)) {
            $this->displayRemovedBlocks($io);
        }

        // Check for warnings
        $warnings = $this->checkForWarnings($layout);
        if (!empty($warnings)) {
            $this->displayWarnings($io, $warnings);
        }

        return Command::SUCCESS;
    }

    /**
     * Dispatch the request using real controllers and capture the layout
     *
     * @return array{success: bool, layout?: Mage_Core_Model_Layout, controller?: \Mage_Core_Controller_Varien_Action, error?: string}
     */
    private function dispatchAndCaptureLayout(string $path): array
    {
        // Set up the request
        $request = Mage::app()->getRequest();
        $request->setRequestUri($path);
        $request->setPathInfo();

        // Initialize front controller
        /** @var \Mage_Core_Controller_Varien_Front $front */
        $front = Mage::app()->getFrontController();
        $front->init();

        // Apply URL rewrites (determines store and rewrites path)
        /** @var \Mage_Core_Model_Url_Rewrite_Request $rewriteRequest */
        $rewriteRequest = Mage::getModel('core/url_rewrite_request', [
            'request' => $request,
            'routers' => $front->getRouters(),
        ]);
        $rewriteRequest->rewrite();

        // Dispatch with output buffering to capture/discard HTML output
        ob_start();

        try {
            foreach ($front->getRouters() as $router) {
                /** @var \Mage_Core_Controller_Varien_Router_Abstract $router */
                if ($router->match($request)) {
                    break;
                }
            }
        } catch (\Exception $e) {
            ob_end_clean();
            return ['success' => false, 'error' => $e->getMessage()];
        }

        ob_end_clean();

        // Get the dispatched controller and its layout
        $controller = $front->getAction();
        if (!$controller) {
            return ['success' => false, 'error' => 'No controller was dispatched'];
        }

        $layout = $controller->getLayout();
        if (!$layout) {
            return ['success' => false, 'error' => 'No layout was generated'];
        }

        return [
            'success' => true,
            'layout' => $layout,
            'controller' => $controller,
        ];
    }

    /**
     * Build route info from the dispatched controller
     *
     * @return array{module: string, controller: string, action: string, controller_class: string, full_action: string}
     */
    private function buildRouteInfo(\Mage_Core_Controller_Varien_Action $controller): array
    {
        $request = $controller->getRequest();

        $controllerName = $request->getControllerName() ?: 'index';
        $action = $request->getActionName() ?: 'index';

        return [
            'module' => $request->getControllerModule() ?: 'Unknown_Module',
            'controller' => $controllerName,
            'action' => $action,
            'controller_class' => $controller::class,
            'full_action' => $controller->getFullActionName('_'),
        ];
    }

    /**
     * Display request info section
     *
     * @param array{module: string, controller: string, action: string, controller_class: string, full_action: string} $routeInfo
     */
    private function displayRequestInfo(OutputInterface $output, array $routeInfo, \Mage_Core_Model_Store $store): void
    {
        $design = Mage::getSingleton('core/design_package');

        // Get theme hierarchy
        $package = $design->getPackageName() ?: 'base';
        $theme = $design->getTheme('default') ?: 'default';
        $themeChain = $this->getThemeInheritanceChain($package, $theme);

        $table = new Table($output);
        $table->setHeaderTitle('Request Info');
        $table->addRows([
            ['Route', $routeInfo['full_action']],
            ['Module', $routeInfo['module']],
            ['Controller', $routeInfo['controller_class']],
            ['Action', $routeInfo['action'] . 'Action'],
            ['Store', $store->getCode() . ' (' . $store->getName() . ')'],
            ['Design', implode(' -> ', $themeChain)],
        ]);
        $table->render();
    }

    /**
     * Get theme inheritance chain
     *
     * @return array<string>
     */
    private function getThemeInheritanceChain(string $package, string $theme): array
    {
        $chain = ["{$package}/{$theme}"];

        // Check theme.xml for parent
        $themeXmlPath = \Maho::findFile("app/design/frontend/{$package}/{$theme}/etc/theme.xml");
        if ($themeXmlPath) {
            $xml = simplexml_load_file($themeXmlPath);
            if ($xml && isset($xml->parent)) {
                $parent = (string) $xml->parent;
                if (!empty($parent) && str_contains($parent, '/')) {
                    [$parentPackage, $parentTheme] = explode('/', $parent, 2);
                    if (!empty($parentPackage) && !empty($parentTheme)) {
                        $chain = array_merge($chain, $this->getThemeInheritanceChain($parentPackage, $parentTheme));
                    }
                }
            }
        }

        // Always ends with base/default if not already there
        if (end($chain) !== 'base/default') {
            $chain[] = 'base/default';
        }

        return array_unique($chain);
    }

    /**
     * Collect layout XML files
     *
     * @return array<string, array<string>>
     */
    private function collectLayoutXmlFiles(): array
    {
        $files = [];
        $design = Mage::getSingleton('core/design_package');
        $area = $design->getArea();

        // Get layout updates from config
        $updatesRoot = Mage::app()->getConfig()->getNode($area . '/layout/updates');
        if (!$updatesRoot) {
            return $files;
        }

        $updates = $updatesRoot->asArray();

        // Add local.xml at the end
        $updateFiles = [];
        foreach ($updates as $moduleName => $updateNode) {
            if (!empty($updateNode['file'])) {
                $updateFiles[$updateNode['file']] = $moduleName;
            }
        }
        $updateFiles['local.xml'] = 'local';

        // Resolve actual file paths
        foreach (array_keys($updateFiles) as $file) {
            $filename = $design->getLayoutFilename($file, ['_area' => $area]);
            if ($filename && is_readable($filename)) {
                $relativePath = \Maho::toRelativePath($filename);
                $handles = $this->getHandlesFromLayoutFile($filename);
                $files[$relativePath] = $handles;
            }
        }

        return $files;
    }

    /**
     * Get handles defined in a layout file
     *
     * @return array<string>
     */
    private function getHandlesFromLayoutFile(string $filename): array
    {
        $handles = [];
        $content = file_get_contents($filename);
        if ($content === false) {
            return $handles;
        }

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($content);
        libxml_clear_errors();

        if ($xml instanceof \SimpleXMLElement) {
            foreach ($xml->children() as $handle) {
                $handles[] = $handle->getName();
            }
        }

        return $handles;
    }

    /**
     * Collect removed blocks from layout XML
     */
    private function collectRemovedBlocks(Mage_Core_Model_Layout $layout): void
    {
        $this->removedBlocks = [];
        $xml = $layout->getUpdate()->asSimplexml();

        if (!$xml) {
            return;
        }

        // Find all <remove> elements
        $removes = $xml->xpath('//remove[@name]');
        if ($removes) {
            foreach ($removes as $remove) {
                $this->removedBlocks[] = (string) $remove['name'];
            }
        }

        $this->removedBlocks = array_unique($this->removedBlocks);
    }

    /**
     * Check for layout warnings
     *
     * @return array<string>
     */
    private function checkForWarnings(Mage_Core_Model_Layout $layout): array
    {
        $warnings = [];

        foreach ($layout->getAllBlocks() as $name => $block) {
            if ($block instanceof Mage_Core_Block_Template) {
                $template = $block->getTemplate();
                if ($template) {
                    try {
                        $templateFile = $block->getTemplateFile();
                        if (!$templateFile || !is_readable($templateFile)) {
                            $warnings[] = "Template not found: {$template} (block: {$name})";
                        }
                    } catch (\Exception $e) {
                        $warnings[] = "Template error: {$template} (block: {$name}) - {$e->getMessage()}";
                    }
                }
            }
        }

        return $warnings;
    }

    /**
     * Display layout handles
     *
     * @param array<string> $handles
     */
    private function displayLayoutHandles(OutputInterface $output, array $handles): void
    {
        $table = new Table($output);
        $table->setHeaderTitle('Layout Handles (in load order)');

        foreach ($handles as $index => $handle) {
            $table->addRow([($index + 1) . '.', $handle]);
        }

        $table->render();
    }

    /**
     * Display layout XML files (only those with active handles)
     *
     * @param array<string, array<string>> $files
     * @param array<string> $activeHandles
     */
    private function displayLayoutXmlFiles(SymfonyStyle $io, OutputInterface $output, array $files, array $activeHandles): void
    {
        $table = new Table($output);
        $table->setHeaderTitle('Layout XML Files');
        $table->setHeaders(['File', 'Theme', 'Active Handles']);

        $activeFiles = 0;
        foreach ($files as $file => $handles) {
            // Find which handles in this file are active (deduplicated)
            $matchingHandles = array_unique(array_intersect($handles, $activeHandles));
            if (!empty($matchingHandles)) {
                $activeFiles++;
                // Extract theme and filename
                [$filename, $theme] = $this->parseLayoutPath($file);
                $table->addRow([$filename, $theme, implode(', ', $matchingHandles)]);
            }
        }

        $table->render();
        $io->newLine();
        $io->text(sprintf('%d files with active handles (of %d total layout files)', $activeFiles, count($files)));
    }

    /**
     * Parse layout path into filename and theme
     *
     * @return array{0: string, 1: string}
     */
    private function parseLayoutPath(string $path): array
    {
        // Extract theme from path like app/design/frontend/package/theme/layout/file.xml
        if (preg_match('#app/design/frontend/([^/]+/[^/]+)/layout/(.+)$#', $path, $matches)) {
            return [$matches[2], $matches[1]];
        }
        return [$path, ''];
    }

    /**
     * Format template path to show theme with colored dot separator
     */
    private function formatTemplatePath(string $path): string
    {
        $blueDot = '<fg=blue>●</>';

        // Extract theme from path like app/design/frontend/package/theme/template/path/file.phtml
        if (preg_match('#app/design/frontend/([^/]+/[^/]+)/template/(.+)$#', $path, $matches)) {
            return sprintf('%s %s %s', $matches[2], $blueDot, $matches[1]);
        }
        return $path;
    }

    /**
     * Display block tree using Symfony TreeHelper
     */
    private function displayBlockTree(SymfonyStyle $io, Mage_Core_Model_Layout $layout): void
    {
        $io->section('Block Tree');

        // Legend
        $greenDot = '<fg=green>●</>';
        $orangeDot = '<fg=#FFA500>●</>';
        $redDot = '<fg=red>●</>';
        $blueDot = '<fg=blue>●</>';
        $io->writeln("Legend: {$greenDot} block_name {$orangeDot} type {$redDot} template {$blueDot} theme");
        $io->newLine();

        $allBlocks = $layout->getAllBlocks();

        // Find root blocks (blocks without parents)
        $rootBlocks = [];
        foreach ($allBlocks as $name => $block) {
            $parent = $block->getParentBlock();
            if (!$parent) {
                $rootBlocks[$name] = $block;
            }
        }

        if (empty($rootBlocks)) {
            $io->text('No blocks generated');
            return;
        }

        // Separate main 'root' block from orphan blocks
        $mainRoot = null;
        $orphanBlocks = [];
        foreach ($rootBlocks as $name => $block) {
            if ($name === 'root') {
                $mainRoot = $block;
            } else {
                $orphanBlocks[$name] = $block;
            }
        }

        // Count totals
        $totalBlocks = count($allBlocks);
        $totalTemplates = 0;
        foreach ($allBlocks as $block) {
            if ($block instanceof Mage_Core_Block_Template && $block->getTemplate()) {
                $totalTemplates++;
            }
        }

        // Display main root tree
        if ($mainRoot) {
            $rootNode = $this->buildBlockTreeNode($mainRoot, 'root');
            $tree = TreeHelper::createTree($io, $rootNode);
            $tree->render();
            $io->newLine();
        }

        $io->text(sprintf('Total: %d blocks, %d templates', $totalBlocks, $totalTemplates));

        // Display orphan blocks separately
        if (!empty($orphanBlocks)) {
            $io->newLine();
            $io->writeln('<comment>Orphan Blocks (not attached to main tree):</comment>');
            foreach ($orphanBlocks as $name => $block) {
                $label = $this->formatBlockLabel($block, $name);
                $io->writeln("  {$label}");
            }
        }
    }

    /**
     * Build a TreeNode for a block and its children
     */
    private function buildBlockTreeNode(Mage_Core_Block_Abstract $block, string $name): TreeNode
    {
        $label = $this->formatBlockLabel($block, $name);
        $node = new TreeNode($label);

        // Add children
        $children = $block->getChild();
        if (is_array($children)) {
            foreach ($children as $childAlias => $childBlock) {
                $childName = $childBlock->getNameInLayout();
                $childNode = $this->buildBlockTreeNode($childBlock, $childName ?: $childAlias);
                $node->addChild($childNode);
            }
        }

        return $node;
    }

    /**
     * Format block label for display - template inline for compactness
     */
    private function formatBlockLabel(Mage_Core_Block_Abstract $block, string $name): string
    {
        $type = $block->getType() ?: $block::class;

        // Colored dots as separators
        $greenDot = '<fg=green>●</>';
        $orangeDot = '<fg=#FFA500>●</>';
        $redDot = '<fg=red>●</>';

        // For template blocks, show template inline
        if ($block instanceof Mage_Core_Block_Template && $block->getTemplate()) {
            try {
                $templateFile = $block->getTemplateFile();
                $templatePath = $templateFile ? $this->formatTemplatePath(\Maho::toRelativePath($templateFile)) : $block->getTemplate();
                return "{$greenDot} {$name} {$orangeDot} {$type} {$redDot} {$templatePath}";
            } catch (\Exception $e) {
                return "{$greenDot} {$name} {$orangeDot} {$type} {$redDot} {$block->getTemplate()} <fg=red>(not found)</>";
            }
        }

        return "{$greenDot} {$name} {$orangeDot} {$type}";
    }

    /**
     * Display removed blocks
     */
    private function displayRemovedBlocks(SymfonyStyle $io): void
    {
        $io->section('Removed Blocks');

        foreach ($this->removedBlocks as $block) {
            $io->text("  - {$block}");
        }
    }

    /**
     * Display warnings
     *
     * @param array<string> $warnings
     */
    private function displayWarnings(SymfonyStyle $io, array $warnings): void
    {
        $io->section('Warnings');

        foreach ($warnings as $warning) {
            $io->text("  ! {$warning}");
        }
    }
}
