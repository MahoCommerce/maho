<?php

/**
 * Maho
 *
 * @package    Maho_Db
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace Maho\Db\Platform;

use Doctrine\DBAL\Platforms\Keywords\KeywordList;
use Doctrine\DBAL\Platforms\MariaDB110700Platform;

/**
 * MariaDB platform with the extended keyword list (see MariaDbKeywords).
 * Selected by MariaDbPlatformMiddleware for MariaDB >= 11.7 connections.
 */
class MariaDbPlatform extends MariaDB110700Platform
{
    #[\Override]
    protected function createReservedKeywordsList(): KeywordList
    {
        return new MariaDbKeywords();
    }
}
