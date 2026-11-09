<?php

/**
 * core_config_data rows scoped by website or store code, with lookups resolved at import time. Each row goes
 * through the config service, so a path that no field declares is refused unless the undeclared option is set.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho
 */

declare(strict_types=1);

namespace Maho\Import\Importer;

use Mage;
use Maho\Import\AbstractImporter;
use Maho\Import\CsvFile;
use Maho\Import\Reporter;
use Maho\Import\Result;

class Config extends AbstractImporter
{
    #[\Override]
    protected function requiredColumns(): array
    {
        return ['path', 'value'];
    }

    #[\Override]
    protected function prepare(CsvFile $file, array $options): array
    {
        $rows = [];
        foreach ($file as $line => $row) {
            $path = $this->requireValue($file, $line, $row, 'path');
            if (!preg_match('#^[A-Za-z0-9_]+/[A-Za-z0-9_]+/[A-Za-z0-9_/]+$#', $path)) {
                $this->fail($file, $line, "path '$path' is not a section/group/field path");
            }
            $scope = ($row['scope'] ?? '') !== '' ? $row['scope'] : 'default';
            if (!in_array($scope, ['default', 'websites', 'stores'], true)) {
                $this->fail($file, $line, "scope '$scope' is not one of default, websites, stores");
            }
            $scopeCode = $row['scope_code'] ?? '';
            if ($scope === 'default' && $scopeCode !== '') {
                $this->fail($file, $line, 'scope_code must be empty for the default scope');
            }
            if ($scope !== 'default' && $scopeCode === '') {
                $this->fail($file, $line, "scope_code is required for the $scope scope");
            }
            $row['scope'] = $scope;
            $row['scope_id'] = $this->at($file, $line, fn() => $this->resolver->scopeId($scope, $scopeCode));
            $this->at($file, $line, fn() => $this->resolver->expand($row['value']));
            try {
                self::service()->assertWritable($path, $scope, $row['scope_id'], !empty($options['undeclared']));
            } catch (\Mage_Core_Exception_NoSuchEntity) {
                $this->fail($file, $line, "path '$path' is not a field of System > Configuration, use the undeclared option to import it");
            } catch (\Mage_Core_Exception $e) {
                $this->fail($file, $line, $e->getMessage());
            }
            $rows[$line] = $row;
        }
        return $rows;
    }

    /**
     * The web/ rows are saved first, and the config and the stores reload before the other rows. So a
     * {{store_url:code}} macro in a later row sees the base URLs and the store code flag that this same file sets,
     * and a store that this process created.
     */
    #[\Override]
    protected function write(CsvFile $file, array $rows, array $options, Reporter $reporter): Result
    {
        $result = new Result();
        $web = array_filter($rows, static fn(array $row): bool => str_starts_with($row['path'], 'web/'));
        $rest = array_diff_key($rows, $web);
        if ($web === []) {
            // The service reloads after a web/ batch only, and a store created after the config loaded has no config
            Mage::getConfig()->reinit();
            Mage::app()->reinitStores();
        }
        foreach ([$web, $rest] as $batch) {
            $line = 0;
            // The generator expands each value when the service reaches it, after the web/ batch reloaded the stores
            $values = (function () use ($batch, &$line): \Generator {
                foreach ($batch as $line => $row) {
                    yield [$row['path'], $this->resolver->expand($row['value']), $row['scope'], $row['scope_id']];
                }
            })();
            try {
                self::service()->saveAll($values, !empty($options['undeclared']));
            } catch (\Mage_Core_Exception $e) {
                $this->fail($file, $line, $e->getMessage());
            }
            $result->updated += count($batch);
        }
        Mage::app()->getCache()->cleanType('config');
        $this->resolver->reset();
        return $result;
    }

    private static function service(): \Mage_Core_Service_Config
    {
        return Mage::getService('core/config');
    }
}
