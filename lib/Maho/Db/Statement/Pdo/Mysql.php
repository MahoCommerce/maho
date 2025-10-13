<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_Db
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2025 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Wrapper for Doctrine DBAL Result to provide Zend_Db_Statement-like interface
 *
 * This class wraps Doctrine DBAL's Result class to maintain compatibility
 * with code that expects a Zend_Db_Statement interface.
 */

namespace Maho\Db\Statement\Pdo;

class Mysql
{
    // Fetch mode constants for backward compatibility with Zend_Db_Statement
    // These match PDO constants but don't require PDO extension
    public const FETCH_ASSOC = 2;       // PDO::FETCH_ASSOC
    public const FETCH_NUM = 3;         // PDO::FETCH_NUM
    public const FETCH_COLUMN = 7;      // PDO::FETCH_COLUMN
    public const FETCH_KEY_PAIR = 12;   // PDO::FETCH_KEY_PAIR

    /**
     * Doctrine DBAL Result object
     */
    protected \Doctrine\DBAL\Result $_result;

    /**
     * The adapter that created this statement
     */
    protected \Maho\Db\Adapter\Pdo\Mysql $_adapter;

    /**
     * Constructor
     */
    public function __construct(\Maho\Db\Adapter\Pdo\Mysql $adapter, \Doctrine\DBAL\Result $result)
    {
        $this->_adapter = $adapter;
        $this->_result = $result;
    }

    /**
     * Fetch a row from the result set
     *
     * @param int $fetchMode One of self::FETCH_* constants
     * @return array|false
     */
    public function fetch($fetchMode = self::FETCH_ASSOC)
    {
        if ($fetchMode === self::FETCH_NUM) {
            return $this->_result->fetchNumeric();
        }
        // Default: associative array
        return $this->_result->fetchAssociative();
    }

    /**
     * Fetch all rows from the result set
     *
     * @param int $fetchMode One of self::FETCH_* constants
     * @param int $col Column index (used for FETCH_COLUMN)
     * @return array
     */
    public function fetchAll($fetchMode = self::FETCH_ASSOC, $col = 0)
    {
        return match ($fetchMode) {
            self::FETCH_COLUMN => $this->_result->fetchFirstColumn(),
            self::FETCH_KEY_PAIR => $this->_result->fetchAllKeyValue(),
            self::FETCH_NUM => $this->_result->fetchAllNumeric(),
            default => $this->_result->fetchAllAssociative(),
        };
    }

    /**
     * Fetch a single column from the next row
     *
     * @param int $col
     * @return mixed|false
     */
    public function fetchColumn($col = 0)
    {
        return $this->_result->fetchOne();
    }

    /**
     * Fetch an object from the result set
     *
     * @param string $class
     * @return object|false
     */
    public function fetchObject($class = 'stdClass', array $config = [])
    {
        $row = $this->_result->fetchAssociative();
        if (!$row) {
            return false;
        }

        if ($class === 'stdClass') {
            return (object) $row;
        }

        $obj = new $class(...$config);
        foreach ($row as $key => $value) {
            $obj->$key = $value;
        }
        return $obj;
    }

    /**
     * Return the number of rows affected by the last statement
     *
     * @return int
     */
    public function rowCount()
    {
        return $this->_result->rowCount();
    }

    /**
     * Closes the cursor, allowing the statement to be executed again
     *
     * @return bool
     */
    public function closeCursor()
    {
        $this->_result->free();
        return true;
    }

    /**
     * Returns the number of columns in the result set
     *
     * @return int
     */
    public function columnCount()
    {
        return $this->_result->columnCount();
    }


    /**
     * Get the Doctrine DBAL Result object
     *
     * @return \Doctrine\DBAL\Result
     */
    public function getResult()
    {
        return $this->_result;
    }

    /**
     * Get the adapter
     *
     * @return \Maho\Db\Adapter\Pdo\Mysql
     */
    public function getAdapter()
    {
        return $this->_adapter;
    }
}
