<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Maho\Db\Driver\Sqlite;

use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;

final class Driver extends AbstractDriverMiddleware
{
    #[\Override]
    public function connect(#[\SensitiveParameter] array $params): Connection
    {
        return new Connection(parent::connect($params));
    }
}
