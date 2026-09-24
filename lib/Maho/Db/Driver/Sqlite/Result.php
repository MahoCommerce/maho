<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Maho\Db\Driver\Sqlite;

use Doctrine\DBAL\Driver\Result as ResultInterface;
use Doctrine\DBAL\Exception\InvalidColumnIndex;

final class Result implements ResultInterface
{
    /**
     * Scale of each DECIMAL column by position, null for every other column
     *
     * @var list<int|null>|null
     */
    private ?array $scales = null;

    /**
     * Scale of each DECIMAL column by name. A later column with the same name wins, as it does
     * in an associative row.
     *
     * @var array<string, int>|null
     */
    private ?array $scalesByName = null;

    public function __construct(private readonly \PDOStatement $statement) {}

    #[\Override]
    public function fetchNumeric(): array|false
    {
        $row = $this->fetch(\PDO::FETCH_NUM);
        return $row === false ? false : $this->formatNumeric($row);
    }

    #[\Override]
    public function fetchAssociative(): array|false
    {
        $row = $this->fetch(\PDO::FETCH_ASSOC);
        return $row === false ? false : $this->formatAssociative($row);
    }

    #[\Override]
    public function fetchOne(): mixed
    {
        $value = $this->fetch(\PDO::FETCH_COLUMN);
        return $value === false ? false : $this->formatValue($value, $this->getScales()[0] ?? null);
    }

    #[\Override]
    public function fetchAllNumeric(): array
    {
        return array_map($this->formatNumeric(...), $this->fetchAll(\PDO::FETCH_NUM));
    }

    #[\Override]
    public function fetchAllAssociative(): array
    {
        return array_map($this->formatAssociative(...), $this->fetchAll(\PDO::FETCH_ASSOC));
    }

    #[\Override]
    public function fetchFirstColumn(): array
    {
        $scale = $this->getScales()[0] ?? null;
        $values = $this->fetchAll(\PDO::FETCH_COLUMN);
        if ($scale === null) {
            return $values;
        }
        return array_map(fn(mixed $value): mixed => $this->formatValue($value, $scale), $values);
    }

    #[\Override]
    public function rowCount(): int
    {
        try {
            return $this->statement->rowCount();
        } catch (\PDOException $e) {
            throw Exception::new($e);
        }
    }

    #[\Override]
    public function columnCount(): int
    {
        try {
            return $this->statement->columnCount();
        } catch (\PDOException $e) {
            throw Exception::new($e);
        }
    }

    /**
     * Declared on the interface only as a @method tag, so it takes no #[\Override]
     */
    public function getColumnName(int $index): string
    {
        try {
            $meta = $this->statement->getColumnMeta($index);
        } catch (\ValueError $e) {
            throw InvalidColumnIndex::new($index, $e);
        } catch (\PDOException $e) {
            throw Exception::new($e);
        }

        if ($meta === false) {
            throw InvalidColumnIndex::new($index);
        }

        return $meta['name'];
    }

    #[\Override]
    public function free(): void
    {
        $this->statement->closeCursor();
    }

    private function fetch(int $mode): mixed
    {
        try {
            return $this->statement->fetch($mode);
        } catch (\PDOException $e) {
            throw Exception::new($e);
        }
    }

    /**
     * @return list<mixed>
     */
    private function fetchAll(int $mode): array
    {
        try {
            return $this->statement->fetchAll($mode);
        } catch (\PDOException $e) {
            throw Exception::new($e);
        }
    }

    /**
     * @param list<mixed> $row
     * @return list<mixed>
     */
    private function formatNumeric(array $row): array
    {
        foreach ($this->getScales() as $index => $scale) {
            if ($scale !== null) {
                $row[$index] = $this->formatValue($row[$index], $scale);
            }
        }
        return $row;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function formatAssociative(array $row): array
    {
        $this->getScales();
        foreach ($this->scalesByName as $name => $scale) {
            $row[$name] = $this->formatValue($row[$name], $scale);
        }
        return $row;
    }

    /**
     * Format a value of a DECIMAL column as MySQL and PostgreSQL return it
     *
     * Only int and float values change. NULL stays NULL, and a string stays as SQLite stored it,
     * because a NUMERIC column keeps a value it cannot convert to a number as text.
     */
    private function formatValue(mixed $value, ?int $scale): mixed
    {
        if ($scale === null) {
            return $value;
        }
        if (is_int($value)) {
            return $scale > 0 ? $value . '.' . str_repeat('0', $scale) : (string) $value;
        }
        if (is_float($value)) {
            // number_format() keeps the sign of -0.0, which neither MySQL nor PostgreSQL prints
            return number_format($value == 0 ? 0.0 : $value, $scale, '.', '');
        }
        return $value;
    }

    /**
     * @return list<int|null>
     */
    private function getScales(): array
    {
        if ($this->scales !== null) {
            return $this->scales;
        }

        $this->scales = [];
        $this->scalesByName = [];
        $count = $this->statement->columnCount();
        for ($index = 0; $index < $count; $index++) {
            $meta = $this->statement->getColumnMeta($index);
            $scale = $this->parseScale($meta['sqlite:decl_type'] ?? null);
            $this->scales[] = $scale;

            $name = $meta['name'] ?? null;
            if ($name === null) {
                continue;
            }
            if ($scale === null) {
                unset($this->scalesByName[$name]);
            } else {
                $this->scalesByName[$name] = $scale;
            }
        }

        return $this->scales;
    }

    /**
     * Read the scale from a declared type such as NUMERIC(12,4) or decimal(10,0)
     *
     * A bare DECIMAL or NUMERIC has a scale of 0, as a bare DECIMAL has on MySQL. A computed
     * column, such as SUM(price), has no declared type and keeps the value SQLite returns.
     */
    private function parseScale(?string $declaredType): ?int
    {
        if ($declaredType === null
            || !preg_match('/^\s*(?:decimal|numeric)\s*(?:\(\s*\d+\s*(?:,\s*(\d+)\s*)?\))?\s*$/i', $declaredType, $match)
        ) {
            return null;
        }
        return (int) ($match[1] ?? 0);
    }
}
