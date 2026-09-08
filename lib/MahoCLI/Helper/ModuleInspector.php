<?php

/**
 * Reads what the installed modules declare, for the checks that compare code to database state.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package MahoCLI
 */

declare(strict_types=1);

namespace MahoCLI\Helper;

use Mage;
use Maho;

class ModuleInspector
{
    /**
     * Every module declared in app/etc/modules, as name => whether it is active.
     * A module missing from this list is gone; a disabled one is not residue.
     *
     * @return array<string, bool>
     */
    public static function declaredModules(): array
    {
        $modules = [];
        $node = Mage::getConfig()->getNode('modules');
        if (!$node instanceof \Maho\Simplexml\Element) {
            return $modules;
        }

        foreach ($node->children() as $name => $moduleNode) {
            if (!$moduleNode instanceof \Mage_Core_Model_Config_Element) {
                continue;
            }
            $modules[(string) $name] = (bool) $moduleNode->is('active');
        }

        return $modules;
    }

    /**
     * Parse one etc/*.xml of a module without leaking libxml warnings, null on failure.
     * The merged config tree cannot answer for a disabled module, and it already holds
     * the database rows the residue checks test, so the file is read directly.
     */
    public static function loadModuleXml(string $module, string $fileName): ?\SimpleXMLElement
    {
        $file = Maho::findFile(Mage::getConfig()->getModuleDir('etc', $module) . '/' . $fileName);
        if ($file === false) {
            return null;
        }

        $previousLibxmlErrors = libxml_use_internal_errors(true);
        try {
            $xml = simplexml_load_file($file);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousLibxmlErrors);
        }

        return $xml === false ? null : $xml;
    }

    /**
     * Table names a <global><models> node declares as resource entities, keyed by name.
     * An entity with no <table> node takes the entity name.
     *
     * @return array<string, true>
     */
    public static function entityTables(?\SimpleXMLElement $models, string $prefix): array
    {
        $tables = [];
        if ($models === null) {
            return $tables;
        }

        foreach ($models->children() as $node) {
            if (!isset($node->entities)) {
                continue;
            }
            foreach ($node->entities->children() as $entity => $entityNode) {
                $table = trim((string) $entityNode->table);
                $tables[$prefix . ($table === '' ? (string) $entity : $table)] = true;
            }
        }

        return $tables;
    }
}
