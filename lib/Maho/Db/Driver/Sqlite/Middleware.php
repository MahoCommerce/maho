<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Maho\Db\Driver\Sqlite;

use Doctrine\DBAL\Driver as DriverInterface;
use Doctrine\DBAL\Driver\Middleware as MiddlewareInterface;

/**
 * Returns DECIMAL columns from SQLite the way MySQL and PostgreSQL return them
 *
 * SQLite has no decimal type. A NUMERIC(12,4) column stores 2 as an integer and 10.5 as a
 * real, so pdo_sqlite hands back int(2) and float(10.5) where the other two backends hand
 * back "2.0000" and "10.5000". Code that is wrong on MySQL and PostgreSQL then passes on
 * SQLite. This middleware reads the declared type of every result column and formats the
 * values of a DECIMAL or NUMERIC column as a string with the declared scale.
 */
final class Middleware implements MiddlewareInterface
{
    #[\Override]
    public function wrap(DriverInterface $driver): DriverInterface
    {
        return new Driver($driver);
    }
}
