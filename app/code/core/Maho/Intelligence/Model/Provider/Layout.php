<?php

/**
 * Maho
 *
 * @package    Maho_Intelligence
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

class Maho_Intelligence_Model_Provider_Layout
{
    /**
     * Get layout handles and their block hierarchy for an area
     */
    public function getHandles(string $area = 'frontend'): array
    {
        $updatesNode = Mage::getConfig()->getNode("{$area}/layout/updates");
        if (!$updatesNode) {
            return [];
        }

        $result = [];
        foreach ($updatesNode->children() as $module) {
            $file = (string) ($module->file ?? '');
            if (empty($file)) {
                continue;
            }

            $handles = $this->parseLayoutFile($area, $file);
            foreach ($handles as $handle => $blocks) {
                $result[$handle] = [
                    'handle' => $handle,
                    'file' => $file,
                    'blocks' => $blocks,
                ];
            }
        }

        ksort($result);
        return $result;
    }

    private function parseLayoutFile(string $area, string $file): array
    {
        $designPackage = Mage::getSingleton('core/design_package');
        $filename = $designPackage->getLayoutFilename($file, [
            '_area' => $area,
        ]);

        if (!$filename || !file_exists($filename)) {
            return [];
        }

        $xml = simplexml_load_file($filename);
        if (!$xml) {
            return [];
        }

        $handles = [];
        foreach ($xml->children() as $handle) {
            $handleName = $handle->getName();
            $handles[$handleName] = $this->extractBlocks($handle);
        }

        return $handles;
    }

    private function extractBlocks(\SimpleXMLElement $node): array
    {
        $blocks = [];

        foreach ($node->children() as $child) {
            if ($child->getName() === 'block') {
                $block = [
                    'type' => (string) ($child['type'] ?? ''),
                    'name' => (string) ($child['name'] ?? ''),
                    'template' => (string) ($child['template'] ?? ''),
                ];

                $childBlocks = $this->extractBlocks($child);
                if (!empty($childBlocks)) {
                    $block['children'] = $childBlocks;
                }

                $blocks[] = $block;
            } elseif ($child->getName() === 'reference') {
                $refBlocks = $this->extractBlocks($child);
                if (!empty($refBlocks)) {
                    $blocks[] = [
                        'reference' => (string) ($child['name'] ?? ''),
                        'children' => $refBlocks,
                    ];
                }
            }
        }

        return $blocks;
    }
}
