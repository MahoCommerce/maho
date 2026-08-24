<?php

/**
 * Finds store configuration sections that no installed module declares.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package MahoCLI
 */

declare(strict_types=1);

namespace MahoCLI\Helper;

use Mage;

class UnclaimedConfigScanner
{
    /**
     * Config scopes of a config.xml. Under <default> the children are sections; under
     * <stores> and <websites> they are store and website codes, so the sections sit one
     * level deeper. <frontend> and <adminhtml> are areas, not scopes, and their children
     * (layout, translate, events, routers) are not sections.
     */
    private const SCOPE_NODES = ['default'];
    private const CODE_KEYED_SCOPE_NODES = ['stores', 'websites'];

    /**
     * core_config_data sections with no owner in code. Advisory only: an operator can
     * hand-write a section that no system.xml declares, so a finding is a question,
     * not a verdict.
     *
     * @return array{unclaimed: list<array{section: string, rows: int}>, disabled: list<array{section: string, module: string, rows: int}>}
     */
    public static function scan(): array
    {
        $owners = self::sectionOwners();
        $findings = ['unclaimed' => [], 'disabled' => []];

        foreach (self::sectionCounts() as $section => $count) {
            $owner = $owners[$section] ?? null;
            if ($owner !== null && $owner['active']) {
                continue;
            }
            if ($owner !== null) {
                $findings['disabled'][] = ['section' => $section, 'module' => $owner['module'], 'rows' => $count];
                continue;
            }
            $findings['unclaimed'][] = ['section' => $section, 'rows' => $count];
        }

        return $findings;
    }

    /**
     * Row count of every core_config_data section, keyed by the first path segment.
     *
     * @return array<string, int>
     */
    private static function sectionCounts(): array
    {
        $resource = Mage::getSingleton('core/resource');
        $adapter = $resource->getConnection('core_read');

        $select = $adapter->select()
            ->from($resource->getTableName('core/config_data'), ['path']);

        $counts = [];
        foreach ($adapter->fetchCol($select) as $path) {
            $section = explode('/', (string) $path)[0];
            if ($section === '') {
                continue;
            }
            $counts[$section] = ($counts[$section] ?? 0) + 1;
        }
        ksort($counts);

        return $counts;
    }

    /**
     * The module that declares each section, read from the module XML rather than the
     * merged tree: the merged tree already holds the rows under test.
     *
     * @return array<string, array{module: string, active: bool}>
     */
    private static function sectionOwners(): array
    {
        $owners = [];

        foreach (ModuleInspector::declaredModules() as $module => $active) {
            foreach (['config.xml', 'system.xml'] as $fileName) {
                $xml = ModuleInspector::loadModuleXml($module, $fileName);
                if ($xml === null) {
                    continue;
                }

                foreach (self::sectionsOf($xml, $fileName) as $section) {
                    // An active owner wins: a section that both an active and a disabled
                    // module declares is still owned.
                    if (!isset($owners[$section]) || (!$owners[$section]['active'] && $active)) {
                        $owners[$section] = ['module' => $module, 'active' => $active];
                    }
                }
            }
        }

        return $owners;
    }

    /**
     * @return list<string>
     */
    private static function sectionsOf(\SimpleXMLElement $xml, string $fileName): array
    {
        $sections = [];

        if ($fileName === 'system.xml') {
            if (isset($xml->sections)) {
                foreach ($xml->sections->children() as $sectionNode) {
                    $sections[] = $sectionNode->getName();
                }
            }
            return $sections;
        }

        foreach (self::SCOPE_NODES as $scope) {
            if (!isset($xml->{$scope})) {
                continue;
            }
            foreach ($xml->{$scope}->children() as $sectionNode) {
                $sections[] = $sectionNode->getName();
            }
        }

        foreach (self::CODE_KEYED_SCOPE_NODES as $scope) {
            if (!isset($xml->{$scope})) {
                continue;
            }
            foreach ($xml->{$scope}->children() as $codeNode) {
                foreach ($codeNode->children() as $sectionNode) {
                    $sections[] = $sectionNode->getName();
                }
            }
        }

        return $sections;
    }
}
