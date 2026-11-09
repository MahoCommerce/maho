<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho
 */

declare(strict_types=1);

namespace Maho\Db\Sql;

/**
 * Splits SQL at statement terminators outside quoted values and comments.
 * This is a bounded splitter, not a SQL parser.
 */
final class StatementSplitter
{
    public const array ENGINES = ['mysql', 'pgsql', 'sqlite'];

    private const string WHITESPACE = " \t\n\r\v\f";

    /**
     * Characters that can start a quote, a comment or a dollar-quoted value, per engine.
     */
    private const array SPECIAL_CHARS = [
        'mysql' => "'\"`-/#",
        'pgsql' => "'\"`-/\$",
        'sqlite' => "'\"`-/[",
    ];

    /**
     * $sqlMode is the MySQL session sql_mode. NO_BACKSLASH_ESCAPES and ANSI_QUOTES change
     * how MySQL reads a quoted value. The other engines ignore it.
     *
     * A MySQL line "DELIMITER x" sets the statement terminator to x, as in the mysql client.
     *
     * @return list<string>
     * @throws \InvalidArgumentException if $engine is not in ENGINES
     */
    public static function split(string $sql, string $engine, string $sqlMode = ''): array
    {
        if (!in_array($engine, self::ENGINES, true)) {
            throw new \InvalidArgumentException("Unsupported database engine: {$engine}");
        }

        $modes = $engine === 'mysql' ? explode(',', strtoupper($sqlMode)) : [];
        $noBackslashEscapes = in_array('NO_BACKSLASH_ESCAPES', $modes, true);
        $ansiQuotes = in_array('ANSI_QUOTES', $modes, true);

        $delimiter = ';';
        $stopChars = self::SPECIAL_CHARS[$engine] . $delimiter[0];
        $statements = [];
        $statement = '';
        $hasSql = false;
        $length = strlen($sql);
        $i = 0;

        while ($i < $length) {
            if (substr_compare($sql, $delimiter, $i, strlen($delimiter)) === 0) {
                if ($engine === 'sqlite' && self::isOpenSqliteTrigger($statement)) {
                    $statement .= $delimiter;
                    $i += strlen($delimiter);
                    continue;
                }
                if ($hasSql) {
                    $statements[] = trim($statement);
                }
                $statement = '';
                $hasSql = false;
                $i += strlen($delimiter);
                continue;
            }

            // Before the first SQL of a statement, take only whitespace, so that DELIMITER is seen.
            $run = $hasSql ? strcspn($sql, $stopChars, $i) : strspn($sql, self::WHITESPACE, $i);
            if ($run > 0) {
                $statement .= substr($sql, $i, $run);
                $i += $run;
                continue;
            }

            $char = $sql[$i];
            $next = $sql[$i + 1] ?? '';

            if ($engine === 'mysql' && !$hasSql && ($char === 'D' || $char === 'd')
                && preg_match('/\GDELIMITER[ \t]+(\S+)[^\n]*\n?/i', $sql, $match, 0, $i)
            ) {
                $delimiter = $match[1];
                $stopChars = self::SPECIAL_CHARS[$engine] . $delimiter[0];
                $statement = '';
                $i += strlen($match[0]);
                continue;
            }

            // SQL line comments. MySQL additionally accepts # comments.
            $dashComment = $char === '-' && $next === '-'
                && ($engine !== 'mysql' || ($i + 2 >= $length) || ctype_space($sql[$i + 2]));
            if ($dashComment || ($engine === 'mysql' && $char === '#')) {
                // PostgreSQL also ends a line comment at a carriage return.
                $lineLength = strcspn($sql, $engine === 'pgsql' ? "\r\n" : "\n", $i);
                $end = min($length, $i + $lineLength + 1);
                $statement .= substr($sql, $i, $end - $i);
                $i = $end;
                continue;
            }

            // Keep comment text in its statement, but do not treat its semicolons as delimiters.
            if ($char === '/' && $next === '*') {
                $executableMysqlComment = $engine === 'mysql'
                    && (substr($sql, $i, 3) === '/*!' || substr($sql, $i, 4) === '/*M!');
                $depth = 1;
                $end = $i + 2;
                while ($end < $length && $depth > 0) {
                    $end += strcspn($sql, '/*', $end);
                    if ($engine === 'pgsql' && substr($sql, $end, 2) === '/*') {
                        $depth++;
                        $end += 2;
                    } elseif (substr($sql, $end, 2) === '*/') {
                        $depth--;
                        $end += 2;
                    } else {
                        $end++;
                    }
                }
                $end = min($end, $length);
                $statement .= substr($sql, $i, $end - $i);
                $hasSql = $hasSql || $executableMysqlComment;
                $i = $end;
                continue;
            }

            // PostgreSQL dollar-quoted strings, including tagged forms.
            if ($engine === 'pgsql' && $char === '$'
                && ($i === 0 || !preg_match('/[A-Za-z0-9_$\x80-\xff]/', $sql[$i - 1]))
                && preg_match('/\G\$([A-Za-z_\x80-\xff][A-Za-z_0-9\x80-\xff]*)?\$/', $sql, $match, 0, $i)
            ) {
                $dollarTag = $match[0];
                $end = strpos($sql, $dollarTag, $i + strlen($dollarTag));
                $end = $end === false ? $length : $end + strlen($dollarTag);
                $statement .= substr($sql, $i, $end - $i);
                $hasSql = true;
                $i = $end;
                continue;
            }

            if ($char === "'" || $char === '"' || $char === '`' || ($engine === 'sqlite' && $char === '[')) {
                $closingQuote = $char === '[' ? ']' : $char;
                $start = $i;
                $backslashEscapes = $engine === 'mysql' && !$noBackslashEscapes
                    && $char !== '`' && !($ansiQuotes && $char === '"');
                // PostgreSQL only gives backslashes escape meaning in E'...' strings.
                if ($engine === 'pgsql' && $char === "'" && $i > 0
                    && ($sql[$i - 1] === 'E' || $sql[$i - 1] === 'e')
                    && ($i < 2 || !preg_match('/[A-Za-z_0-9$\x80-\xff]/', $sql[$i - 2]))
                ) {
                    $backslashEscapes = true;
                }

                $quoteStops = $backslashEscapes ? '\\' . $closingQuote : $closingQuote;
                $i++;
                while ($i < $length) {
                    $i += strcspn($sql, $quoteStops, $i);
                    if ($i >= $length) {
                        break;
                    }
                    if ($sql[$i] === '\\') {
                        $i += 2;
                        continue;
                    }
                    if ($closingQuote !== ']' && ($sql[$i + 1] ?? '') === $closingQuote) {
                        $i += 2;
                        continue;
                    }
                    $i++;
                    break;
                }
                $i = min($i, $length);
                $statement .= substr($sql, $start, $i - $start);
                $hasSql = true;
                continue;
            }

            $statement .= $char;
            $hasSql = true;
            $i++;
        }

        if ($hasSql) {
            $statements[] = trim($statement);
        }

        return $statements;
    }

    /**
     * Whether $statement is a SQLite CREATE TRIGGER whose BEGIN ... END body is still open.
     * As in sqlite3_complete(), the word END before a semicolon closes the body.
     */
    private static function isOpenSqliteTrigger(string $statement): bool
    {
        if (!preg_match('#^(?:\s+|--[^\n]*\n?|/\*.*?\*/)*CREATE\s+(?:TEMP(?:ORARY)?\s+)?TRIGGER\b#is', $statement)) {
            return false;
        }

        $statement = rtrim($statement);
        return strcasecmp(substr($statement, -3), 'END') !== 0
            || preg_match('/[A-Za-z0-9_$\x80-\xff]/', substr($statement, -4, 1)) === 1;
    }
}
