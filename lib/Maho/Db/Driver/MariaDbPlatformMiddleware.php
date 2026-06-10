<?php

/**
 * Maho
 *
 * @package    Maho_Db
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace Maho\Db\Driver;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Middleware;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MariaDB110700Platform;
use Doctrine\DBAL\ServerVersionProvider;
use Maho\Db\Platform\MariaDbPlatform;

/**
 * Substitutes Maho's MariaDB platform, whose keyword list knows the words
 * newer MariaDB releases reserve but DBAL does not (see MariaDbKeywords),
 * so DBAL quotes them in generated DDL.
 */
class MariaDbPlatformMiddleware implements Middleware
{
    #[\Override]
    public function wrap(Driver $driver): Driver
    {
        return new class ($driver) extends AbstractDriverMiddleware {
            #[\Override]
            public function getDatabasePlatform(ServerVersionProvider $versionProvider): AbstractPlatform
            {
                $platform = parent::getDatabasePlatform($versionProvider);

                return $platform instanceof MariaDB110700Platform ? new MariaDbPlatform() : $platform;
            }
        };
    }
}
