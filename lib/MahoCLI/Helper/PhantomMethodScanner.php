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

    /** Scope nodes keyed by store or website code, so a section sits one level deeper. */
    private const CODE_KEYED_SCOPE_NODES = ['stores', 'websites'];

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
            $scopePaths = self::scopePaths($section);

            $codes = array_keys($rows);
            foreach ($scopePaths as $scopePath) {
                $node = $config->getNode($scopePath);
                if ($node instanceof \Maho\Simplexml\Element) {
                    foreach ($node->children() as $groupNode) {
                        $codes[] = $groupNode->getName();
                    }
                }
            }

            foreach (array_unique($codes) as $code) {
                $code = (string) $code;
                $aliases = self::scopeValues($scopePaths, $code, 'model');
                // A group with no model in any scope is residue only when no declared
                // module names it. A disabled module still declares its method, and an
                // active one can group shared settings under a code that carries no model.
                if ($aliases === [] && isset($declared[$section][$code])) {
                    continue;
                }

                $model = '';
                $reason = null;
                foreach ($aliases === [] ? [''] : $aliases as $alias) {
                    $reason = CallbackResolver::findMissingModel($alias);
                    if ($reason !== null) {
                        $model = $alias;
                        break;
                    }
                }
                if ($reason === null) {
                    continue;
                }

                $findings[] = [
                    'section' => $section,
                    'label' => $label,
                    'code' => $code,
                    'active' => in_array('1', self::scopeValues($scopePaths, $code, 'active'), true)
                        || ($rows[$code]['active'] ?? false),
                    'model' => $model,
                    'reason' => $reason,
                    'paths' => $rows[$code]['paths'] ?? [],
                ];
            }
        }

        return $findings;
    }

    /**
     * Config-tree path of one section, one per scope. loadToXml() extends the default
     * scope into every website node and each website into its stores, so a store node
     * carries the whole tree. A group declared for one store alone lives nowhere else.
     *
     * @return list<string>
     */
    private static function scopePaths(string $section): array
    {
        $config = Mage::getConfig();
        $paths = ['default/' . $section];

        foreach (self::CODE_KEYED_SCOPE_NODES as $scope) {
            $node = $config->getNode($scope);
            if (!$node instanceof \Maho\Simplexml\Element) {
                continue;
            }
            foreach ($node->children() as $code => $codeNode) {
                $paths[] = sprintf('%s/%s/%s', $scope, $code, $section);
            }
        }

        return $paths;
    }

    /**
     * The distinct values one field of a group carries across the scopes, in scope order
     * and without the empty ones.
     *
     * @param list<string> $scopePaths
     * @return list<string>
     */
    private static function scopeValues(array $scopePaths, string $code, string $field): array
    {
        $config = Mage::getConfig();
        $values = [];

        foreach ($scopePaths as $scopePath) {
            $value = trim((string) $config->getNode("{$scopePath}/{$code}/{$field}"));
            if ($value !== '' && !in_array($value, $values, true)) {
                $values[] = $value;
            }
        }

        return $values;
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
     * The method and carrier codes every declared module names, read from its own XML: the
     * merged tree drops a disabled module, and it already holds the database rows under
     * test. A group in system.xml counts as a declaration too. A module can give a vendor
     * its own settings group there and put the model on the methods that use it, so the
     * group owns rows without ever naming a model.
     *
     * @return array<string, array<string, true>> section => code => true
     */
    private static function declaredGroups(): array
    {
        $declared = array_fill_keys(array_keys(self::SECTIONS), []);

        foreach (array_keys(ModuleInspector::declaredModules()) as $module) {
            foreach (['config.xml', 'system.xml'] as $fileName) {
                $xml = ModuleInspector::loadModuleXml($module, $fileName);
                if ($xml === null) {
                    continue;
                }
                foreach (array_keys(self::SECTIONS) as $section) {
                    foreach (self::groupsOf($xml, $fileName, $section) as $code) {
                        $declared[$section][$code] = true;
                    }
                }
            }
        }

        return $declared;
    }

    /**
     * @return list<string>
     */
    private static function groupsOf(\SimpleXMLElement $xml, string $fileName, string $section): array
    {
        $nodes = [];
        if ($fileName === 'system.xml') {
            $nodes[] = $xml->sections->{$section}->groups ?? null;
        } else {
            $nodes[] = $xml->default->{$section} ?? null;
            foreach (self::CODE_KEYED_SCOPE_NODES as $scope) {
                if (!isset($xml->{$scope})) {
                    continue;
                }
                foreach ($xml->{$scope}->children() as $codeNode) {
                    $nodes[] = $codeNode->{$section} ?? null;
                }
            }
        }

        $codes = [];
        foreach ($nodes as $node) {
            if ($node === null || $node->count() === 0) {
                continue;
            }
            foreach ($node->children() as $groupNode) {
                $codes[] = $groupNode->getName();
            }
        }

        return $codes;
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
