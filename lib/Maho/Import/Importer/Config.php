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
    private const MACRO = '/\{\{(attribute_id|attribute_ids|category_id|cms_block_id|store_id|website_id):([^}]*)\}\}/';

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
            if (!preg_match('#^[a-z0-9_]+/[a-z0-9_]+/[a-z0-9_/]+$#', $path)) {
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
            $row['value'] = $this->at($file, $line, fn() => $this->expand($row['value']));
            $rows[$line] = $row;
        }
        return $rows;
    }

    #[\Override]
    protected function write(CsvFile $file, array $rows, array $options, Reporter $reporter): Result
    {
        $result = new Result();
        $config = Mage::getModel('core/config');
        $storesTouched = false;
        foreach ($rows as $row) {
            $config->saveConfig($row['path'], $row['value'], $row['scope'], $row['scope_id']);
            $result->updated++;
            $storesTouched = $storesTouched || str_starts_with($row['path'], 'web/');
        }
        Mage::app()->getCache()->cleanType('config');
        if ($storesTouched) {
            Mage::app()->reinitStores();
        }
        $this->resolver->reset();
        return $result;
    }

    /**
     * Resolves {{attribute_id:code}}, {{attribute_ids:a,b}}, {{category_id:Root/url-key/...}},
     * {{cms_block_id:identifier}}, {{store_id:code}} and {{website_id:code}}.
     */
    private function expand(string $value): string
    {
        return (string) preg_replace_callback(self::MACRO, function (array $match): string {
            $argument = trim($match[2]);
            return match ($match[1]) {
                'attribute_id' => (string) $this->resolver->attributeId($argument),
                'attribute_ids' => implode(',', array_map(fn(string $code) => (string) $this->resolver->attributeId(trim($code)), explode(',', $argument))),
                'category_id' => (string) ($this->categoryId($argument) ?? throw new \InvalidArgumentException("unknown category '$argument'")),
                'cms_block_id' => (string) ($this->resolver->cmsBlockId($argument) ?? throw new \InvalidArgumentException("unknown cms block '$argument'")),
                'store_id' => (string) $this->resolver->storeId($argument),
                'website_id' => (string) $this->resolver->websiteId($argument),
            };
        }, $value);
    }

    private function categoryId(string $argument): ?int
    {
        [$root, $path] = array_pad(explode('/', $argument, 2), 2, '');
        return $this->resolver->categoryId($root, $path);
    }
}
