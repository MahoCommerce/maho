<?php

/**
 * Former names of a declarative table and of its columns, and the renames a
 * live database still needs to catch up with them.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Maho\Db\Schema;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;

/**
 * A schema comparison is structural, so it cannot tell a rename from a drop
 * plus an add. The author supplies the missing identity link on the table the
 * rename produced, newest name first:
 *
 *     $t = $schema->createTable('sales_flat_order');
 *     Renamer::renamed($t, from: 'sales_order', columns: ['customer_email' => 'customer_mail']);
 *
 * That lands in one table option, which this class depends on for three DBAL
 * properties: Table::edit() copies options, so the prefix rebuild preserves
 * them; every platform builds DDL options from a closed allowlist, so an
 * unknown key never reaches a CREATE TABLE; and the Comparator never diffs
 * options, so the key causes no churn. DBAL's own Table::renameColumn() map is
 * unusable here, since TableEditor does not carry it and
 * Canonicalizer::preserveUndeclaredColumns() moves the old column into the
 * target before the Comparator could read it.
 *
 * Renames run before the plan compares anything, so every path downstream (the
 * comparator diff, the Postgres fixups, the SQLite rebuild) only ever sees a
 * database whose objects already carry their declared names.
 *
 * Each rename carries its own precondition instead of a position in an ordered
 * list. Sources and destinations are disjoint, since validate() rejects an
 * alias that names a declared object, so the renames commute and are
 * self-idempotent: no ordering, no version counter, no state table.
 *
 *   source present, destination absent   rename
 *   source absent,  destination present  skip, the rename already ran
 *   both absent                          skip
 *   both present                         refuse, a human must merge the two
 */
final class Renamer
{
    public const OPTION = 'maho_previous_names';

    /**
     * Record what $target and its columns used to be called. Repeated calls
     * append, so a module can extend a history another module declared.
     *
     * @param string|list<string> $from former table name(s), newest-first
     * @param array<string, string|list<string>> $columns current column name => former name(s), newest-first
     */
    public static function renamed(Table $target, string|array $from = [], array $columns = []): void
    {
        $history = self::read($target);

        foreach ((array) $from as $name) {
            if (!in_array($name, $history['table'], true)) {
                $history['table'][] = $name;
            }
        }

        foreach ($columns as $current => $previous) {
            $history['columns'][$current] ??= [];
            foreach ((array) $previous as $name) {
                if (!in_array($name, $history['columns'][$current], true)) {
                    $history['columns'][$current][] = $name;
                }
            }
        }

        $target->addOption(self::OPTION, $history);
    }

    /**
     * @return list<string> newest-first
     */
    public static function previousTableNames(Table $table): array
    {
        return self::read($table)['table'];
    }

    /**
     * @return array<string, list<string>> current name => former names, newest-first
     */
    public static function previousColumnNames(Table $table): array
    {
        return self::read($table)['columns'];
    }

    /**
     * Authors declare unprefixed names throughout, so the recorded table names
     * move with the ones Collector::rebuildWithPrefix() rewrites.
     */
    public static function applyPrefix(Table $table, string $prefix): void
    {
        if ($prefix === '' || !$table->hasOption(self::OPTION)) {
            return;
        }

        $history = self::read($table);
        $history['table'] = array_map(static fn(string $name): string => $prefix . $name, $history['table']);
        $table->addOption(self::OPTION, $history);
    }

    /**
     * Reject a history that cannot be applied unambiguously, whatever the live
     * database holds. Called from Collector::collect(), so the request path
     * gets the check too, via Status::isConverged().
     *
     * The first rule does double duty: an alias naming a declared table would
     * let one rename destroy another table, and forbidding it also makes a
     * rename cycle impossible, since a cycle needs both names declared.
     *
     * @throws UnsupportedMigrationException
     */
    public static function validate(Schema $target): void
    {
        $declaredTables = [];
        foreach ($target->getTables() as $table) {
            $declaredTables[strtolower(self::tableName($table))] = self::tableName($table);
        }

        $claimedBy = [];
        foreach ($target->getTables() as $table) {
            $name = self::tableName($table);
            foreach (self::previousTableNames($table) as $previous) {
                $key = strtolower($previous);

                if (isset($declaredTables[$key])) {
                    throw new UnsupportedMigrationException(sprintf(
                        'Table "%s" declares the previous name "%s", but "%s" is itself a declared table. '
                        . 'Renaming onto a live table would destroy it.',
                        $name,
                        $previous,
                        $declaredTables[$key],
                    ));
                }

                if (isset($claimedBy[$key])) {
                    throw new UnsupportedMigrationException(sprintf(
                        'Tables "%s" and "%s" both declare the previous name "%s". Only one table can inherit it.',
                        $claimedBy[$key],
                        $name,
                        $previous,
                    ));
                }

                $claimedBy[$key] = $name;
            }

            self::validateColumns($table, $name);
        }
    }

    /**
     * @throws UnsupportedMigrationException
     */
    private static function validateColumns(Table $table, string $tableName): void
    {
        $claimedBy = [];
        foreach (self::previousColumnNames($table) as $current => $previousNames) {
            if (!$table->hasColumn($current)) {
                throw new UnsupportedMigrationException(sprintf(
                    'Table "%s" declares a previous name for column "%s", but the table declares no such column.',
                    $tableName,
                    $current,
                ));
            }

            foreach ($previousNames as $previous) {
                $key = strtolower($previous);

                if ($table->hasColumn($previous)) {
                    throw new UnsupportedMigrationException(sprintf(
                        'Column "%s.%s" declares the previous name "%s", but "%s" is itself a declared column.',
                        $tableName,
                        $current,
                        $previous,
                        $previous,
                    ));
                }

                if (isset($claimedBy[$key])) {
                    throw new UnsupportedMigrationException(sprintf(
                        'Columns "%s" and "%s" of table "%s" both declare the previous name "%s".',
                        $claimedBy[$key],
                        $current,
                        $tableName,
                        $previous,
                    ));
                }

                $claimedBy[$key] = $current;
            }
        }
    }

    /**
     * $existing is the live table-name set plan() already built. The returned
     * sources map lets plan() introspect a renamed table under the name it
     * still physically carries.
     *
     * @param array<string, true> $existing
     * @return array{sql: list<string>, sources: array<string, string>} target name => live source name
     * @throws UnsupportedMigrationException
     */
    public static function planTableRenames(AbstractPlatform $platform, Schema $target, array $existing): array
    {
        $sql = [];
        $sources = [];

        foreach ($target->getTables() as $table) {
            $name = self::tableName($table);
            $present = self::presentNames(self::previousTableNames($table), $existing);

            if (isset($existing[$name])) {
                if ($present !== []) {
                    throw new UnsupportedMigrationException(sprintf(
                        'Cannot rename "%s" to "%s": both tables exist. Merge them by hand, then drop the old one.',
                        $present[0],
                        $name,
                    ));
                }
                continue;
            }

            if ($present === []) {
                continue;
            }

            if (count($present) > 1) {
                throw new UnsupportedMigrationException(sprintf(
                    'Cannot rename to "%s": the previous names %s all exist. Merge them by hand, then keep one.',
                    $name,
                    '"' . implode('", "', $present) . '"',
                ));
            }

            $sql[] = $platform->getRenameTableSQL(
                $platform->quoteSingleIdentifier($present[0]),
                $platform->quoteSingleIdentifier($name),
            );
            $sources[$name] = $present[0];
        }

        return ['sql' => $sql, 'sources' => $sources];
    }

    /**
     * Column renames the live table still needs, plus that table rewritten as
     * the renames leave it.
     *
     * @return array{sql: list<string>, live: Table}
     * @throws UnsupportedMigrationException
     */
    public static function renameLiveColumns(AbstractPlatform $platform, Table $live, Table $target): array
    {
        $tableName = self::tableName($target);
        $sql = [];
        $pending = [];

        foreach (self::previousColumnNames($target) as $current => $previousNames) {
            $present = array_values(array_filter($previousNames, $live->hasColumn(...)));

            if ($live->hasColumn($current)) {
                if ($present !== []) {
                    throw new UnsupportedMigrationException(sprintf(
                        'Cannot rename "%s.%s" to "%s": both columns exist. Copy the data across by hand, '
                        . 'then drop the old column.',
                        $tableName,
                        $present[0],
                        $current,
                    ));
                }
                continue;
            }

            if ($present === []) {
                continue;
            }

            if (count($present) > 1) {
                throw new UnsupportedMigrationException(sprintf(
                    'Cannot rename to "%s.%s": the previous names %s all exist. Keep one by hand.',
                    $tableName,
                    $current,
                    '"' . implode('", "', $present) . '"',
                ));
            }

            // Emitted directly: getRenameColumnSQL() is protected, and routing a
            // rename through getAlterTableSQL() makes SQLite rebuild the table
            // from a partial diff, which Applier::sqliteAlters() exists to avoid.
            $sql[] = sprintf(
                'ALTER TABLE %s RENAME COLUMN %s TO %s',
                $platform->quoteSingleIdentifier($tableName),
                $platform->quoteSingleIdentifier($present[0]),
                $platform->quoteSingleIdentifier($current),
            );
            $pending[] = [$present[0], $current];
        }

        if ($pending === []) {
            return ['sql' => [], 'live' => $live];
        }

        // Via the editor, not Table::renameColumn(): the latter leaves the
        // primary key constraint on the old column name, which would make the
        // Comparator rebuild the primary key.
        $editor = $live->edit();
        foreach ($pending as [$from, $to]) {
            $editor->renameColumnByUnquotedName($from, $to);
        }

        return ['sql' => $sql, 'live' => $editor->create()];
    }

    /**
     * @param list<string> $names
     * @param array<string, true> $existing
     * @return list<string>
     */
    private static function presentNames(array $names, array $existing): array
    {
        return array_values(array_filter($names, static fn(string $name): bool => isset($existing[$name])));
    }

    private static function tableName(Table $table): string
    {
        return $table->getObjectName()->getUnqualifiedName()->getValue();
    }

    /**
     * @return array{table: list<string>, columns: array<string, list<string>>}
     */
    private static function read(Table $table): array
    {
        return self::normalize($table->hasOption(self::OPTION) ? $table->getOption(self::OPTION) : null);
    }

    /**
     * @return array{table: list<string>, columns: array<string, list<string>>}
     */
    private static function normalize(mixed $value): array
    {
        if (!is_array($value)) {
            return ['table' => [], 'columns' => []];
        }

        $tables = [];
        foreach (is_array($value['table'] ?? null) ? $value['table'] : [] as $name) {
            $tables[] = (string) $name;
        }

        $columns = [];
        foreach (is_array($value['columns'] ?? null) ? $value['columns'] : [] as $current => $names) {
            $columns[(string) $current] = array_map(strval(...), is_array($names) ? array_values($names) : []);
        }

        return ['table' => $tables, 'columns' => $columns];
    }
}
