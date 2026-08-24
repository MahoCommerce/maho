<?php

/**
 * Finds payment methods and shipping carriers that are configured but have no model class.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package MahoCLI
 */

declare(strict_types=1);

namespace MahoCLI\Helper;

use Mage;

class PhantomMethodScanner
{
    /** Both sections share the "group with a model node" shape. */
    public const SECTIONS = [
        'payment' => 'payment method',
        'carriers' => 'shipping carrier',
    ];

    /**
     * Methods and carriers the store still offers with no model behind them. An
     * active one is a live fault: the checkout and the admin order view both ask
     * for a model the configuration promises and no module can build.
     *
     * @return list<array{section: string, label: string, code: string, active: bool, model: string, reason: string, paths: list<string>}>
     */
    public static function scan(): array
    {
        $config = Mage::getConfig();
        $declared = self::declaredGroups();
        $findings = [];

        foreach (self::SECTIONS as $section => $label) {
            $rows = self::configRows($section);

            $codes = array_keys($rows);
            $node = $config->getNode('default/' . $section);
            if ($node instanceof \Maho\Simplexml\Element) {
                foreach ($node->children() as $groupNode) {
                    $codes[] = $groupNode->getName();
                }
            }

            foreach (array_unique($codes) as $code) {
                $code = (string) $code;
                $alias = trim((string) $config->getNode("default/{$section}/{$code}/model"));
                // A group with no model at all is residue only when no declared module
                // names it. A disabled module still declares its method, and an active
                // one can group shared settings under a code that carries no model.
                if ($alias === '' && isset($declared[$section][$code])) {
                    continue;
                }
                $reason = CallbackResolver::findMissingModel($alias);
                if ($reason === null) {
                    continue;
                }

                $findings[] = [
                    'section' => $section,
                    'label' => $label,
                    'code' => $code,
                    'active' => ((string) $config->getNode("default/{$section}/{$code}/active") === '1')
                        || ($rows[$code]['active'] ?? false),
                    'model' => $alias,
                    'reason' => $reason,
                    'paths' => $rows[$code]['paths'] ?? [],
                ];
            }
        }

        return $findings;
    }

    /**
     * Delete the given core_config_data paths. Paths are passed in rather than built
     * as a LIKE prefix, where an underscore in a method code would act as a wildcard.
     *
     * @param list<string> $paths
     */
    public static function purge(array $paths): int
    {
        if ($paths === []) {
            return 0;
        }

        $resource = Mage::getSingleton('core/resource');
        $deleted = $resource->getConnection('core_write')->delete(
            $resource->getTableName('core/config_data'),
            ['path IN (?)' => $paths],
        );

        if ($deleted > 0) {
            Mage::app()->cleanCache([\Mage_Core_Model_Config::CACHE_TAG]);
        }

        return $deleted;
    }

    /**
     * The method and carrier codes every declared module names under <default>, read from
     * its own config.xml: the merged tree drops a disabled module, and it already holds the
     * database rows under test.
     *
     * @return array<string, array<string, true>> section => code => true
     */
    private static function declaredGroups(): array
    {
        $declared = array_fill_keys(array_keys(self::SECTIONS), []);

        foreach (array_keys(ModuleInspector::declaredModules()) as $module) {
            $xml = ModuleInspector::loadModuleXml($module, 'config.xml');
            if ($xml === null || !isset($xml->default)) {
                continue;
            }
            foreach (array_keys(self::SECTIONS) as $section) {
                if (!isset($xml->default->{$section})) {
                    continue;
                }
                foreach ($xml->default->{$section}->children() as $groupNode) {
                    $declared[$section][$groupNode->getName()] = true;
                }
            }
        }

        return $declared;
    }

    /**
     * Every core_config_data path of one section, grouped by the code that follows it.
     * Every scope counts: a row only a website activates is still deletable, and still
     * offers the method to that website.
     *
     * @return array<string, array{paths: list<string>, active: bool}>
     */
    private static function configRows(string $section): array
    {
        $resource = Mage::getSingleton('core/resource');
        $adapter = $resource->getConnection('core_read');

        $select = $adapter->select()
            ->from($resource->getTableName('core/config_data'), ['path', 'value'])
            ->where('path LIKE ?', $section . '/%')
            ->order('path');

        $rows = [];
        foreach ($adapter->fetchAll($select) as $row) {
            $path = (string) $row['path'];
            $segments = explode('/', $path);
            $code = $segments[1] ?? '';
            if ($code === '') {
                continue;
            }

            $rows[$code] ??= ['paths' => [], 'active' => false];
            if (!in_array($path, $rows[$code]['paths'], true)) {
                $rows[$code]['paths'][] = $path;
            }
            if (($segments[2] ?? '') === 'active' && (string) ($row['value'] ?? '') === '1') {
                $rows[$code]['active'] = true;
            }
        }

        return $rows;
    }
}
