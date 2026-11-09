<?php

/**
 * Product attributes from attributes.csv, their options and swatches from an optional options CSV.
 *
 * A row with a store_code carries the label of that store view only: its code names the
 * attribute, its label is the translated label, and every other cell stays empty.
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

    private const ENTITY_TYPE = 'catalog_product';

    private const FLAGS = [
        'required', 'filterable', 'filterable_in_search', 'searchable', 'comparable', 'visible_on_front',
        'used_in_product_listing', 'used_for_sort_by', 'is_configurable', 'is_html_allowed_on_front',
        'visible_in_advanced_search',
    ];

    /** The eav_attribute / catalog_eav_attribute column behind each flag whose name differs. */
    private const FLAG_COLUMNS = [
        'required' => 'is_required',
        'filterable' => 'is_filterable',
        'filterable_in_search' => 'is_filterable_in_search',
        'searchable' => 'is_searchable',
        'comparable' => 'is_comparable',
        'visible_on_front' => 'is_visible_on_front',
        'visible_in_advanced_search' => 'is_visible_in_advanced_search',
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
        $inputs = array_keys(self::service()->newAttribute(self::ENTITY_TYPE)->getInputTypes());
        foreach ($file as $line => $row) {
            $code = $this->requireValue($file, $line, $row, 'code');
            if (!preg_match(\Mage_Eav_Model_Entity_Attribute::ATTRIBUTE_CODE_PATTERN, $code)) {
                $this->fail($file, $line, "code '$code' must be lowercase letters, digits and underscores");
            }
            if (($row['store_code'] ?? '') !== '') {
                $this->at($file, $line, fn() => $this->resolver->storeId($row['store_code']));
                $this->requireValue($file, $line, $row, 'label');
                $rows[$line] = ['code' => $code, 'store_code' => $row['store_code'], 'label' => $row['label']];
                continue;
            }
            $input = ($row['input'] ?? '') !== '' ? $row['input'] : 'text';
            if (!in_array($input, $inputs, true)) {
                $this->fail($file, $line, "input '$input' is not one of " . implode(', ', $inputs));
            }
            $scope = ($row['scope'] ?? '') !== '' ? $row['scope'] : 'global';
            if (!isset(self::SCOPES[$scope])) {
                $this->fail($file, $line, "scope '$scope' is not one of global, website, store");
            }
            $row['scope_set'] = ($row['scope'] ?? '') !== '';
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
        // A store label may name an attribute the file defines further down, or one already in the store
        $defined = array_column(array_filter($rows, static fn($row) => ($row['store_code'] ?? '') === ''), 'code');
        foreach ($rows as $line => $row) {
            if (($row['store_code'] ?? '') !== '' && !in_array($row['code'], $defined, true)) {
                $this->at($file, $line, fn() => $this->resolver->attributeId($row['code']));
            }
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
        $service = self::service();
        $setup = new \Mage_Catalog_Model_Resource_Setup('catalog_setup');
        $entityTypeId = (int) Mage::getSingleton('eav/config')->getEntityType(self::ENTITY_TYPE)->getId();
        $swatchCodes = [];
        $storeLabels = [];
        foreach ($rows as $line => $row) {
            if (($row['store_code'] ?? '') !== '') {
                $storeLabels[$row['code']][$this->resolver->storeId($row['store_code'])] = $row['label'];
                continue;
            }
            try {
                // An existing attribute, a core one included, only takes the cells that the file fills in
                $attribute = $service->getByCode($row['code'], self::ENTITY_TYPE);
                $update = $this->attributeUpdate($row);
                if ($update !== []) {
                    $this->saveAt($file, $line, fn() => $service->save($attribute->addData($update)));
                }
                $result->updated++;
            } catch (\Mage_Core_Exception_NoSuchEntity) {
                $attribute = $service->newAttribute(self::ENTITY_TYPE)->addData($this->attributeData($row));
                $this->saveAt($file, $line, fn() => $service->save($attribute));
                $result->created++;
            }
            $this->assignToSets($setup, $entityTypeId, $row);
            if ($row['swatch_attribute']) {
                $swatchCodes[] = $row['code'];
            }
        }
        $this->clearEavCache();
        foreach ($storeLabels as $code => $labels) {
            $this->writeStoreLabels($code, $labels);
            $result->updated++;
        }
        if (isset($options[self::OPTION_OPTIONS_CSV])) {
            $result->merge($this->writeOptions(CsvFile::open($options[self::OPTION_OPTIONS_CSV], ['attribute_code', 'label']), $reporter));
        }
        if ($swatchCodes !== []) {
            $this->registerSwatchAttributes($swatchCodes);
        }
        $this->resolver->reset();
        return $result;
    }

    private static function service(): \Mage_Eav_Service_Attribute
    {
        /** @var \Mage_Eav_Service_Attribute */
        return Mage::getService('eav/attribute');
    }

    /**
     * Run $save, and pin the error of the attribute service to the row.
     */
    private function saveAt(CsvFile $file, int $line, callable $save): void
    {
        try {
            $save();
        } catch (\Mage_Core_Exception $e) {
            $this->fail($file, $line, str_replace(PHP_EOL, '; ', $e->getMessage()));
        }
    }

    /**
     * The columns of a new attribute. The attribute service gives it the models of its input type.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function attributeData(array $row): array
    {
        $data = [
            'attribute_code' => $row['code'],
            'frontend_input' => $row['input'],
            'frontend_label' => ($row['label'] ?? '') !== '' ? $row['label'] : ucwords(str_replace('_', ' ', $row['code'])),
            'is_global' => self::SCOPES[$row['scope']],
            'is_required' => 0,
            'is_configurable' => 0,
            'default_value' => ($row['default_value'] ?? '') !== '' ? $row['default_value'] : null,
            'apply_to' => CsvFile::list($row['apply_to'] ?? ''),
        ];
        if (($row['type'] ?? '') !== '') {
            $data['backend_type'] = $row['type'];
        }
        return array_replace($data, $this->flagColumns($row));
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, int>
     */
    private function flagColumns(array $row): array
    {
        $data = [];
        foreach (self::FLAGS as $flag) {
            if (array_key_exists($flag, $row)) {
                $data[self::FLAG_COLUMNS[$flag] ?? $flag] = $row[$flag] ? 1 : 0;
            }
        }
        return $data;
    }

    /**
     * The columns of an existing attribute the file may change. The input and its backend
     * type stay: changing them would orphan the stored values.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function attributeUpdate(array $row): array
    {
        $data = [];
        if (($row['label'] ?? '') !== '') {
            $data['frontend_label'] = $row['label'];
        }
        if ($row['scope_set']) {
            $data['is_global'] = self::SCOPES[$row['scope']];
        }
        if (($row['default_value'] ?? '') !== '') {
            $data['default_value'] = $row['default_value'];
        }
        if (($row['apply_to'] ?? '') !== '') {
            $data['apply_to'] = CsvFile::list($row['apply_to']);
        }
        return array_replace($data, $this->flagColumns($row));
    }

    /**
     * The save deletes every store label of the attribute and writes the given ones back,
     * so a store view the file leaves alone keeps its label.
     *
     * @param array<int, string> $labels store id to label
     */
    private function writeStoreLabels(string $code, array $labels): void
    {
        $attribute = self::service()->getByCode($code, self::ENTITY_TYPE);
        self::service()->save($attribute->setStoreLabels($labels + $attribute->getStoreLabels()));
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
        $service = self::service();
        $result = new Result();
        $byAttribute = [];
        foreach ($file as $line => $row) {
            $byAttribute[$row['attribute_code']][$line] = $row;
        }
        foreach ($byAttribute as $code => $rows) {
            $attribute = $service->getByCode((string) $code, self::ENTITY_TYPE);
            if (!$service->hasOwnOptions($attribute)) {
                $result->notices[] = "$code is not a select attribute, its options were skipped";
                continue;
            }
            $existing = [];
            foreach ($service->getOptions($attribute) as $optionId => $option) {
                $existing[$option['labels'][0] ?? ''] = $optionId;
            }
            $changes = [];
            $keys = [];
            $next = 0;
            $hasSwatches = false;
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
                $changes[$key]['labels'][0] ??= $adminLabel;
                if (($row['store_code'] ?? '') !== '') {
                    $changes[$key]['labels'][$this->resolver->storeId($row['store_code'])] = $row['label'];
                }
                if (($row['sort_order'] ?? '') !== '') {
                    $changes[$key]['order'] = (int) $row['sort_order'];
                }
                if (($row['swatch'] ?? '') !== '') {
                    $changes[$key]['swatch'] = strtolower($row['swatch']);
                    $hasSwatches = true;
                }
            }
            if ($hasSwatches && !Mage::helper('core')->isModuleEnabled('Mage_ConfigurableSwatches')) {
                $result->notices[] = "$code: the Configurable Swatches module is disabled, its swatches were skipped";
            }
            $this->saveAt($file, (int) array_key_first($rows), fn() => $service->saveOptions($attribute, $changes));
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
        $stored = Mage::getResourceModel('core/config_data_collection')
            ->addFieldToFilter('path', self::SWATCH_CONFIG_PATH)
            ->addFieldToFilter('scope', 'default')
            ->getFirstItem();
        $ids = array_filter(array_map(intval(...), explode(',', (string) $stored->getValue())));
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
