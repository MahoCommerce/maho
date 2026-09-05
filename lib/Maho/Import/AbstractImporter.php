<?php

/**
 * Two-pass importer: prepare() reads and checks every row, write() saves; validate() stops after the first pass.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho
 */

declare(strict_types=1);

namespace Maho\Import;

abstract class AbstractImporter implements ImporterInterface
{
    public function __construct(protected readonly Resolver $resolver = new Resolver()) {}

    #[\Override]
    public function validate(string $csvPath, array $options = []): void
    {
        $file = CsvFile::open($csvPath, $this->requiredColumns());
        $this->prepare($file, $options);
    }

    #[\Override]
    public function import(string $csvPath, array $options = [], ?Reporter $reporter = null): Result
    {
        $file = CsvFile::open($csvPath, $this->requiredColumns());
        $rows = $this->prepare($file, $options);
        return $this->write($file, $rows, $options, $reporter ?? new NullReporter());
    }

    /**
     * @return list<string>
     */
    abstract protected function requiredColumns(): array;

    /**
     * Normalised rows keyed by line number. Throws a RowException on the first bad row.
     *
     * @param array<string, mixed> $options
     * @return array<int, array<string, mixed>>
     */
    abstract protected function prepare(CsvFile $file, array $options): array;

    /**
     * @param array<int, array<string, mixed>> $rows
     * @param array<string, mixed> $options
     */
    abstract protected function write(CsvFile $file, array $rows, array $options, Reporter $reporter): Result;

    protected function fail(CsvFile $file, int $line, string $message): never
    {
        throw new RowException($file->getPath(), $line, $message);
    }

    /**
     * Runs a lookup or a conversion and pins its InvalidArgumentException to the row.
     *
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    protected function at(CsvFile $file, int $line, callable $callback): mixed
    {
        try {
            return $callback();
        } catch (\InvalidArgumentException $e) {
            $this->fail($file, $line, $e->getMessage());
        }
    }

    protected function requireValue(CsvFile $file, int $line, array $row, string $column): string
    {
        if (($row[$column] ?? '') === '') {
            $this->fail($file, $line, "$column is required");
        }
        return $row[$column];
    }
}
