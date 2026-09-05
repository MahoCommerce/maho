<?php

/**
 * Runs a Mage_ImportExport entity on a checked temp copy of the CSV, so a bad file can never be truncated
 * and every error carries a line number.
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
use Maho\Import\NullReporter;
use Maho\Import\Reporter;
use Maho\Import\Result;
use Maho\Import\RowException;

abstract class AbstractImportExportImporter extends AbstractImporter
{
    public const OPTION_BEHAVIOR = 'behavior';

    abstract protected function entityCode(): string;

    /**
     * Columns appended to every row of the temp copy, resolved once per run.
     *
     * @return array<string, string>
     */
    protected function injectedColumns(): array
    {
        return [];
    }

    /**
     * @param array<string, string> $row
     * @param array<string, mixed> $options
     */
    abstract protected function checkRow(CsvFile $file, int $line, array $row, array $options): void;

    #[\Override]
    protected function prepare(CsvFile $file, array $options): array
    {
        $behavior = $options[self::OPTION_BEHAVIOR] ?? \Mage_ImportExport_Model_Import::BEHAVIOR_APPEND;
        $behaviors = [
            \Mage_ImportExport_Model_Import::BEHAVIOR_APPEND,
            \Mage_ImportExport_Model_Import::BEHAVIOR_REPLACE,
            \Mage_ImportExport_Model_Import::BEHAVIOR_DELETE,
        ];
        if (!in_array($behavior, $behaviors, true)) {
            $this->fail($file, 0, "behavior '$behavior' is not one of " . implode(', ', $behaviors));
        }
        foreach (array_keys($this->injectedColumns()) as $column) {
            if ($file->hasColumn($column)) {
                $this->fail($file, 1, "column $column is set by the importer, remove it");
            }
        }
        $rows = [];
        foreach ($file as $line => $row) {
            $this->checkRow($file, $line, $row, $options);
            $rows[$line] = $row;
        }
        if ($rows === []) {
            $this->fail($file, 1, 'the file has no data rows');
        }
        return $rows;
    }

    /**
     * Fills the option defaults that depend on the file location.
     *
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    protected function normalize(CsvFile $file, array $options): array
    {
        return $options;
    }

    #[\Override]
    public function validate(string $csvPath, array $options = []): void
    {
        $file = CsvFile::open($csvPath, $this->requiredColumns());
        $options = $this->normalize($file, $options);
        $rows = $this->prepare($file, $options);
        $copy = $this->copy($file, $rows);
        try {
            $this->validated($file, $copy, $options);
        } finally {
            @unlink($copy);
        }
    }

    #[\Override]
    public function import(string $csvPath, array $options = [], ?Reporter $reporter = null): Result
    {
        $file = CsvFile::open($csvPath, $this->requiredColumns());
        $options = $this->normalize($file, $options);
        $rows = $this->prepare($file, $options);
        return $this->write($file, $rows, $options, $reporter ?? new NullReporter());
    }

    #[\Override]
    protected function write(CsvFile $file, array $rows, array $options, Reporter $reporter): Result
    {
        $copy = $this->copy($file, $rows);
        try {
            $import = $this->validated($file, $copy, $options);
            $import->importSource();
            $import->invalidateIndex();
            $result = new Result();
            $result->created = (int) $import->getProcessedEntitiesCount();
            foreach ($import->getNotices() as $notice) {
                $result->notices[] = (string) $notice;
            }
            $reporter->info($import->getFormatedLogTrace());
            return $result;
        } finally {
            @unlink($copy);
        }
    }

    /**
     * @param array<string, mixed> $options
     */
    private function validated(CsvFile $file, string $copy, array $options): \Mage_ImportExport_Model_Import
    {
        /** @var \Mage_ImportExport_Model_Import $import */
        $import = Mage::getModel('importexport/import');
        $import->setData(array_merge($options, [
            'entity' => $this->entityCode(),
            'behavior' => $options[self::OPTION_BEHAVIOR] ?? \Mage_ImportExport_Model_Import::BEHAVIOR_APPEND,
        ]));
        Mage::getSingleton('eav/config')->clear();
        if (!$import->validateSource($copy)) {
            throw $this->errorsOf($file, $import);
        }
        return $import;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    private function copy(CsvFile $file, array $rows): string
    {
        $dir = \Mage_ImportExport_Model_Import::getWorkingDir();
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $path = $dir . $this->entityCode() . '-' . uniqid() . '.csv';
        $handle = fopen($path, 'w');
        if ($handle === false) {
            throw new RowException($file->getPath(), 0, "cannot write the working copy $path");
        }
        $injected = $this->injectedColumns();
        fputcsv($handle, array_merge($file->getColumns(), array_keys($injected)), escape: '\\');
        foreach ($rows as $row) {
            $values = [];
            foreach ($file->getColumns() as $column) {
                $values[] = $row[$column];
            }
            fputcsv($handle, array_merge($values, array_values($injected)), escape: '\\');
        }
        fclose($handle);
        return $path;
    }

    /**
     * The entity reports data rows counted from 1; the working copy keeps the source order, so line = row + 1.
     */
    private function errorsOf(CsvFile $file, \Mage_ImportExport_Model_Import $import): RowException
    {
        $lines = [];
        $first = 0;
        foreach ($import->getErrors() as $message => $rowNumbers) {
            $numbers = array_map(static fn($row) => (int) (is_array($row) ? $row[0] : $row) + 1, (array) $rowNumbers);
            sort($numbers);
            $first = $first === 0 ? $numbers[0] : min($first, $numbers[0]);
            $lines[] = $message . ' (line ' . implode(', ', $numbers) . ')';
        }
        return new RowException($file->getPath(), $first, implode('; ', $lines));
    }
}
