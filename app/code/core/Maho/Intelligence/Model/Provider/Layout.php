<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Intelligence
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
