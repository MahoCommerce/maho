<?php

/**
 * Build the DBAL objects and table options that a sql/schema.php file declares.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Maho\Db;

use Doctrine\DBAL\Schema\Column;
use Maho\Db\Schema\Renamer;

final class Schema
{
    public static function column(
        string $name,
        string $type,
        ?int $length = null,
        ?int $precision = null,
        int $scale = 0,
        bool $unsigned = false,
        bool $notNull = true,
        mixed $default = null,
        bool $autoincrement = false,
        string $comment = '',
    ): Column {
        return Column::editor()
            ->setUnquotedName($name)
            ->setTypeName($type)
            ->setLength($length)
            ->setPrecision($precision)
            ->setScale($scale)
            ->setUnsigned($unsigned)
            ->setNotNull($notNull)
            ->setDefaultValue($default)
            ->setAutoincrement($autoincrement)
            ->setComment($comment)
            ->create();
    }

    /**
     * The table options that record what a table and its columns used to be
     * called. Pass the result to TableEditor::setOptions().
     *
     * @param string|list<string> $from former table name(s), newest-first
     * @param array<string, string|list<string>> $columns current column name => former name(s), newest-first
     * @return array<string, mixed>
     */
    public static function renamed(string|array $from = [], array $columns = []): array
    {
        $history = ['table' => [], 'columns' => []];

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

        return [Renamer::OPTION => $history];
    }
}
