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
    /**
     * Doctrine DBAL Result object
     *
     * @var \Doctrine\DBAL\Result
     */
    protected $_result;

    /**
     * The adapter that created this statement
     *
     * @var \Maho\Db\Adapter\Pdo\Mysql
     */
    protected $_adapter;

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
     * @param int $fetchMode
     * @return array|false
     */
    public function fetch($fetchMode = 2)
    {
        // Mode 3 = numeric array (PDO::FETCH_NUM)
        if ($fetchMode === 3) {
            return $this->_result->fetchNumeric();
        }
        // Mode 2 = associative array (PDO::FETCH_ASSOC) - default
        return $this->_result->fetchAssociative();
    }

    /**
     * Fetch all rows from the result set
     *
     * @param int $fetchMode
     * @param int $col
     * @return array
     */
    public function fetchAll($fetchMode = 2, $col = 0)
    {
        // Mode 7 = column array (PDO::FETCH_COLUMN)
        if ($fetchMode === 7) {
            return $this->_result->fetchFirstColumn();
            // Mode 12 = key-value pairs (PDO::FETCH_KEY_PAIR)
        } elseif ($fetchMode === 12) {
            return $this->_result->fetchAllKeyValue();
            // Mode 3 = numeric arrays (PDO::FETCH_NUM)
        } elseif ($fetchMode === 3) {
            return $this->_result->fetchAllNumeric();
        }
        // Mode 2 = associative arrays (PDO::FETCH_ASSOC) - default
        return $this->_result->fetchAllAssociative();
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
     * Bind a column to a PHP variable
     *
     * @param mixed $column
     * @param mixed $param
     * @param int $type
     * @return bool
     */
    public function bindColumn($column, &$param, $type = null)
    {
        // Doctrine DBAL doesn't support bindColumn
        // This is rarely used, so we'll just return true for compatibility
        return true;
    }

    /**
     * Bind a parameter to the specified variable name
     *
     * @param mixed $parameter
     * @param mixed $variable
     * @param int $type
     * @param int $length
     * @param mixed $options
     * @return bool
     */
    public function bindParam($parameter, &$variable, $type = \PDO::PARAM_STR, $length = null, $options = null)
    {
        // Doctrine DBAL handles parameter binding differently
        // This is for compatibility only
        return true;
    }

    /**
     * Bind a value to a parameter
     *
     * @param mixed $parameter
     * @param mixed $value
     * @param int $type
     * @return bool
     */
    public function bindValue($parameter, $value, $type = \PDO::PARAM_STR)
    {
        // Doctrine DBAL handles parameter binding differently
        // This is for compatibility only
        return true;
    }

    /**
     * Execute the prepared statement
     *
     * @param array $params
     * @return bool
     */
    public function execute($params = null)
    {
        // In Doctrine DBAL, the statement is already executed when Result is created
        // This method is here for compatibility only
        return true;
    }

    /**
     * Set fetch mode
     *
     * @param int $mode
     * @return bool
     */
    public function setFetchMode($mode)
    {
        // Doctrine DBAL doesn't have a setFetchMode on Result
        // Mode is specified per fetch call
        return true;
    }

    /**
     * Get error code
     *
     * @return string
     */
    public function errorCode()
    {
        return '00000'; // Success code - errors would have thrown exceptions
    }

    /**
     * Get error info
     *
     * @return array
     */
    public function errorInfo()
    {
        return ['00000', null, null]; // No error
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
