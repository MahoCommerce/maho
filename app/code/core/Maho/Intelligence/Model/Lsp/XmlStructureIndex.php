<?php

/**
 * Maho
 *
 * @package    Maho_Intelligence
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

/**
 * Builds and maintains a dynamic index of valid XML child elements by
 * merging all module XML files from disk (config.xml, system.xml, adminhtml.xml)
 * and walking the resulting tree.
 *
 * Layout XML files are merged separately from all theme layout directories.
 *
 * The index is rebuilt on demand when an XML file changes (triggered by
 * LSP didChange/didSave notifications).
 */
class Maho_Intelligence_Model_Lsp_XmlStructureIndex
{
    private const FILE_TYPES = ['config.xml', 'system.xml', 'adminhtml.xml'];

    /**
     * Merged children map per file type.
     * E.g. ['config.xml' => ['config' => ['global', 'modules', ...], 'config/global' => ['models', ...]], ...]
     *
     * @var array<string, array<string, list<string>>>
     */
    private array $childrenMap = [];

    /** @var array<string, list<string>> */
    private array $layoutChildrenMap = [];

    private bool $built = false;

    private string $baseDir;

    public function __construct(?string $baseDir = null)
    {
        $this->baseDir = $baseDir ?? BP;
    }

    /**
     * Get valid child element names for a given parent path in a specific file type.
     *
     * @return list<string>
     */
    public function getChildren(string $parentPath, string $filename): array
    {
        if (!$this->built) {
            $this->rebuild();
        }

        if ($filename !== '' && isset($this->childrenMap[$filename])) {
            return $this->childrenMap[$filename][$parentPath] ?? [];
        }

        return $this->layoutChildrenMap[$parentPath] ?? [];
    }

    /**
     * Rebuild the entire index by re-reading and merging all XML files from disk.
     */
    public function rebuild(): void
    {
        $this->childrenMap = [];
        $this->layoutChildrenMap = [];

        foreach (self::FILE_TYPES as $fileType) {
            $this->childrenMap[$fileType] = $this->buildChildrenMapForFileType($fileType);
        }

        $this->layoutChildrenMap = $this->buildLayoutChildrenMap();
        $this->built = true;
    }

    /**
     * Mark the index as stale so it will be rebuilt on next access.
     */
    public function invalidate(): void
    {
        $this->built = false;
    }

    /**
     * Merge all module files of a given type and extract parent→children mappings.
     *
     * @return array<string, list<string>>
     */
    private function buildChildrenMapForFileType(string $fileType): array
    {
        $pattern = $this->baseDir . '/app/code/*/*/*/etc/' . $fileType;
        $files = glob($pattern) ?: [];

        if ($files === []) {
            return [];
        }

        $merged = new \Maho\Simplexml\Config();
        $merged->loadString('<?xml version="1.0"?><config/>');

        $fileConfig = new \Maho\Simplexml\Config();
        foreach ($files as $file) {
            if ($fileConfig->loadFile($file)) {
                $merged->extend($fileConfig, true);
            }
        }

        $root = $merged->getNode();
        if ($root === null) {
            return [];
        }

        $map = [];
        $this->walkXmlTree($root, $root->getName(), $map);
        return $map;
    }

    /**
     * Merge all layout XML files from theme directories.
     *
     * @return array<string, list<string>>
     */
    private function buildLayoutChildrenMap(): array
    {
        $layoutDirs = glob($this->baseDir . '/app/design/{frontend,adminhtml}/*/default/layout', GLOB_BRACE) ?: [];

        $merged = new \Maho\Simplexml\Config();
        $merged->loadString('<?xml version="1.0"?><layout/>');

        $fileConfig = new \Maho\Simplexml\Config();
        foreach ($layoutDirs as $dir) {
            $files = glob($dir . '/*.xml') ?: [];
            foreach ($files as $file) {
                if ($fileConfig->loadFile($file)) {
                    $merged->extend($fileConfig, true);
                }
            }
        }

        $root = $merged->getNode();
        if ($root === null) {
            return [];
        }

        $map = [];
        $this->walkXmlTree($root, $root->getName(), $map);
        return $map;
    }

    /**
     * Recursively walk an XML tree and collect parent→children name mappings.
     *
     * @param array<string, list<string>> $map
     */
    private function walkXmlTree(\SimpleXMLElement $node, string $currentPath, array &$map): void
    {
        $childNames = [];
        foreach ($node->children() as $child) {
            $name = $child->getName();
            if (!in_array($name, $childNames, true)) {
                $childNames[] = $name;
            }
            $childPath = $currentPath . '/' . $name;
            $this->walkXmlTree($child, $childPath, $map);
        }

        if ($childNames !== [] && $currentPath !== '') {
            $map[$currentPath] = $childNames;
        }
    }
}
