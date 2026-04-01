<?php

/**
 * Maho
 *
 * @package    Maho_Intelligence
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

class Maho_Intelligence_Model_Provider_ClassAlias
{
    private const TYPES = ['model', 'block', 'helper'];

    /**
     * Resolve a single alias to its class name, file path, and rewrite info
     */
    public function resolveAlias(string $type, string $alias): array
    {
        $config = Mage::getConfig();
        $classArr = explode('/', trim($alias));
        $group = $classArr[0];
        $class = $classArr[1] ?? ($type === 'helper' ? 'data' : null);

        if ($type === 'resource_model') {
            return $this->resolveResourceModelAlias($config, $alias, $group, $class);
        }

        $groupNode = $config->getNode("global/{$type}s/{$group}");
        if (!$groupNode) {
            return [
                'alias' => $alias,
                'class' => null,
                'file' => null,
                'rewritten_by' => null,
                'error' => "Unknown group: {$group}",
            ];
        }

        $rewrittenBy = null;
        $className = '';

        if ($class && isset($groupNode->rewrite->$class)) {
            $className = (string) $groupNode->rewrite->$class;
            $rewrittenBy = $className;
        }

        if (empty($className)) {
            $classPrefix = $groupNode->getClassName();
            if (empty($classPrefix)) {
                $classPrefix = 'mage_' . $group . '_' . $type;
            }
            if (!empty($class)) {
                $classPrefix .= '_' . $class;
            }
            $className = uc_words($classPrefix);
        }

        return [
            'alias' => $alias,
            'class' => $className,
            'file' => $this->findClassFile($className),
            'rewritten_by' => $rewrittenBy,
        ];
    }

    private function resolveResourceModelAlias(
        Mage_Core_Model_Config $config,
        string $alias,
        string $group,
        ?string $class,
    ): array {
        $groupNode = $config->getNode("global/models/{$group}");

        // Follow the resourceModel indirection: catalog → catalog_resource
        if ($groupNode && !empty($groupNode->resourceModel)) {
            $resourceGroup = (string) $groupNode->resourceModel;
            $resourceNode = $config->getNode("global/models/{$resourceGroup}");
        } else {
            // The group itself may already be a resource model group
            $resourceGroup = $group;
            $resourceNode = $groupNode;
        }

        if (!$resourceNode) {
            return [
                'alias' => $alias,
                'class' => null,
                'file' => null,
                'rewritten_by' => null,
                'error' => "Unknown resource model group: {$group}",
            ];
        }

        $rewrittenBy = null;
        $className = '';

        if ($class && isset($resourceNode->rewrite->$class)) {
            $className = (string) $resourceNode->rewrite->$class;
            $rewrittenBy = $className;
        }

        if (empty($className)) {
            $classPrefix = $resourceNode->getClassName();
            if (empty($classPrefix)) {
                $classPrefix = 'mage_' . $resourceGroup . '_model';
            }
            if (!empty($class)) {
                $classPrefix .= '_' . $class;
            }
            $className = uc_words($classPrefix);
        }

        return [
            'alias' => $alias,
            'class' => $className,
            'file' => $this->findClassFile($className),
            'rewritten_by' => $rewrittenBy,
        ];
    }

    /**
     * Get all aliases for a given type (model, block, helper, resource_model)
     */
    public function getAllAliases(string $type): array
    {
        $config = Mage::getConfig();
        $result = [];

        if ($type === 'resource_model') {
            return $this->getAllResourceModelAliases();
        }

        $groupsNode = $config->getNode("global/{$type}s");
        if (!$groupsNode) {
            return [];
        }

        foreach ($groupsNode->children() as $group => $groupConfig) {
            $classPrefix = (string) ($groupConfig->class ?? '');
            if (empty($classPrefix)) {
                continue;
            }

            $classes = $this->findClassesWithPrefix($classPrefix);
            foreach ($classes as $className) {
                $suffix = substr($className, strlen($classPrefix) + 1);
                if ($suffix === '') {
                    continue;
                }
                $alias = $group . '/' . strtolower($suffix);
                $result[$alias] = [
                    'alias' => $alias,
                    'class' => $className,
                    'group' => $group,
                ];
            }
        }

        ksort($result);
        return $result;
    }

    /**
     * Get all class rewrites with conflict detection
     */
    public function getAllRewrites(): array
    {
        $config = Mage::getConfig();
        $rewrites = [];

        foreach (self::TYPES as $type) {
            $groupsNode = $config->getNode("global/{$type}s");
            if (!$groupsNode) {
                continue;
            }

            foreach ($groupsNode->children() as $group => $groupConfig) {
                if (!isset($groupConfig->rewrite)) {
                    continue;
                }

                foreach ($groupConfig->rewrite->children() as $class => $rewriteClass) {
                    $alias = "{$group}/{$class}";
                    $rewriteClassName = (string) $rewriteClass;

                    if (!isset($rewrites[$alias])) {
                        $rewrites[$alias] = [
                            'alias' => $alias,
                            'type' => $type,
                            'original_class' => $this->getOriginalClass($type, $group, (string) $class),
                            'rewrites' => [],
                        ];
                    }

                    $rewrites[$alias]['rewrites'][] = $rewriteClassName;
                    $rewrites[$alias]['conflict'] = count($rewrites[$alias]['rewrites']) > 1;
                }
            }
        }

        ksort($rewrites);
        return $rewrites;
    }

    private function getAllResourceModelAliases(): array
    {
        $config = Mage::getConfig();
        $result = [];

        $modelsNode = $config->getNode('global/models');
        if (!$modelsNode) {
            return [];
        }

        foreach ($modelsNode->children() as $group => $groupConfig) {
            if (!isset($groupConfig->resourceModel)) {
                continue;
            }

            $resourceGroup = (string) $groupConfig->resourceModel;
            $resourceNode = $config->getNode("global/models/{$resourceGroup}");
            if (!$resourceNode || !isset($resourceNode->class)) {
                continue;
            }

            $classPrefix = (string) $resourceNode->class;
            $classes = $this->findClassesWithPrefix($classPrefix);
            foreach ($classes as $className) {
                $suffix = substr($className, strlen($classPrefix) + 1);
                if ($suffix === '') {
                    continue;
                }
                $alias = $resourceGroup . '/' . strtolower($suffix);
                $result[$alias] = [
                    'alias' => $alias,
                    'class' => $className,
                    'group' => $resourceGroup,
                ];
            }
        }

        ksort($result);
        return $result;
    }

    private function getOriginalClass(string $type, string $group, string $class): string
    {
        $config = Mage::getConfig();
        $groupNode = $config->getNode("global/{$type}s/{$group}");
        $classPrefix = $groupNode ? $groupNode->getClassName() : 'mage_' . $group . '_' . $type;
        return uc_words($classPrefix . '_' . $class);
    }

    private function findClassFile(string $className): ?string
    {
        $file = str_replace(['_', '\\'], DIRECTORY_SEPARATOR, $className) . '.php';

        $paths = [
            BP . '/app/code/core/' . $file,
            BP . '/app/code/community/' . $file,
            BP . '/app/code/local/' . $file,
        ];

        foreach ($paths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        foreach (spl_autoload_functions() as $autoloader) {
            if (is_array($autoloader) && $autoloader[0] instanceof \Composer\Autoload\ClassLoader) {
                $found = $autoloader[0]->findFile($className);
                if ($found !== false) {
                    return realpath($found) ?: $found;
                }
                break;
            }
        }

        return null;
    }

    private function findClassesWithPrefix(string $prefix): array
    {
        $relativePart = str_replace('_', DIRECTORY_SEPARATOR, $prefix);
        $classes = [];

        foreach (['core', 'community', 'local'] as $pool) {
            $dir = BP . "/app/code/{$pool}/" . $relativePart;
            if (!is_dir($dir)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            );

            foreach ($iterator as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }
                $relativePath = substr($file->getPathname(), strlen($dir) + 1, -4);
                $classSuffix = str_replace(DIRECTORY_SEPARATOR, '_', $relativePath);
                $className = $prefix . '_' . $classSuffix;
                $classes[$className] = true;
            }
        }

        return array_keys($classes);
    }
}
