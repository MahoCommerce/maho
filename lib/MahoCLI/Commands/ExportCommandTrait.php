<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package MahoCLI
 */

declare(strict_types=1);

namespace MahoCLI\Commands;

use Mage;
use Mage_Core_Exception;
use Mage_Eav_Model_Entity_Attribute;
use Mage_ImportExport_Model_Export as Export;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

trait ExportCommandTrait
{
    protected const string CSV_DESCRIPTION = 'Path of the CSV file to write (default: var/export/<entity>_<date>.csv)';
    protected const string FILTER_DESCRIPTION = 'Export only the rows that match: code=value (text matches a part), or code=from..to for a date or a number';
    protected const string SKIP_DESCRIPTION = 'Code of an attribute to leave out of the file';

    /**
     * Write the file under a temporary name and rename it at the end, so a reader never gets a part of the file.
     *
     * @param list<string> $filters
     * @param list<string> $skip
     */
    protected function runExport(string $entity, ?string $csv, array $filters, array $skip, OutputInterface $output): int
    {
        $csv ??= Mage::getBaseDir('var') . '/export/' . $entity . '_' . date('Ymd_His') . '.csv';
        $partial = $csv . '.part';
        try {
            $attributes = $this->exportAttributes($entity);
            $data = [
                'entity' => $entity,
                'file_format' => 'csv',
                Export::FILTER_ELEMENT_GROUP => $this->exportFilters($filters, $attributes),
                Export::FILTER_ELEMENT_SKIP => $this->exportSkip($skip, $attributes),
            ];
            $directory = dirname($csv);
            if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
                Mage::throwException("Cannot create the directory {$directory}");
            }
            $result = Mage::getModel('importexport/export')->setData($data)->setDestination($partial)->exportFile();
            if (!rename($partial, $csv)) {
                Mage::throwException("Cannot write {$csv}");
            }
        } catch (Mage_Core_Exception $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        } finally {
            if (is_file($partial)) {
                unlink($partial);
            }
        }
        $rows = (int) $result['rows'];
        $output->writeln(sprintf('<info>%s: %d %s</info>', $csv, $rows, $rows === 1 ? 'row' : 'rows'));
        return Command::SUCCESS;
    }

    /**
     * @return array<string, Mage_Eav_Model_Entity_Attribute>
     */
    private function exportAttributes(string $entity): array
    {
        $model = Mage::getModel('importexport/export')->setData(['entity' => $entity, 'file_format' => 'csv']);
        $attributes = [];
        foreach ($model->filterAttributeCollection($model->getEntityAttributeCollection()) as $attribute) {
            $attributes[$attribute->getAttributeCode()] = $attribute;
        }
        return $attributes;
    }

    /**
     * @param list<string> $filters
     * @param array<string, Mage_Eav_Model_Entity_Attribute> $attributes
     * @return array<string, string|list<string>>
     */
    private function exportFilters(array $filters, array $attributes): array
    {
        $result = [];
        foreach ($filters as $filter) {
            [$code, $value] = array_pad(explode('=', $filter, 2), 2, null);
            if ($code === '' || $value === null) {
                Mage::throwException("Write the filter '{$filter}' as code=value");
            }
            $type = Export::getAttributeFilterType($this->exportAttribute($code, $attributes));
            if ($type !== Export::FILTER_TYPE_DATE && $type !== Export::FILTER_TYPE_NUMBER) {
                $result[$code] = $value;
                continue;
            }
            $range = explode('..', $value);
            if (count($range) !== 2) {
                Mage::throwException("Write the filter on {$code} as {$code}=from..to, one side can be empty");
            }
            foreach ($range as $limit) {
                $valid = $type === Export::FILTER_TYPE_DATE ? date_create($limit) !== false : is_numeric($limit);
                if ($limit !== '' && !$valid) {
                    Mage::throwException("'{$limit}' is not a valid {$type} for the filter on {$code}");
                }
            }
            $result[$code] = $range;
        }
        return $result;
    }

    /**
     * @param list<string> $skip
     * @param array<string, Mage_Eav_Model_Entity_Attribute> $attributes
     * @return list<int>
     */
    private function exportSkip(array $skip, array $attributes): array
    {
        return array_map(fn(string $code): int => (int) $this->exportAttribute($code, $attributes)->getAttributeId(), $skip);
    }

    /**
     * @param array<string, Mage_Eav_Model_Entity_Attribute> $attributes
     */
    private function exportAttribute(string $code, array $attributes): Mage_Eav_Model_Entity_Attribute
    {
        if (!isset($attributes[$code])) {
            Mage::throwException("The export has no attribute '{$code}'");
        }
        return $attributes[$code];
    }
}
