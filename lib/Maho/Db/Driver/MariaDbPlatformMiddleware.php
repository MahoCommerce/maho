<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Maho\Db\Driver;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Middleware;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\Keywords\KeywordList;
use Doctrine\DBAL\Platforms\Keywords\MariaDB117Keywords;
use Doctrine\DBAL\Platforms\MariaDB110700Platform;
use Doctrine\DBAL\ServerVersionProvider;

/**
 * Extends the MariaDB keyword list with reserved words DBAL does not know yet.
 *
 * MariaDB 12.3 implements the Oracle-compatible TO_DATE() function as a
 * parser-level keyword (MDEV-19683), so a bare to_date column identifier is a
 * syntax error there. DBAL quotes an identifier only when the platform keyword
 * list knows it, so listing the word here makes every DDL emission site
 * backtick-quote it, on every MariaDB version that resolves to this platform.
 *
 * The KeywordList feature is deprecated upstream because DBAL 5 will quote
 * every identifier unconditionally, which subsumes this fix; until then it is
 * the only extension point (see the scoped exceptions in .phpstan.dist.neon).
 *
 * @todo Remove this middleware once https://github.com/doctrine/dbal/pull/7391
 *       is merged and released (the upstream fix subsumes it).
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
                if (!$platform instanceof MariaDB110700Platform) {
                    return $platform;
                }

                return new class extends MariaDB110700Platform {
                    #[\Override]
                    protected function createReservedKeywordsList(): KeywordList
                    {
                        return new class extends MariaDB117Keywords {
                            #[\Override]
                            protected function getKeywords(): array
                            {
                                return array_merge(parent::getKeywords(), ['TO_DATE']);
                            }
                        };
                    }
                };
            }
        };
    }
}
