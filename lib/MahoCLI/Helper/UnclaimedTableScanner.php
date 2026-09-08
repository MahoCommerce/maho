<?php

/**
 * Finds live tables that no installed module declares.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package MahoCLI
 */

declare(strict_types=1);

namespace MahoCLI\Helper;

use Mage;
use Maho;
use Maho\Db\Schema\Collector;

class UnclaimedTableScanner
{
    /** The SQLite adapter creates this table at runtime, so no schema declares it. */
    private const PLATFORM_TABLES = ['core_advisory_lock'];

    /**
     * Tables that neither a sql/schema.php nor a resource entity claims. Advisory only,
     * and nothing is ever dropped: a module can create a table in an install script and
     * name it nowhere else. "./maho migrate" is forward-only, so nothing else reports these.
     *
     * @return array{unclaimed: list<string>, disabled: list<array{table: string, module: string}>}
     */
    public static function scan(): array
    {
        $adapter = Mage::getSingleton('core/resource')->getConnection('core_read');
        $prefix = Collector::tablePrefix();

        $owned = self::declaredEntityTables($prefix);
        foreach (self::PLATFORM_TABLES as $table) {
            // The SQLite adapter hard-codes the unprefixed name, so exempt both spellings.
            $owned[$table] = true;
            $owned[$prefix . $table] = true;
        }
        [$schema] = Collector::collect();
        foreach ($schema->getTables() as $table) {
            $owned[$table->getObjectName()->getUnqualifiedName()->getValue()] = true;
        }

        $unclaimed = [];
        foreach ($adapter->listTables() as $table) {
            $table = (string) $table;
            if (!isset($owned[$table])) {
                $unclaimed[] = $table;
            }
        }
        sort($unclaimed);

        return self::splitTablesOfDisabledModules($unclaimed, $prefix);
    }

    /**
     * Tables named by a resource entity of any declared module. The merged tree covers
     * every active module at once; disabled modules are read from their own XML.
     *
     * @return array<string, true>
     */
    private static function declaredEntityTables(string $prefix): array
    {
        $models = Mage::getConfig()->getNode('global/models');
        $tables = ModuleInspector::entityTables(
            $models instanceof \Maho\Simplexml\Element ? $models : null,
            $prefix,
        );

        foreach (ModuleInspector::declaredModules() as $module => $active) {
            if ($active) {
                continue;
            }
            $xml = ModuleInspector::loadModuleXml($module, 'config.xml');
            if ($xml !== null && isset($xml->global->models)) {
                $tables += ModuleInspector::entityTables($xml->global->models, $prefix);
            }
        }

        return $tables;
    }

    /**
     * Move to the disabled bucket every table a disabled module's sql/schema.php creates.
     * Collector only runs the schema of active modules, so the source text is read
     * instead of executed.
     *
     * @param list<string> $unclaimed
     * @return array{unclaimed: list<string>, disabled: list<array{table: string, module: string}>}
     */
    private static function splitTablesOfDisabledModules(array $unclaimed, string $prefix): array
    {
        if ($unclaimed === []) {
            return ['unclaimed' => [], 'disabled' => []];
        }

        $disabled = [];
        foreach (ModuleInspector::declaredModules() as $module => $active) {
            if ($active || $unclaimed === []) {
                continue;
            }

            $file = Maho::findFile(Mage::getConfig()->getModuleDir('sql', $module) . '/schema.php');
            if ($file === false) {
                continue;
            }
            $source = (string) file_get_contents($file);

            foreach ($unclaimed as $index => $table) {
                $bare = str_starts_with($table, $prefix) ? substr($table, strlen($prefix)) : $table;
                if (preg_match('/createTable\(\s*[\'"]' . preg_quote($bare, '/') . '[\'"]/', $source) === 1) {
                    $disabled[] = ['table' => $table, 'module' => $module];
                    unset($unclaimed[$index]);
                }
            }
        }

        return ['unclaimed' => array_values($unclaimed), 'disabled' => $disabled];
    }
}
