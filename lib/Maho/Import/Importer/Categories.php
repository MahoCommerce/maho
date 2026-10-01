<?php

/**
 * Categories in the Mage_ImportExport layout, keyed by _root and _path or by category_id, with pictures read from a media dir.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho
 */

declare(strict_types=1);

namespace Maho\Import\Importer;

use Maho\Import\CsvFile;

class Categories extends AbstractImportExportImporter
{
    public const OPTION_MEDIA_DIR = \Mage_ImportExport_Model_Import_Entity_Category::PARAM_MEDIA_DIR;

    /**
     * The column names of the first sample data packs. Drop them when maho-sample-data uses the new names.
     */
    private const LEGACY_COLUMNS = [
        'root' => \Mage_ImportExport_Model_Import_Entity_Category::COL_ROOT,
        'path' => \Mage_ImportExport_Model_Import_Entity_Category::COL_PATH,
        'store_code' => \Mage_ImportExport_Model_Import_Entity_Category::COL_STORE,
    ];

    #[\Override]
    protected function entityCode(): string
    {
        return 'catalog_category';
    }

    #[\Override]
    protected function requiredColumns(): array
    {
        return [];
    }

    /**
     * The default media dir applies only when it exists. Without a media dir, the pictures must already be in media/catalog/category.
     */
    #[\Override]
    protected function normalize(CsvFile $file, array $options): array
    {
        $default = dirname($file->getPath()) . '/media/catalog/category';
        if (!isset($options[self::OPTION_MEDIA_DIR]) && is_dir($default)) {
            $options[self::OPTION_MEDIA_DIR] = $default;
        }
        return $options;
    }

    /**
     * Rows go parents first and store rows last, so the file can list them in any order.
     */
    #[\Override]
    protected function prepare(CsvFile $file, array $options): array
    {
        $rows = [];
        foreach (parent::prepare($file, $options) as $line => $row) {
            foreach (self::LEGACY_COLUMNS as $legacy => $column) {
                if (array_key_exists($legacy, $row) && !array_key_exists($column, $row)) {
                    $row[$column] = $row[$legacy];
                    unset($row[$legacy]);
                }
            }
            $rows[$line] = $row;
        }
        uasort($rows, fn(array $a, array $b): int => $this->order($a) <=> $this->order($b));
        return $rows;
    }

    #[\Override]
    protected function checkRow(CsvFile $file, int $line, array $row, array $options): void {}

    /**
     * @param array<string, string> $row
     * @return array{0: int, 1: bool}
     */
    private function order(array $row): array
    {
        $path = trim($row[\Mage_ImportExport_Model_Import_Entity_Category::COL_PATH] ?? '', '/');
        $isStoreRow = ($row[\Mage_ImportExport_Model_Import_Entity_Category::COL_STORE] ?? '') !== '';
        return [$path === '' ? 0 : substr_count($path, '/') + 1, $isStoreRow];
    }
}
