<?php

/**
 * Maho
 *
 * @package    Zend_Db
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Compatibility stub for Zend_Db constants and factory method
 * Provides constants used throughout the codebase for type hinting and fetch modes
 * @deprecated since 25.11
 */
class Zend_Db
{
    /**
     * Constants for fetch modes
     */
    public const FETCH_LAZY = PDO::FETCH_LAZY;
    public const FETCH_ASSOC = PDO::FETCH_ASSOC;
    public const FETCH_NUM = PDO::FETCH_NUM;
    public const FETCH_BOTH = PDO::FETCH_BOTH;
    public const FETCH_OBJ = PDO::FETCH_OBJ;
    public const FETCH_BOUND = PDO::FETCH_BOUND;
    public const FETCH_COLUMN = PDO::FETCH_COLUMN;
    public const FETCH_CLASS = PDO::FETCH_CLASS;
    public const FETCH_INTO = PDO::FETCH_INTO;
    public const FETCH_FUNC = PDO::FETCH_FUNC;
    public const FETCH_NAMED = PDO::FETCH_NAMED;
    public const FETCH_KEY_PAIR = PDO::FETCH_KEY_PAIR;

    /**
     * Constants for quote type hints
     */
    public const INT_TYPE = 0;
    public const BIGINT_TYPE = 5;
    public const FLOAT_TYPE = 2;

    /**
     * Use the PARAM_* constants if you want to use bind() instead of quote()
     */
    public const PARAM_BOOL = PDO::PARAM_BOOL;
    public const PARAM_NULL = PDO::PARAM_NULL;
    public const PARAM_INT = PDO::PARAM_INT;
    public const PARAM_STR = PDO::PARAM_STR;
    public const PARAM_LOB = PDO::PARAM_LOB;

    /**
     * Factory for Zend_Db_Adapter classes using Doctrine DBAL
     *
     * @param string $adapterName Name of the adapter class (e.g., 'Pdo_Mysql')
     * @param array $config Database configuration parameters
     * @return Varien_Db_Adapter_Pdo_Mysql
     * @throws Zend_Db_Exception
     */
    public static function factory($adapterName, $config = [])
    {
        // Normalize adapter name
        $adapterName = str_replace(['_', '-'], ' ', strtolower($adapterName));
        $adapterName = str_replace(' ', '', ucwords($adapterName));

        // Support common adapter names
        if (in_array($adapterName, ['PdoMysql', 'Mysql', 'Mysqli'])) {
            return new Varien_Db_Adapter_Pdo_Mysql($config);
        }

        throw new Zend_Db_Exception("Adapter '{$adapterName}' not supported in Doctrine DBAL compatibility layer");
    }
}
