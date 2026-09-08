<?php

/**
 * A CSV file read row by row as associative arrays keyed by header, with physical line numbers.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho
 */

declare(strict_types=1);

namespace Maho\Import;

/**
 * @implements \IteratorAggregate<int, array<string, string>>
 */
final class CsvFile implements \IteratorAggregate
{
    /** @var list<string> */
    private array $columns;

    /**
     * @param list<string> $required
     */
    private function __construct(private readonly string $path, array $required)
    {
        if (!is_readable($path)) {
            throw new RowException($path, 0, 'file is missing or not readable');
        }
        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new RowException($path, 0, 'file cannot be opened');
        }
        $header = fgetcsv($handle, escape: '\\');
        fclose($handle);
        if (!is_array($header) || $header === [null]) {
            throw new RowException($path, 1, 'header row is missing');
        }
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]);
        $this->columns = array_map(static fn($column) => trim((string) $column), $header);

        if (in_array('', $this->columns, true)) {
            throw new RowException($path, 1, 'header has an empty column name');
        }
        $duplicates = array_diff_assoc($this->columns, array_unique($this->columns));
        if ($duplicates !== []) {
            throw new RowException($path, 1, 'duplicate column ' . reset($duplicates));
        }
        $missing = array_diff($required, $this->columns);
        if ($missing !== []) {
            throw new RowException($path, 1, 'missing required column(s) ' . implode(', ', $missing));
        }
    }

    /**
     * @param list<string> $required
     */
    public static function open(string $path, array $required = []): self
    {
        return new self($path, $required);
    }

    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * @return list<string>
     */
    public function getColumns(): array
    {
        return $this->columns;
    }

    public function hasColumn(string $column): bool
    {
        return in_array($column, $this->columns, true);
    }

    /**
     * Yields the physical line number (the header is line 1) and the row keyed by column.
     * Blank lines are skipped, short rows are padded, long rows are an error.
     *
     * @return \Generator<int, array<string, string>>
     */
    #[\Override]
    public function getIterator(): \Generator
    {
        $handle = fopen($this->path, 'r');
        if ($handle === false) {
            throw new RowException($this->path, 0, 'file cannot be opened');
        }
        try {
            $line = 1;
            fgetcsv($handle, escape: '\\');
            $width = count($this->columns);
            while (($row = fgetcsv($handle, escape: '\\')) !== false) {
                $line++;
                if ($row === [null] || ($row === [''] && $width > 1)) {
                    continue;
                }
                if (count($row) > $width) {
                    throw new RowException($this->path, $line, sprintf('%d values for %d columns', count($row), $width));
                }
                $row = array_pad($row, $width, '');
                $values = array_map(static fn($value) => trim((string) $value), $row);
                yield $line => array_combine($this->columns, $values);
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * Every row keyed by line number, for importers that need more than one pass.
     *
     * @return array<int, array<string, string>>
     */
    public function toArray(): array
    {
        return iterator_to_array($this->getIterator());
    }

    /**
     * A pipe-separated list cell, trimmed, without empty entries.
     *
     * @return list<string>
     */
    public static function list(string $value): array
    {
        $items = array_map(trim(...), explode('|', $value));
        return array_values(array_filter($items, static fn(string $item) => $item !== ''));
    }

    public static function bool(string $value, bool $default): bool
    {
        if ($value === '') {
            return $default;
        }
        return match (strtolower($value)) {
            '1', 'true', 'yes', 'y' => true,
            '0', 'false', 'no', 'n' => false,
            default => throw new \InvalidArgumentException("'$value' is not a boolean"),
        };
    }
}
