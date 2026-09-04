<?php

/**
 * Product attributes from attributes.csv, their options and swatches from an optional options CSV.
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

class Attributes extends AbstractImporter
{
    public const OPTION_OPTIONS_CSV = 'options';

    private const SWATCH_CONFIG_PATH = 'configswatches/general/swatch_attributes';

    /** @var array<string, array{type: string, backend?: string, source?: string}> */
    private const INPUTS = [
        'text' => ['type' => 'varchar'],
        'textarea' => ['type' => 'text'],
        'select' => ['type' => 'int', 'source' => 'eav/entity_attribute_source_table'],
        'multiselect' => ['type' => 'varchar', 'backend' => 'eav/entity_attribute_backend_array', 'source' => 'eav/entity_attribute_source_table'],
        'boolean' => ['type' => 'int', 'source' => 'eav/entity_attribute_source_boolean'],
        'price' => ['type' => 'decimal', 'backend' => 'catalog/product_attribute_backend_price'],
        'date' => ['type' => 'datetime', 'backend' => 'eav/entity_attribute_backend_datetime'],
        'weight' => ['type' => 'decimal'],
    ];

    private const FLAGS = [
        'required', 'filterable', 'filterable_in_search', 'searchable', 'comparable', 'visible_on_front',
        'used_in_product_listing', 'used_for_sort_by', 'is_configurable', 'is_html_allowed_on_front',
        'visible_in_advanced_search',
    ];

    private const SCOPES = [
        'global' => \Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_GLOBAL,
        'website' => \Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_WEBSITE,
        'store' => \Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_STORE,
    ];

    #[\Override]
    protected function requiredColumns(): array
    {
        return ['code'];
    }

    #[\Override]
    protected function prepare(CsvFile $file, array $options): array
    {
        $rows = [];
        foreach ($file as $line => $row) {
            $code = $this->requireValue($file, $line, $row, 'code');
            if (!preg_match('/^[a-z][a-z0-9_]{0,29}$/', $code)) {
                $this->fail($file, $line, "code '$code' must be lowercase letters, digits and underscores");
            }
            $input = ($row['input'] ?? '') !== '' ? $row['input'] : 'text';
            if (!isset(self::INPUTS[$input])) {
                $this->fail($file, $line, "input '$input' is not one of " . implode(', ', array_keys(self::INPUTS)));
            }
            $scope = ($row['scope'] ?? '') !== '' ? $row['scope'] : 'global';
            if (!isset(self::SCOPES[$scope])) {
                $this->fail($file, $line, "scope '$scope' is not one of global, website, store");
            }
            $row['input'] = $input;
            $row['scope'] = $scope;
            foreach (self::FLAGS as $flag) {
                if (($row[$flag] ?? '') !== '') {
                    $row[$flag] = $this->at($file, $line, fn() => CsvFile::bool($row[$flag], false));
                } else {
                    unset($row[$flag]);
                }
            }
            $row['swatch_attribute'] = $this->at($file, $line, fn() => CsvFile::bool($row['swatch_attribute'] ?? '', false));
            $row['sets'] = CsvFile::list($row['sets'] ?? '');
            foreach ($row['sets'] as $set) {
                if ($set !== '*') {
                    $this->at($file, $line, fn() => $this->resolver->attributeSetId($set));
                }
            }
            $rows[$line] = $row;
        }
        if (isset($options[self::OPTION_OPTIONS_CSV])) {
            $this->prepareOptions(CsvFile::open($options[self::OPTION_OPTIONS_CSV], ['attribute_code', 'label']), array_column($rows, 'code'));
        }
        return $rows;
    }

    #[\Override]
    protected function write(CsvFile $file, array $rows, array $options, Reporter $reporter): Result
    {
        $result = new Result();
        $setup = new \Mage_Catalog_Model_Resource_Setup('catalog_setup');
        $entityTypeId = (int) Mage::getSingleton('eav/config')->getEntityType('catalog_product')->getId();
        $swatchCodes = [];
        foreach ($rows as $row) {
            $existing = (bool) $setup->getAttribute($entityTypeId, $row['code'], 'attribute_id');
            $setup->addAttribute($entityTypeId, $row['code'], $this->attributeData($row));
            $existing ? $result->updated++ : $result->created++;
            $this->assignToSets($setup, $entityTypeId, $row);
            if ($row['swatch_attribute']) {
                $swatchCodes[] = $row['code'];
            }
        }
        $this->clearEavCache();
        if (isset($options[self::OPTION_OPTIONS_CSV])) {
            $result->merge($this->writeOptions(CsvFile::open($options[self::OPTION_OPTIONS_CSV], ['attribute_code', 'label']), $reporter));
        }
        if ($swatchCodes !== []) {
            $this->registerSwatchAttributes($swatchCodes);
        }
        $this->resolver->reset();
        return $result;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function attributeData(array $row): array
    {
        $input = self::INPUTS[$row['input']];
        $data = [
            'type' => ($row['type'] ?? '') !== '' ? $row['type'] : $input['type'],
            'input' => $row['input'],
            'backend' => $input['backend'] ?? null,
            'source' => $input['source'] ?? null,
            'label' => ($row['label'] ?? '') !== '' ? $row['label'] : ucwords(str_replace('_', ' ', $row['code'])),
            'global' => self::SCOPES[$row['scope']],
            'user_defined' => 1,
            'required' => 0,
            'is_configurable' => 0,
            'default' => ($row['default_value'] ?? '') !== '' ? $row['default_value'] : null,
            'apply_to' => implode(',', CsvFile::list($row['apply_to'] ?? '')),
        ];
        foreach (self::FLAGS as $flag) {
            if (array_key_exists($flag, $row)) {
                $data[$flag] = $row[$flag] ? 1 : 0;
            }
        }
        return $data;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function assignToSets(\Mage_Catalog_Model_Resource_Setup $setup, int $entityTypeId, array $row): void
    {
        if ($row['sets'] === []) {
            return;
        }
        $group = ($row['group'] ?? '') !== '' ? $row['group'] : 'General';
        $sortOrder = ($row['sort_order'] ?? '') !== '' ? (int) $row['sort_order'] : null;
        $sets = $row['sets'];
        if (in_array('*', $sets, true)) {
            $sets = array_map(
                static fn($set) => $set->getAttributeSetName(),
                iterator_to_array(Mage::getResourceModel('eav/entity_attribute_set_collection')->setEntityTypeFilter($entityTypeId)),
            );
        }
        foreach ($sets as $setName) {
            $setId = $this->resolver->attributeSetId($setName);
            $setup->addAttributeGroup($entityTypeId, $setId, $group);
            $setup->addAttributeToGroup($entityTypeId, $setId, $group, $row['code'], $sortOrder);
        }
    }

    /**
     * @param list<string> $codesInFile
     */
    private function prepareOptions(CsvFile $file, array $codesInFile): void
    {
        foreach ($file as $line => $row) {
            $code = $this->requireValue($file, $line, $row, 'attribute_code');
            $this->requireValue($file, $line, $row, 'label');
            if (!in_array($code, $codesInFile, true)) {
                $this->at($file, $line, fn() => $this->resolver->attributeId($code));
            }
            if (($row['store_code'] ?? '') !== '') {
                $this->at($file, $line, fn() => $this->resolver->storeId($row['store_code']));
                $this->requireValue($file, $line, $row, 'label_admin');
            }
            $swatch = $row['swatch'] ?? '';
            if ($swatch !== '' && !preg_match('/^#[0-9a-fA-F]{6}$/', $swatch)) {
                $this->fail($file, $line, "swatch '$swatch' must be a hex color like #ff0000");
            }
        }
    }

    private function writeOptions(CsvFile $file, Reporter $reporter): Result
    {
        $result = new Result();
        $byAttribute = [];
        foreach ($file as $row) {
            $byAttribute[$row['attribute_code']][] = $row;
        }
        foreach ($byAttribute as $code => $rows) {
            $attribute = Mage::getModel('catalog/resource_eav_attribute')->loadByCode('catalog_product', $code);
            if (!in_array($attribute->getFrontendInput(), ['select', 'multiselect'], true)) {
                $result->notices[] = "$code is not a select attribute, its options were skipped";
                continue;
            }
            $existing = [];
            foreach (Mage::getResourceModel('eav/entity_attribute_option_collection')->setAttributeFilter((int) $attribute->getId())->setStoreFilter(0) as $option) {
                $existing[$option->getValue()] = (int) $option->getId();
            }
            $payload = ['value' => [], 'order' => [], 'swatch' => []];
            $next = 0;
            $keys = [];
            foreach ($rows as $row) {
                $adminLabel = ($row['store_code'] ?? '') !== '' ? $row['label_admin'] : $row['label'];
                $key = $keys[$adminLabel] ?? ($existing[$adminLabel] ?? null);
                if ($key === null) {
                    $key = 'option_' . $next++;
                    $result->created++;
                } elseif (!isset($keys[$adminLabel])) {
                    $result->updated++;
                }
                $keys[$adminLabel] = $key;
                $payload['value'][$key][0] ??= $adminLabel;
                if (($row['store_code'] ?? '') !== '') {
                    $payload['value'][$key][$this->resolver->storeId($row['store_code'])] = $row['label'];
                }
                if (($row['sort_order'] ?? '') !== '') {
                    $payload['order'][$key] = (int) $row['sort_order'];
                }
                if (($row['swatch'] ?? '') !== '') {
                    $payload['swatch'][$key] = strtolower($row['swatch']);
                }
            }
            $attribute->setOption($payload)->save();
            $reporter->info("$code: " . count($rows) . ' option rows');
        }
        $this->clearEavCache();
        return $result;
    }

    /**
     * @param list<string> $codes
     */
    private function registerSwatchAttributes(array $codes): void
    {
        $ids = array_filter(array_map(intval(...), explode(',', (string) Mage::getStoreConfig(self::SWATCH_CONFIG_PATH))));
        foreach ($codes as $code) {
            $ids[] = $this->resolver->attributeId($code);
        }
        $ids = array_values(array_unique($ids));
        sort($ids);
        Mage::getModel('core/config')->saveConfig(self::SWATCH_CONFIG_PATH, implode(',', $ids));
        Mage::app()->getCache()->cleanType('config');
    }

    private function clearEavCache(): void
    {
        Mage::getSingleton('eav/config')->clear();
        Mage::app()->getCache()->cleanType('eav');
        Mage::app()->getCache()->cleanType('config');
    }
}
