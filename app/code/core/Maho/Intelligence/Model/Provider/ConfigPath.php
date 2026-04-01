<?php

/**
 * Maho
 *
 * @package    Maho_Intelligence
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

class Maho_Intelligence_Model_Provider_ConfigPath
{
    private ?array $cachedPaths = null;

    /**
     * Get all system.xml configuration paths with metadata
     */
    public function getAllPaths(): array
    {
        if ($this->cachedPaths !== null) {
            return $this->cachedPaths;
        }

        $result = [];
        $systemXmlFiles = $this->findSystemXmlFiles();

        foreach ($systemXmlFiles as $file) {
            $xml = simplexml_load_file($file);
            if (!$xml || !isset($xml->sections)) {
                continue;
            }

            foreach ($xml->sections->children() as $section) {
                $sectionName = $section->getName();
                $sectionLabel = (string) ($section->label ?? $sectionName);

                if (!isset($section->groups)) {
                    continue;
                }

                foreach ($section->groups->children() as $group) {
                    $groupName = $group->getName();
                    $groupLabel = (string) ($group->label ?? $groupName);

                    if (!isset($group->fields)) {
                        continue;
                    }

                    foreach ($group->fields->children() as $field) {
                        $fieldName = $field->getName();
                        $path = "{$sectionName}/{$groupName}/{$fieldName}";

                        $result[$path] = [
                            'path' => $path,
                            'label' => (string) ($field->label ?? $fieldName),
                            'section_label' => $sectionLabel,
                            'group_label' => $groupLabel,
                            'type' => (string) ($field->frontend_type ?? ''),
                            'source_model' => (string) ($field->source_model ?? ''),
                            'default' => $this->getDefaultValue($path),
                        ];
                    }
                }
            }
        }

        ksort($result);
        $this->cachedPaths = $result;
        return $result;
    }

    /**
     * Get metadata for a specific config path
     */
    public function getPathInfo(string $path): ?array
    {
        $allPaths = $this->getAllPaths();
        return $allPaths[$path] ?? null;
    }

    private function getDefaultValue(string $path): ?string
    {
        $node = Mage::getConfig()->getNode("default/{$path}");
        return $node !== false ? (string) $node : null;
    }

    private function findSystemXmlFiles(): array
    {
        $files = [];
        $codePools = ['core', 'community', 'local'];

        foreach ($codePools as $pool) {
            $dir = BP . "/app/code/{$pool}";
            if (!is_dir($dir)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            );

            foreach ($iterator as $file) {
                if ($file->getFilename() === 'system.xml') {
                    $files[] = $file->getPathname();
                }
            }
        }

        return $files;
    }
}
