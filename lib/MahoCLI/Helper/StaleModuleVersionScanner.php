<?php

/**
 * Finds core_resource rows that record the install history of a module that is gone.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package MahoCLI
 */

declare(strict_types=1);

namespace MahoCLI\Helper;

use Mage;
use Maho;
use Maho\Db\Schema\Status;

class StaleModuleVersionScanner
{
    /** The migration layer owns this row, so no module declares it. */
    private const PLATFORM_RESOURCES = [Status::RESOURCE_CODE];

    /**
     * core_resource rows naming a setup resource that no installed module ships.
     *
     * @return array{stale: list<array{code: string, version: string}>, disabled: list<array{code: string, module: string}>}
     */
    public static function scan(): array
    {
        $known = \Mage_Core_Model_Resource_Setup::getAllSetupResources();
        $disabledResources = self::disabledModuleResources();
        $findings = ['stale' => [], 'disabled' => []];

        $resource = Mage::getSingleton('core/resource');
        $adapter = $resource->getConnection('core_read');
        $select = $adapter->select()
            ->from($resource->getTableName('core/resource'), ['code', 'version'])
            ->order('code');

        foreach ($adapter->fetchAll($select) as $row) {
            $code = (string) $row['code'];
            if (isset($known[$code]) || in_array($code, self::PLATFORM_RESOURCES, true)) {
                continue;
            }
            if (isset($disabledResources[$code])) {
                $findings['disabled'][] = ['code' => $code, 'module' => $disabledResources[$code]];
                continue;
            }
            $findings['stale'][] = ['code' => $code, 'version' => (string) ($row['version'] ?? '')];
        }

        return $findings;
    }

    /**
     * @param list<string> $codes
     */
    public static function purge(array $codes): int
    {
        if ($codes === []) {
            return 0;
        }

        $resource = Mage::getSingleton('core/resource');
        return $resource->getConnection('core_write')->delete(
            $resource->getTableName('core/resource'),
            ['code IN (?)' => $codes],
        );
    }

    /**
     * Setup resource names of declared but disabled modules. getAllSetupResources()
     * skips them, so without this every disabled module reads as removed.
     *
     * @return array<string, string> resource name => module
     */
    private static function disabledModuleResources(): array
    {
        $resources = [];

        foreach (ModuleInspector::declaredModules() as $module => $active) {
            if ($active) {
                continue;
            }

            foreach (['sql', 'data'] as $type) {
                $dir = Mage::getConfig()->getModuleDir($type, $module);
                foreach (Maho::listDirectories($dir) as $subDir) {
                    if (str_ends_with($subDir, '_setup')) {
                        $resources[$subDir] ??= $module;
                    }
                }
            }

            $xml = ModuleInspector::loadModuleXml($module, 'config.xml');
            if ($xml === null || !isset($xml->global->resources)) {
                continue;
            }
            foreach ($xml->global->resources->children() as $name => $node) {
                if (isset($node->setup)) {
                    $resources[(string) $name] ??= $module;
                }
            }
        }

        return $resources;
    }
}
