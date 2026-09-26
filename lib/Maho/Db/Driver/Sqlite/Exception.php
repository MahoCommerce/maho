<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Maho\Db\Driver\Sqlite;

use Doctrine\DBAL\Driver\AbstractException;

final class Exception extends AbstractException
{
    public static function new(\PDOException $e): self
    {
        if ($e->errorInfo !== null) {
            [$sqlState, $code] = $e->errorInfo;
            return new self($e->getMessage(), $sqlState, $code ?? 0, $e);
        }
        return new self($e->getMessage(), null, $e->getCode(), $e);
    }
}
