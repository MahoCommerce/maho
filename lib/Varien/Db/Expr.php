<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Varien_Db
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Raw SQL expression wrapper
 *
 * Marks a string as raw SQL that should not be quoted or escaped.
 * Used to distinguish SQL expressions from column names in query building.
 *
 * This class serves as a type marker to indicate that a string should be
 * treated as a literal SQL expression rather than a value that needs quoting.
 *
 * @example
 * // Aggregate functions
 * new Varien_Db_Expr('COUNT(*)')
 * new Varien_Db_Expr('SUM(price)')
 *
 * // Mathematical expressions
 * new Varien_Db_Expr('quantity * price')
 * new Varien_Db_Expr('logins + 1')
 *
 * // MySQL functions
 * new Varien_Db_Expr('NOW()')
 * new Varien_Db_Expr("CONCAT(first_name, ' ', last_name)")
 *
 * // Complex expressions
 * new Varien_Db_Expr("CAST($expression AS SIGNED)")
 * new Varien_Db_Expr("UNHEX(HEX(CAST(column as UNSIGNED INT)))")
 */
class Varien_Db_Expr implements Stringable
{
    /**
     * Raw SQL expression string
     */
    private string $expression;

    /**
     * Constructor
     *
     * @param string $expression Raw SQL expression
     */
    public function __construct(string $expression)
    {
        $this->expression = $expression;
    }

    /**
     * Get the raw SQL expression
     */
    public function getExpression(): string
    {
        return $this->expression;
    }

    /**
     * Magic method to convert expression to string
     */
    #[\Override]
    public function __toString(): string
    {
        return $this->expression;
    }
}
