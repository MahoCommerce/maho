<?php

/**
 * core_config_data rows scoped by website or store code, with lookups resolved at import time.
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
            $rows[$line] = $row;
        }
        return $rows;
    }

    /**
     * The web/ rows are saved first and the stores reloaded, so a {{store_url:code}} macro in a later row
     * sees the base URLs and the store code flag this same file sets.
     */
    #[\Override]
    protected function write(CsvFile $file, array $rows, array $options, Reporter $reporter): Result
    {
        $result = new Result();
        $config = Mage::getModel('core/config');
        $web = array_filter($rows, static fn(array $row): bool => str_starts_with($row['path'], 'web/'));
        $rest = array_diff_key($rows, $web);
        foreach ([$web, $rest] as $batch) {
            foreach ($batch as $row) {
                $config->saveConfig($row['path'], $this->resolver->expand($row['value']), $row['scope'], $row['scope_id']);
                $result->updated++;
            }
            if ($batch === $web && $web !== []) {
                Mage::app()->getCache()->cleanType('config');
                Mage::getConfig()->reinit();
                Mage::app()->reinitStores();
            }
        }
        Mage::app()->getCache()->cleanType('config');
        $this->resolver->reset();
        return $result;
    }

}
