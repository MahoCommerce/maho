<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    MahoLib
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Maho\Db\Statement;

/**
 * Common interface for database statement wrappers
 *
 * This interface defines the standard methods that all statement classes must implement
 * to provide compatibility with code that expects a Zend_Db_Statement-like interface.
 */
interface StatementInterface
{
    // Fetch mode constants
    public const FETCH_ASSOC = 2;       // PDO::FETCH_ASSOC
    public const FETCH_NUM = 3;         // PDO::FETCH_NUM
    public const FETCH_COLUMN = 7;      // PDO::FETCH_COLUMN
    public const FETCH_KEY_PAIR = 12;   // PDO::FETCH_KEY_PAIR

    /**
     * Fetch a row from the result set
     *
     * @param int $fetchMode One of self::FETCH_* constants
     * @return array<string|int, mixed>|false
     */
    public function fetch(int $fetchMode = self::FETCH_ASSOC): array|false;

    /**
     * Fetch all rows from the result set
     *
     * @param int $fetchMode One of self::FETCH_* constants
     * @param int $col Column index (used for FETCH_COLUMN)
     * @return array<int, array<string|int, mixed>|mixed>
     */
    public function fetchAll(int $fetchMode = self::FETCH_ASSOC, int $col = 0): array;

    /**
     * Fetch a single column from the next row
     */
    public function fetchColumn(int $col = 0): mixed;

    /**
     * Fetch an object from the result set
     *
     * @param array<int, mixed> $config
     */
    public function fetchObject(string $class = 'stdClass', array $config = []): object|false;

    /**
     * Return the number of rows affected by the last statement
     */
    public function rowCount(): int;

    /**
     * Closes the cursor, allowing the statement to be executed again
     */
    public function closeCursor(): bool;

    /**
     * Returns the number of columns in the result set
     */
    public function columnCount(): int;

    /**
     * Get the Doctrine DBAL Result object
     */
    public function getResult(): \Doctrine\DBAL\Result;
}
