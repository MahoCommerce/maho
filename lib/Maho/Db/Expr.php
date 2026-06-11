<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Maho\Db;

/**
 * Class for SQL expressions
 *
 * Passed as a value to a Select object, it will be inserted into the SQL
 * as-is without any quoting or escaping.
 */
class Expr implements \Stringable
{
    /**
     * Storage for the SQL expression.
     */
    protected string $_expression;

    /**
     * Instantiate an expression, which is just a string stored as
     * an instance member variable.
     *
     * @param string $expression The string containing a SQL expression.
     */
    public function __construct(string $expression)
    {
        $this->_expression = $expression;
    }

    /**
     * @return string The string of the SQL expression stored in this object.
     */
    #[\Override]
    public function __toString(): string
    {
        return $this->_expression;
    }
}
